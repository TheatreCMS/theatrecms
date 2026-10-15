<?php

namespace TheatreCMS\Tests\Includes;

/**
 * Runs code while collecting the level of every user notice, warning or deprecation it raises,
 * instead of letting PHPUnit report them, so a test can assert that a notice was (or wasn't)
 * raised and that execution carried on.
 */
trait CapturesUserNotices
{
    /**
     * @return array{mixed, list<int>} the callback's return value and the levels raised, in order
     */
    protected function captureUserNotices(callable $callback): array
    {
        $levels = [];
        set_error_handler(static function (int $level) use (&$levels): bool {
            $levels[] = $level;

            return true;
        }, E_USER_NOTICE | E_USER_WARNING | E_USER_DEPRECATED);

        try {
            $result = $callback();
        } finally {
            restore_error_handler();
        }

        return [$result, $levels];
    }
}
