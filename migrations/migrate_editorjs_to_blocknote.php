<?php

/**
 * One-time migration: converts EditorJS JSON to BlockNote JSON in all content columns.
 *
 * Run from the project root (or inside DDEV):
 *   php migrations/migrate_editorjs_to_blocknote.php
 *
 * Skips rows that are already in BlockNote format (JSON array starting with '[').
 * Wraps each table in a transaction; rolls back on per-row error and reports which
 * row failed. Safe to re-run — already-migrated rows are skipped.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';

$container = require APP_ROOT . '/app/bootstrap.php';

use Doctrine\ORM\EntityManager;
use TheatreCMS\Text\EditorJsToBlockNoteConverter;

$em = $container->get(EntityManager::class);
$conn = $em->getConnection();
$converter = new EditorJsToBlockNoteConverter();

$targets = [
    ['table' => 'posts',       'col' => 'content',     'nullable' => false],
    ['table' => 'pages',       'col' => 'content',     'nullable' => false],
    ['table' => 'productions', 'col' => 'description', 'nullable' => true],
    ['table' => 'works',       'col' => 'description', 'nullable' => true],
];

$totalConverted = 0;
$totalSkipped   = 0;
$totalErrors    = 0;

foreach ($targets as $target) {
    $table = $target['table'];
    $col   = $target['col'];

    echo "Processing {$table}.{$col}…\n";

    $rows = $conn->fetchAllAssociative("SELECT id, {$col} FROM {$table}");
    $converted = 0;
    $skipped   = 0;
    $errors    = 0;

    $conn->beginTransaction();
    try {
        foreach ($rows as $row) {
            $id    = $row['id'];
            $value = $row[$col];

            if ($value === null || trim($value) === '') {
                $skipped++;
                continue;
            }

            $first = substr(ltrim($value), 0, 1);

            // Already BlockNote JSON (starts with '[')
            if ($first === '[') {
                $skipped++;
                continue;
            }

            // Convert EditorJS JSON (starts with '{') or any other string
            try {
                $newValue = $converter->convert($value);
            } catch (\Throwable $e) {
                echo "  ERROR row id={$id}: " . $e->getMessage() . "\n";
                $errors++;
                continue;
            }

            $conn->executeStatement(
                "UPDATE {$table} SET {$col} = ? WHERE id = ?",
                [$newValue, $id]
            );
            $converted++;
        }
        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollBack();
        echo "  ROLLED BACK {$table}.{$col}: " . $e->getMessage() . "\n";
        $errors += count($rows) - $skipped - $converted;
    }

    echo "  Converted: {$converted}  Skipped: {$skipped}  Errors: {$errors}\n";
    $totalConverted += $converted;
    $totalSkipped   += $skipped;
    $totalErrors    += $errors;
}

echo "\nDone. Total — Converted: {$totalConverted}  Skipped: {$totalSkipped}  Errors: {$totalErrors}\n";
