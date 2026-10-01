<?php

// Stands in for bin/theatrecms in ProcessTaskRunnerTest: `<command> [arg]`.
$command = $argv[1] ?? '';
$arg = $argv[2] ?? '';

switch ($command) {
    case 'echo':
        echo $arg;
        exit(0);
    case 'fail':
        fwrite(STDERR, 'something broke');
        exit((int) $arg);
    case 'sleep':
        sleep((int) $arg);
        exit(0);
}

exit(64);
