<?php

namespace TheatreCMS\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Cache-busting URLs for files under the public web root (`www/`), so a deploy reaches
 * browsers that already cached the old copy.
 *
 * - `asset('/assets/js/menu.js')` gives `/assets/js/menu.js?v=<mtime>`: use it for every
 *   `src`/`href` that points at a local file.
 * - `asset_import_map({...})` renders an import map that versions the same way every module
 *   in `/assets/js/`, so `import ... from '/assets/js/editorjs-config.js'` (in templates or in
 *   other modules) loads the current file too. A page may have only one import map, so pass
 *   any other entries it needs as the argument.
 *
 * The version is the file's modification time: it changes whenever the file does, with no
 * build step. A path that isn't a local file is returned unchanged.
 */
class AssetExtension extends AbstractExtension
{
    /** Folder (relative to the web root) whose modules the import map versions. */
    private const MODULE_DIR = '/assets/js';

    /** @var array<string, string> */
    private array $versioned = [];

    public function __construct(private readonly string $publicDir)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset', [$this, 'asset']),
            new TwigFunction('asset_import_map', [$this, 'importMap'], ['is_safe' => ['html']]),
        ];
    }

    public function asset(string $path): string
    {
        if (isset($this->versioned[$path])) {
            return $this->versioned[$path];
        }

        $file = $this->localFile($path);
        $mtime = $file !== null ? @filemtime($file) : false;

        if ($mtime === false) {
            return $this->versioned[$path] = $path;
        }

        $separator = str_contains($path, '?') ? '&' : '?';

        return $this->versioned[$path] = $path . $separator . 'v=' . dechex($mtime);
    }

    /**
     * @param array<string, string> $imports extra specifier => URL entries; a URL that is a
     *                                       local file is versioned, a prefix (ending in `/`) is kept as is
     */
    public function importMap(array $imports = []): string
    {
        $map = [];

        foreach (glob($this->publicDir . self::MODULE_DIR . '/*.js') ?: [] as $file) {
            $url = self::MODULE_DIR . '/' . basename($file);
            $map[$url] = $this->asset($url);
        }

        foreach ($imports as $specifier => $url) {
            $map[$specifier] = str_ends_with($url, '/') ? $url : $this->asset($url);
        }

        $json = json_encode(
            ['imports' => $map],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR
        );

        return '<script type="importmap">' . $json . '</script>';
    }

    /**
     * The file a root-relative URL points at, or null for anything else (absolute or
     * protocol-relative URLs, or a path that escapes the web root).
     */
    private function localFile(string $path): ?string
    {
        $urlPath = parse_url($path, PHP_URL_PATH);

        $isRootRelative = str_starts_with($path, '/') && !str_starts_with($path, '//');

        if (!is_string($urlPath) || !$isRootRelative || str_contains($urlPath, '..')) {
            return null;
        }

        $file = $this->publicDir . $urlPath;

        return is_file($file) ? $file : null;
    }
}
