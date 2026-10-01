# Console and scheduler

`bin/theatrecms` is TheatreCMS's command-line tool, in the spirit of WP-CLI. It boots the app
through `app/bootstrap.php` exactly as a web request does (so plugins `register()` and the theme's
`functions.php` loads), then runs a [Symfony Console](https://symfony.com/doc/current/console.html)
application holding core's commands and every plugin's.

```bash
bin/theatrecms list                    # every command
bin/theatrecms help media:backfill     # one command's options
```

In DDEV, run it inside the web container: `ddev exec bin/theatrecms list`.

## Core commands

| Command | What it does |
|---|---|
| `migrate [--dry-run] [--baseline]` | Apply pending database migrations from core and plugins (see [Migrations](#migrations)) |
| `user:create-admin [--email=] [--username=] [--password=] [--force]` | Create an administrator. Options fall back to `THEATRECMS_ADMIN_EMAIL` / `_USERNAME` / `_PASSWORD`, then to prompts (password entry is hidden). Does nothing once an admin exists, unless `--force` is passed |
| `media:backfill [--dry-run]` | Register files in `www/uploads/` as media and repoint legacy image URLs at them |
| `media:regenerate-thumbnails [--dry-run] [--force] [--size=<name>]` | Generate missing image-size variants; `--force` regenerates all of them (see `documentation/Theme/image-sizes.md`) |
| `media:rename-filenames [--dry-run]` | Rename generated media filenames to SEO-friendly slugs |
| `schedule:run` | Run the scheduled tasks that are due; cron calls it every minute |
| `schedule:list` | List scheduled tasks with their last and next run |

The old root scripts (`./create-admin`, `./backfill-images`, `./regenerate-media-thumbnails`,
`./rename-media-filenames`) still work but are deprecated: they print a notice and forward to the
commands above. `./doctrine` (the Doctrine ORM console) is unchanged.

## Writing a command

A command is a Symfony `Command` subclass with an `#[AsCommand]` attribute. Its constructor
dependencies are injected by the DI container, so ask for services rather than fetching them:

```php
namespace Acme\Box\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'box-office:sync', description: 'Pull performances from the box office')]
class SyncCommand extends Command
{
    public function __construct(private readonly BoxOfficeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('upcoming', null, InputOption::VALUE_NONE, 'Only future performances');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->client->sync((bool) $input->getOption('upcoming'));
        $output->writeln("Synced {$count} performance(s).");

        return Command::SUCCESS;
    }
}
```

- Commands are loaded lazily: `bin/theatrecms list` reads the name and description from the
  attribute and only builds the command that actually runs.
- Aliases go in the name, separated by `|` (`'box-office:sync|bo:sync'`); a leading `|` hides the
  command from `list`.
- Keep the work in a service and keep the command to parsing input and printing output, so the
  same logic can run from the admin UI or a test.
- Return `Command::FAILURE` (non-zero) on failure; the scheduler records it.

### From a plugin

Return the class names from your plugin's `commands()` (see `documentation/plugins.md`):

```php
public function commands(): array
{
    return [SyncCommand::class];
}
```

A class that isn't a `Command`, or has no `#[AsCommand]`, is logged and skipped; the other
commands still load.

## Migrations

Schema changes are hand-written, timestamp-named `.sql` files (not Doctrine Migrations classes):
core's in `migrations/`, a plugin's in the directories its `migrationPaths()` returns.
`bin/theatrecms migrate` runs the pending ones in filename order across core and plugins (core
first on equal names), statement by statement, and records each file in `schema_migrations` with
its SHA-256 checksum once every statement has succeeded.

```bash
bin/theatrecms migrate --dry-run   # list pending migrations
bin/theatrecms migrate             # apply them
bin/theatrecms migrate --baseline  # record them as applied without running them
```

- **Naming**: `YYYYMMDD_short_description.sql`, or `YYYYMMDDHHMMSS_...` when several land on one
  day. Plugins use the same scheme, so their files interleave with core's by date.
- **Never edit an applied migration.** `migrate` warns about files whose checksum changed but does
  not re-run them; put the change in a new migration.
- **Keep entities and migrations in step.** A new or changed Doctrine entity needs a migration that
  produces the same schema: write the DDL by hand, or start from
  `./doctrine orm:schema-tool:update --dump-sql`. Afterwards that command should report nothing to
  update. A plugin's entity tables come from its own migrations too.
- **Don't touch the `users` / `users_*` tables**; they belong to delight-im/auth.
- A failing statement stops the run and its file is not recorded. MySQL commits DDL immediately,
  so earlier statements in that file may have taken effect; fix things up, then re-run.
- MySQL `DELIMITER` blocks (stored procedures and triggers) aren't supported.

`migrations/20261001_baseline.sql` holds the complete schema as of the runner's introduction, so an
empty database is built by `migrate` alone. The older incremental files are kept in
`migrations/legacy/` for reference; they are never run. A database created before the runner has
tables but no `schema_migrations` rows, and `migrate` refuses to run there: bring it up to date,
then run `migrate --baseline` once (see `documentation/DEPLOYMENT.md`).

## Scheduling tasks

Register a recurring task from a plugin's `register()` or a theme's `functions.php`:

```php
register_scheduled_task('box-office:sync', 'box-office:sync --upcoming', '15m');
register_scheduled_task('nightly-report', 'reports:email', '1d', timeout: 1800);
```

- **Name**: lowercase letters, digits and `-` `_` `.` `:`. Registering a name again replaces the task.
- **Command**: a `bin/theatrecms` command line (anything after `bin/theatrecms`).
- **Interval**: seconds, or a number with `s`, `m`, `h` or `d`. At least one minute, since cron
  runs the scheduler once a minute.
- **Timeout**: seconds a run may take before it is stopped (default one hour).

`register_scheduled_task()` wraps `ScheduledTaskRegistry` (`src/Scheduler/`).

### How `schedule:run` works

Each minute, for every registered task:

1. A task is due if it has never run, or if its interval has passed since its last run *started*.
   Tasks that aren't due are skipped.
2. A non-blocking file lock in `var/locks/` is taken. If an earlier run of the same task still holds
   it, the task is skipped, so a slow run never overlaps itself.
3. The task runs as a separate `php bin/theatrecms <command>` process with its timeout, so a fatal
   error or memory leak in one task can't stop the others.
4. The start and finish times, status, exit code and the last 4,000 characters of output are
   stored in the `scheduled_task_runs` table (`TheatreCMS\Models\ScheduledTaskRun`). Failures are
   also logged.

`schedule:run` exits non-zero if any task failed. Use `schedule:list` to see each task's last
status and when it is next due (times are UTC).

`bin/theatrecms migrate` creates the table; it is part of the baseline migration.

### Cron

On a server, add one crontab entry for the user that owns `var/`:

```cron
* * * * * cd /path/to/theatrecms && php bin/theatrecms schedule:run >> var/log/schedule.log 2>&1
```

Locally, DDEV has no cron by default. Install the [`ddev/ddev-cron`](https://github.com/ddev/ddev-cron)
add-on and add `.ddev/web-build/schedule.cron` (`.ddev/` is git-ignored in core):

```bash
ddev add-on get ddev/ddev-cron
printf '* * * * * cd /var/www/html && php bin/theatrecms schedule:run >> var/log/schedule.log 2>&1\n' \
  > .ddev/web-build/schedule.cron
ddev restart
```

Or run it by hand when you need it: `ddev exec bin/theatrecms schedule:run`.
