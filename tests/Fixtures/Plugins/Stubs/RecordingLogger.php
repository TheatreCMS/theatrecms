<?php

namespace TheatreCMS\Tests\Fixtures\Plugins\Stubs;

use Psr\Log\AbstractLogger;

/**
 * In-memory PSR-3 logger for asserting what was logged.
 */
final class RecordingLogger extends AbstractLogger
{
    /**
     * @var array<int, array{level: mixed, message: string, context: array<string, mixed>}>
     */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }

    /**
     * @return string[] packages named in logged errors
     */
    public function failedPackages(): array
    {
        return array_map(static fn(array $record): string => $record['context']['package'] ?? '', $this->records);
    }
}
