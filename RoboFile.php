<?php

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see https://robo.li/
 */

use Robo\Result;
use Robo\Tasks;

class RoboFile extends Tasks
{
    private const MAIN_BRANCH = 'main';
    private const REMOTE = 'origin';

    // define public methods as commands
    public function tests(): Result
    {
        return $this->taskPHPUnit()
            ->configFile('phpunit.xml.dist')
            ->run();
    }

    public function phpstan(): Result
    {
        return $this->taskExec('./vendor/bin/phpstan analyse -c phpstan.neon.dist --memory-limit=1G')->run();
    }

    /**
     * @option errors-only Ignore warnings (exit non-zero only on errors)
     */
    public function lint(array $opts = ['errors-only' => false]): Result
    {
        return $this->taskExec('./vendor/bin/phpcs' . ($opts['errors-only'] ? ' -n' : ''))->run();
    }

    /**
     * Tag a release and publish it on GitHub.
     *
     * The version is an explicit semver (0.2.0, v0.2.0, 0.2.0-alpha.1) or a bump
     * of the latest tag: major, minor, patch, or pre (increments the -alpha.N style
     * counter of a prerelease tag). With no tags yet, a bump starts at 0.1.0.
     *
     * Git tags are the only record of the version; nothing in the repo is edited.
     *
     * @param string $version Explicit version or major|minor|patch|pre
     * @option dry-run Run the checks and show the notes, but tag and publish nothing
     * @option draft Create the GitHub release as a draft
     * @option no-checks Skip tests, phpstan and phpcs
     * @option no-edit Use the generated notes without opening $EDITOR
     */
    public function release(
        string $version = 'patch',
        array $opts = ['dry-run' => false, 'draft' => false, 'no-checks' => false, 'no-edit' => false]
    ): int {
        $dryRun = (bool) $opts['dry-run'];

        $this->io()->section('Preflight');
        $problems = $this->releasePreflight();
        foreach ($problems as $problem) {
            $dryRun ? $this->io()->warning($problem) : $this->io()->error($problem);
        }
        if ($problems !== [] && !$dryRun) {
            return 1;
        }

        $previous = $this->latestTag();
        $tag = 'v' . $this->nextVersion($previous === null ? null : ltrim($previous, 'v'), $version);

        if ($this->tagExists($tag)) {
            $this->io()->error("Tag $tag already exists locally or on " . self::REMOTE . '.');
            return 1;
        }

        if (!$opts['no-checks']) {
            $this->io()->section('Checks');
            $checks = [
                'tests' => fn () => $this->tests(),
                'phpstan' => fn () => $this->phpstan(),
                'phpcs' => fn () => $this->lint(['errors-only' => true]),
            ];
            foreach ($checks as $check => $run) {
                if (!$run()->wasSuccessful()) {
                    $this->io()->error("$check failed; not releasing.");
                    return 1;
                }
            }
        }

        $notesFile = $this->writeReleaseNotes($previous);
        if (!$opts['no-edit']) {
            $editor = getenv('VISUAL') ?: getenv('EDITOR') ?: 'vi';
            $this->taskExec($editor . ' ' . escapeshellarg($notesFile))->interactive(true)->run();
        }
        $notes = trim((string) file_get_contents($notesFile));
        if ($notes === '') {
            $this->io()->error('Release notes are empty; aborting.');
            return 1;
        }

        $prerelease = str_contains($tag, '-');
        $this->io()->section("Release $tag");
        $this->io()->definitionList(
            ['Previous tag' => $previous ?? '(none)'],
            ['Commit' => $this->git('rev-parse --short HEAD')],
            ['Type' => ($opts['draft'] ? 'draft ' : '') . ($prerelease ? 'prerelease' : 'release')],
        );
        $this->io()->text($notes);
        $this->io()->newLine();

        $gh = sprintf(
            'gh release create %s --verify-tag --title %s --notes-file %s%s%s',
            escapeshellarg($tag),
            escapeshellarg($tag),
            escapeshellarg($notesFile),
            $prerelease ? ' --prerelease' : '',
            $opts['draft'] ? ' --draft' : ''
        );
        $commands = [
            sprintf('git tag -a %s -F %s', escapeshellarg($tag), escapeshellarg($notesFile)),
            sprintf('git push %s %s', self::REMOTE, escapeshellarg('refs/tags/' . $tag)),
            $gh,
        ];

        if ($dryRun) {
            $this->io()->note("Dry run; would run:\n" . implode("\n", $commands));
            @unlink($notesFile);
            return 0;
        }

        if (!$this->confirm("Tag and publish $tag?")) {
            $this->io()->text('Aborted; nothing was tagged.');
            @unlink($notesFile);
            return 1;
        }

        $stack = $this->taskExecStack()->stopOnFail();
        foreach ($commands as $command) {
            $stack->exec($command);
        }
        $result = $stack->run();
        @unlink($notesFile);

        if (!$result->wasSuccessful()) {
            $this->io()->error(
                "Release failed part-way. Check whether tag $tag exists locally (git tag -d $tag) "
                . 'or on ' . self::REMOTE . " (git push --delete " . self::REMOTE . " $tag) before retrying."
            );
            return 1;
        }

        $this->io()->success("Released $tag");
        return 0;
    }

