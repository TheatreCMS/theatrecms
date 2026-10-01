<?php

namespace TheatreCMS\Migrations;

/**
 * Splits a migration file into single statements, so each runs (and fails) on its own: PDO's
 * multi-statement exec() can silently skip errors after the first statement.
 *
 * Semicolons inside quoted strings, quoted identifiers and comments don't end a statement. Comments
 * are kept with the statement they precede; a piece that is only comments is dropped. MySQL
 * `DELIMITER` blocks (stored routines) aren't supported.
 */
final class SqlSplitter
{
    /**
     * @return string[]
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($char === "'" || $char === '"' || $char === '`') {
                $end = self::quoteEnd($sql, $i, $char);
                $current .= substr($sql, $i, $end - $i + 1);
                $i = $end;
            } elseif (($char === '-' && $next === '-') || $char === '#') {
                $end = strpos($sql, "\n", $i);
                $end = $end === false ? $length - 1 : $end;
                $current .= substr($sql, $i, $end - $i + 1);
                $i = $end;
            } elseif ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $end = $end === false ? $length - 1 : $end + 1;
                $current .= substr($sql, $i, $end - $i + 1);
                $i = $end;
            } elseif ($char === ';') {
                self::add($statements, $current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        self::add($statements, $current);

        return $statements;
    }

    /**
     * Index of the quote closing the one at $start; a doubled quote or a backslash escapes it.
     */
    private static function quoteEnd(string $sql, int $start, string $quote): int
    {
        $length = strlen($sql);
        for ($i = $start + 1; $i < $length; $i++) {
            if ($sql[$i] === '\\' && $quote !== '`') {
                $i++;
            } elseif ($sql[$i] === $quote) {
                if (($sql[$i + 1] ?? '') !== $quote) {
                    return $i;
                }
                $i++;
            }
        }

        return $length - 1;
    }

    /**
     * @param string[] $statements
     */
    private static function add(array &$statements, string $statement): void
    {
        $statement = trim($statement);
        if ($statement !== '' && trim(self::stripComments($statement)) !== '') {
            $statements[] = $statement;
        }
    }

    private static function stripComments(string $sql): string
    {
        return (string) preg_replace(['~/\*(?!!).*?\*/~s', '~(--|#)[^\n]*~'], '', $sql);
    }
}
