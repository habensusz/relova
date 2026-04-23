<?php

declare(strict_types=1);

namespace Relova\Security;

use Relova\Exceptions\QuerySecurityException;

/**
 * Defence-in-depth SQL validator.
 *
 * Relova never accepts raw SQL from outside the package — all SQL is built
 * internally by QueryExecutor. This sanitizer is a final safety net that
 * runs against every internally-generated string before execution to ensure
 * no write-capable, file-IO, or shell-execution patterns slip through.
 *
 * Patterns use \b word boundaries to avoid false-positives on legitimate
 * column names like CREATE_DATE, DELETE_FLAG, UPDATE_TS, USER_NAME, etc.
 */
final class QuerySanitizer
{
    /**
     * Pre-compiled regex patterns for blocked SQL constructs.
     *
     * @var array<string, string>
     */
    private const BLOCKED_PATTERNS = [
        'INTO OUTFILE' => '/\bINTO\s+OUTFILE\b/i',
        'INTO DUMPFILE' => '/\bINTO\s+DUMPFILE\b/i',
        'COPY TO' => '/\bCOPY\b[^;]*\bTO\b/i',
        'xp_cmdshell' => '/\bxp_cmdshell\b/i',
        'EXEC' => '/\bEXEC(UTE)?\b/i',
        'CREATE' => '/\bCREATE\s+(TABLE|DATABASE|SCHEMA|INDEX|VIEW|FUNCTION|PROCEDURE|TRIGGER|USER|ROLE)\b/i',
        'DROP' => '/\bDROP\s+(TABLE|DATABASE|SCHEMA|INDEX|VIEW|FUNCTION|PROCEDURE|TRIGGER|USER|ROLE|COLUMN)\b/i',
        'ALTER' => '/\bALTER\s+(TABLE|DATABASE|SCHEMA|INDEX|VIEW|USER|ROLE)\b/i',
        'INSERT' => '/\bINSERT\s+INTO\b/i',
        'UPDATE' => '/\bUPDATE\s+\w+\s+SET\b/i',
        'DELETE' => '/\bDELETE\s+FROM\b/i',
        'TRUNCATE' => '/\bTRUNCATE\s+(TABLE\s+)?\w+/i',
        'SLEEP' => '/\bSLEEP\s*\(/i',
        'BENCHMARK' => '/\bBENCHMARK\s*\(/i',
        'GRANT' => '/\bGRANT\b/i',
        'REVOKE' => '/\bREVOKE\b/i',
    ];

    /**
     * Throws QuerySecurityException if the SQL contains any blocked pattern,
     * does not start with SELECT (after stripping leading WITH/whitespace),
     * or contains a stacked-query terminator.
     */
    public function assertSafe(string $sql): void
    {
        $stripped = $this->stripStringLiterals($sql);

        // Check for stacked queries — semicolons outside string literals.
        $trimmed = rtrim($stripped, "; \t\n\r\0\x0B");
        if (str_contains($trimmed, ';')) {
            throw new QuerySecurityException('Stacked queries are not permitted.');
        }

        // Must be a read-only query: SELECT or WITH ... SELECT.
        $leading = ltrim($stripped);
        if (! preg_match('/^(SELECT|WITH)\b/i', $leading)) {
            throw new QuerySecurityException(
                'Only SELECT (and WITH ... SELECT) queries are permitted.'
            );
        }

        foreach (self::BLOCKED_PATTERNS as $name => $pattern) {
            if (preg_match($pattern, $stripped) === 1) {
                throw new QuerySecurityException(
                    "Query contains blocked pattern: {$name}"
                );
            }
        }
    }

    /**
     * Returns true if the SQL would pass assertSafe(), without throwing.
     */
    public function isSafe(string $sql): bool
    {
        try {
            $this->assertSafe($sql);

            return true;
        } catch (QuerySecurityException) {
            return false;
        }
    }

    /**
     * Strip single-quoted, double-quoted, and backtick-quoted string literals
     * so that pattern matching cannot be tricked by user-supplied text inside
     * a literal (e.g. WHERE name = 'DELETE FROM x').
     */
    private function stripStringLiterals(string $sql): string
    {
        // Single-quoted strings (with '' escape) and double-quoted identifiers.
        $sql = preg_replace("/'(?:''|[^'])*'/", "''", $sql) ?? $sql;
        $sql = preg_replace('/"(?:""|[^"])*"/', '""', $sql) ?? $sql;
        // Backtick-quoted identifiers (MySQL).
        $sql = preg_replace('/`(?:``|[^`])*`/', '``', $sql) ?? $sql;

        // Strip line and block comments so they cannot smuggle keywords.
        $sql = preg_replace('/--[^\r\n]*/', '', $sql) ?? $sql;
        $sql = preg_replace('|/\*.*?\*/|s', '', $sql) ?? $sql;

        return $sql;
    }
}