    /**
     * @return string[] Problems that block a release.
     */
    private function releasePreflight(): array
    {
        $problems = [];

        if ($this->git('status --porcelain') !== '') {
            $problems[] = 'Working tree has uncommitted changes.';
        }

        $branch = $this->git('rev-parse --abbrev-ref HEAD');
        if ($branch !== self::MAIN_BRANCH) {
            $problems[] = sprintf('On "%s"; releases are cut from %s.', $branch, self::MAIN_BRANCH);
        }

        $this->git(sprintf('fetch --quiet --tags %s %s', self::REMOTE, self::MAIN_BRANCH));
        $remoteRef = self::REMOTE . '/' . self::MAIN_BRANCH;
        if ($this->git('rev-parse HEAD') !== $this->git('rev-parse ' . $remoteRef)) {
            $problems[] = "HEAD is not the same commit as $remoteRef; push or pull first.";
        }

        exec('gh auth status 2>&1', $output, $status);
        if ($status !== 0) {
            $problems[] = 'gh is not authenticated; run `gh auth login`.';
        }

        return $problems;
    }

    private function latestTag(): ?string
    {
        $tags = array_filter(explode("\n", $this->git("tag --list 'v*' --sort=-v:refname")));
        foreach ($tags as $tag) {
            if ($this->parseVersion(ltrim($tag, 'v')) !== null) {
                return $tag;
            }
        }
        return null;
    }

    private function tagExists(string $tag): bool
    {
        if ($this->git('tag --list ' . escapeshellarg($tag)) !== '') {
            return true;
        }
        return $this->git(sprintf('ls-remote --tags %s %s', self::REMOTE, escapeshellarg('refs/tags/' . $tag))) !== '';
    }

    private function nextVersion(?string $previous, string $requested): string
    {
        $requested = ltrim(trim($requested), 'v');

        if (!in_array($requested, ['major', 'minor', 'patch', 'pre'], true)) {
            if ($this->parseVersion($requested) === null) {
                throw new InvalidArgumentException("\"$requested\" is not a semver version or major|minor|patch|pre.");
            }
            return $requested;
        }

        if ($previous === null) {
            return '0.1.0';
        }

        [$major, $minor, $patch, $preLabel, $preNumber] = $this->parseVersion($previous);

        return match ($requested) {
            'major' => ($major + 1) . '.0.0',
            'minor' => "$major." . ($minor + 1) . '.0',
            'patch' => $preLabel !== null ? "$major.$minor.$patch" : "$major.$minor." . ($patch + 1),
            'pre' => $preLabel !== null
                ? "$major.$minor.$patch-$preLabel." . ($preNumber + 1)
                : throw new InvalidArgumentException(
                    "$previous is not a prerelease; pass an explicit version such as $major."
                    . ($minor + 1) . '.0-alpha.1.'
                ),
        };
    }

    /**
     * @return array{int, int, int, ?string, int}|null
     */
    private function parseVersion(string $version): ?array
    {
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-([a-z]+)\.(\d+))?$/', $version, $m)) {
            return null;
        }
        return [(int) $m[1], (int) $m[2], (int) $m[3], ($m[4] ?? '') !== '' ? $m[4] : null, (int) ($m[5] ?? 0)];
    }

    private function writeReleaseNotes(?string $previous): string
    {
        $file = tempnam(sys_get_temp_dir(), 'theatrecms-release-');

        if ($previous === null) {
            $notes = "First alpha release of TheatreCMS.\n";
        } else {
            $log = $this->git(sprintf("log %s..HEAD --no-merges --format='- %%s'", escapeshellarg($previous)));
            $notes = "## Changes\n\n" . ($log !== '' ? $log : '- (no commits since ' . $previous . ')') . "\n";
        }

        file_put_contents($file, $notes);
        return $file;
    }

    private function git(string $args): string
    {
        exec('git ' . $args . ' 2>/dev/null', $output);
        return trim(implode("\n", $output));
    }
}
