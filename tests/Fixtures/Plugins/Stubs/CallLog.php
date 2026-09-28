<?php

namespace TheatreCMS\Tests\Fixtures\Plugins\Stubs;

/**
 * Records plugin lifecycle calls so tests can assert on order.
 */
final class CallLog
{
    /**
     * @var string[]
     */
    public static array $calls = [];
}
