<?php

namespace App\WiseAi\Intelligence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Sandbox isolation for BI / fleet — Playground & eval must not inflate ecosystem stats.
 * MySQL JSON booleans are unreliable via Eloquent where(json->k, false); use UNQUOTE.
 */
class SandboxScope
{
    public static function excludeTurnsSql(string $column = 'config_snapshot'): string
    {
        return "COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.sandbox')), 'false') NOT IN ('true', '1')";
    }

    /**
     * @param  Builder<*>|QueryBuilder  $q
     */
    public static function excludeTurns($q, string $column = 'config_snapshot'): void
    {
        $q->whereRaw(self::excludeTurnsSql($column));
    }
}
