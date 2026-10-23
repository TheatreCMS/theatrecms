<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

// Mirrors phpcs.xml (PSR-12 + short arrays), which is the authoritative standard. Editors run
// this fixer on save; a broader rule set such as `@auto` (PER-CS) rewrites code phpcs rejects,
// e.g. `single_line_empty_body` collapsing an empty constructor body to `) {}`.
return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder(
        (new Finder())
            ->in(__DIR__)
    )
;
