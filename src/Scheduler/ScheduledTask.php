<?php

namespace TheatreCMS\Scheduler;

use InvalidArgumentException;

/**
 * A console command line that `bin/theatrecms schedule:run` runs at most once per interval.
 */
final class ScheduledTask
{
    private const UNIT_SECONDS = ['s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400];

    /**
     * @param string $name            Unique task name, e.g. `tessitura-sync`
     * @param string $command         Console command line, e.g. `tessitura:sync --upcoming`
     * @param int    $intervalSeconds Minimum time between runs
     * @param int    $timeoutSeconds  A run is stopped after this long
     */
    public function __construct(
        public readonly string $name,
        public readonly string $command,
        public readonly int $intervalSeconds,
        public readonly int $timeoutSeconds = 3600,
    ) {
    }

    /**
     * Parses an interval given as seconds (`300`) or a duration string (`30s`, `15m`, `1h`, `1d`).
     *
     * @throws InvalidArgumentException for anything else, or an interval under one minute
     */
    public static function parseInterval(string|int $interval): int
    {
        if (is_int($interval) || ctype_digit($interval)) {
            $seconds = (int) $interval;
        } elseif (preg_match('/^(\d+)\s*([smhd])$/i', trim($interval), $matches)) {
            $seconds = (int) $matches[1] * self::UNIT_SECONDS[strtolower($matches[2])];
        } else {
            throw new InvalidArgumentException(sprintf(
                'Invalid schedule interval "%s"; use seconds or a duration such as 15m, 1h or 1d.',
                $interval,
            ));
        }

        // schedule:run is driven by a once-a-minute cron entry, so shorter intervals can't be honoured.
        if ($seconds < 60) {
            throw new InvalidArgumentException(sprintf(
                'Schedule interval "%s" is under one minute, the scheduler\'s resolution.',
                $interval,
            ));
        }

        return $seconds;
    }
}
