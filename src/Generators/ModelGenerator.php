<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModelGenerator extends BaseGenerator
{
    public function generate(string $className, string $table, bool $timestamps, bool $softDeletes): string
    {
        $columns = $this->getColumns($table);
        $fillable = $this->buildFillable($columns, $softDeletes, $timestamps);
        $casts = $this->buildCasts($columns, $softDeletes);
        $relationships = $this->buildRelationships($table, $className, $columns);

        $softDeletesUse = $softDeletes ? "\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;" : '';
        $softDeletesTrait = $softDeletes ? "\n    use SoftDeletes;\n" : '';
        $timestampsLine = $timestamps ? '' : "\n    public \$timestamps = false;";

        $content = $this->render($this->getStub('model'), [
            'Namespace' => "App\\Models\\{$className}",
            'ClassName' => $className,
            'tableName' => $table,
            'SoftDeletesUse' => $softDeletesUse,
            'SoftDeletesTrait' => $softDeletesTrait,
            'Fillable' => $fillable,
            'Casts' => $casts,
            'TimestampsLine' => $timestampsLine,
            'Relationships' => $relationships,
        ]);

        $dir = app_path("Models/{$className}");
        $path = "{$dir}/{$className}.php";
        $this->write($path, $content, true);
        return $path;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relationship detection
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build all relationship methods for this model.
     * Detects:
     *   - belongsTo  — for every FK column on this table
     *   - hasMany    — for every FK on other tables that points back here
     *   - belongsToMany — for pure pivot tables (exactly 2 FK columns, no extra cols)
     */
    private function buildRelationships(string $table, string $className, array $columns): string
    {
        $methods = [];

        // 1. Try real FK constraints first
        $outgoing = $this->getOutgoingFks($table);
        $incoming = $this->getIncomingFks($table);

        // 2. If no constraints found, fall back to naming convention
        if (empty($outgoing)) {
            $outgoing = $this->guessOutgoingFks($table, $columns);
        }

        foreach ($outgoing as $fk) {
            $methods[] = $this->buildBelongsTo($fk);
        }

        foreach ($incoming as $fk) {
            if ($this->isPivotTable($fk['from_table'])) {
                $methods[] = $this->buildBelongsToMany($fk, $table);
            } else {
                $methods[] = $this->buildHasMany($fk);
            }
        }

        // 3. If no real incoming FKs, guess hasMany from other tables' _id columns
        if (empty($incoming)) {
            foreach ($this->guessIncomingFks($table, $columns) as $fk) {
                $methods[] = $this->buildHasMany($fk);
            }
        }

        if (empty($methods)) {
            return '';
        }

        return "\n" . implode("\n\n", $methods) . "\n";
    }

    /**
     * Guess belongsTo by scanning columns named like "something_id"
     * and checking if a table named "somethings" exists.
     */
    private function guessOutgoingFks(string $table, array $columns): array
    {
        $existingTables = $this->getAllTables();
        $fks = [];

        foreach ($columns as $col) {
            $field = $col->Field;

            if (!str_ends_with($field, '_id')) {
                continue;
            }

            // e.g. "department_id" -> guess table "departments"
            $guessedBase = preg_replace('/_id$/', '', $field);
            $guessedTable = Str::plural($guessedBase);

            if (in_array($guessedTable, $existingTables)) {
                $fks[] = [
                    'fk_column' => $field,
                    'referenced_table' => $guessedTable,
                    'referenced_column' => 'id',
                ];
            }
        }

        return $fks;
    }

    /**
     * Guess hasMany by scanning all other tables for a column named
     * "{singular_of_this_table}_id".
     */
    private function guessIncomingFks(string $table, array $columns): array
    {
        $existingTables = $this->getAllTables();
        $fks = [];

        // Check both singular and plural forms of the expected FK column
        $singular = Str::singular($table);   // e.g. "voucher"
        $expectedCols = array_unique([
            $singular . '_id',                   // "voucher_id"
            Str::plural($singular) . '_id',      // "vouchers_id" (rare but safe)
            $table . '_id',                      // exact table name + _id
        ]);

        foreach ($existingTables as $otherTable) {
            if ($otherTable === $table) {
                continue;
            }

            try {
                $otherColumns = DB::select("SHOW COLUMNS FROM `{$otherTable}`");
            } catch (\Throwable) {
                continue;
            }

            $fields = collect($otherColumns)->pluck('Field');

            foreach ($expectedCols as $expectedCol) {
                if ($fields->contains($expectedCol)) {
                    $fks[] = [
                        'from_table' => $otherTable,
                        'fk_column' => $expectedCol,
                        'referenced_column' => 'id',
                    ];
                    break; // found one match for this table, move on
                }
            }
        }

        return $fks;
    }

    private function getAllTables(): array
    {
        $db = config('database.connections.' . config('database.default') . '.database');
        $raw = DB::select('SHOW TABLES');
        $key = 'Tables_in_' . $db;

        return collect($raw)
            ->pluck($key)
            ->reject(fn($t) => in_array($t, config('prism-init.exclude_tables', ['migrations'])))
            ->values()
            ->toArray();
    }

    /**
     * FK rows where this table is the child (owns the FK column).
     * Returns: [ ['fk_column', 'referenced_table', 'referenced_column'], … ]
     */
    private function getOutgoingFks(string $table): array
    {
        $db = config('database.connections.' . config('database.default') . '.database');

        $rows = DB::select("
            SELECT
                kcu.COLUMN_NAME        AS fk_column,
                kcu.REFERENCED_TABLE_NAME  AS referenced_table,
                kcu.REFERENCED_COLUMN_NAME AS referenced_column
            FROM information_schema.KEY_COLUMN_USAGE kcu
            JOIN information_schema.TABLE_CONSTRAINTS tc
              ON tc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
             AND tc.TABLE_SCHEMA      = kcu.TABLE_SCHEMA
             AND tc.TABLE_NAME        = kcu.TABLE_NAME
            WHERE tc.CONSTRAINT_TYPE  = 'FOREIGN KEY'
              AND kcu.TABLE_SCHEMA    = ?
              AND kcu.TABLE_NAME      = ?
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ", [$db, $table]);

        return array_map(fn($r) => [
            'fk_column' => $r->fk_column,
            'referenced_table' => $r->referenced_table,
            'referenced_column' => $r->referenced_column,
        ], $rows);
    }

    /**
     * FK rows where other tables reference this table (parent side).
     * Returns: [ ['from_table', 'fk_column', 'referenced_column'], … ]
     */
    private function getIncomingFks(string $table): array
    {
        $db = config('database.connections.' . config('database.default') . '.database');

        $rows = DB::select("
            SELECT
                kcu.TABLE_NAME             AS from_table,
                kcu.COLUMN_NAME            AS fk_column,
                kcu.REFERENCED_COLUMN_NAME AS referenced_column
            FROM information_schema.KEY_COLUMN_USAGE kcu
            JOIN information_schema.TABLE_CONSTRAINTS tc
              ON tc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
             AND tc.TABLE_SCHEMA      = kcu.TABLE_SCHEMA
             AND tc.TABLE_NAME        = kcu.TABLE_NAME
            WHERE tc.CONSTRAINT_TYPE  = 'FOREIGN KEY'
              AND kcu.TABLE_SCHEMA    = ?
              AND kcu.REFERENCED_TABLE_NAME = ?
              AND kcu.REFERENCED_COLUMN_NAME IS NOT NULL
        ", [$db, $table]);

        return array_map(fn($r) => [
            'from_table' => $r->from_table,
            'fk_column' => $r->fk_column,
            'referenced_column' => $r->referenced_column,
        ], $rows);
    }

    /**
     * A pivot table has exactly 2 FK columns and no extra non-FK, non-timestamp columns.
     */
    private function isPivotTable(string $table): bool
    {
        $db = config('database.connections.' . config('database.default') . '.database');
        $fkCount = DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM information_schema.KEY_COLUMN_USAGE kcu
            JOIN information_schema.TABLE_CONSTRAINTS tc
              ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
             AND tc.TABLE_SCHEMA    = kcu.TABLE_SCHEMA
             AND tc.TABLE_NAME      = kcu.TABLE_NAME
            WHERE tc.CONSTRAINT_TYPE = 'FOREIGN KEY'
              AND kcu.TABLE_SCHEMA   = ?
              AND kcu.TABLE_NAME     = ?
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ", [$db, $table])->cnt;

        if ((int) $fkCount !== 2) {
            return false;
        }

        // Check there are no extra columns beyond the two FK cols + id/timestamps
        $allColumns = DB::select("SHOW COLUMNS FROM `{$table}`");
        $pivot_skip = ['id', 'created_at', 'updated_at', 'deleted_at'];

        $fkCols = array_column($this->getOutgoingFks($table), 'fk_column');

        $extraCols = collect($allColumns)
            ->pluck('Field')
            ->reject(fn($f) => in_array($f, $pivot_skip) || in_array($f, $fkCols))
            ->values();

        return $extraCols->isEmpty();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Method builders
    // ─────────────────────────────────────────────────────────────────────────

    private function buildBelongsTo(array $fk): string
    {
        // fk_column: e.g. "department_id"  →  method: "department"
        $relatedClass = ucfirst(Str::camel(Str::singular($fk['referenced_table'])));
        $methodName = Str::camel(Str::singular(
            preg_replace('/_id$/', '', $fk['fk_column']) ?: $fk['fk_column']
        ));
        $fkArg = $fk['fk_column'] !== Str::snake($methodName) . '_id'
            ? ", '{$fk['fk_column']}'"
            : '';
        $ownerArg = $fk['referenced_column'] !== 'id'
            ? ", '{$fk['referenced_column']}'"
            : '';

        return <<<PHP
    public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return \$this->belongsTo(\\App\\Models\\{$relatedClass}\\{$relatedClass}::class{$fkArg}{$ownerArg});
    }
PHP;
    }

    private function buildHasMany(array $fk): string
    {
        // from_table: e.g. "employees"  →  method: "employees"
        $relatedClass = ucfirst(Str::camel(Str::singular($fk['from_table'])));
        $methodName = Str::camel($fk['from_table']); // plural, e.g. "employees"

        $fkArg = $fk['fk_column'] !== Str::snake($relatedClass) . '_id'
            ? ", '{$fk['fk_column']}'"
            : '';
        $localArg = $fk['referenced_column'] !== 'id'
            ? ", '{$fk['referenced_column']}'"
            : '';

        return <<<PHP
    public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return \$this->hasMany(\\App\\Models\\{$relatedClass}\\{$relatedClass}::class{$fkArg}{$localArg});
    }
PHP;
    }

    private function buildBelongsToMany(array $fk, string $ownTable): string
    {
        // Find the OTHER FK on the pivot table (not the one pointing to $ownTable)
        $pivotFks = $this->getOutgoingFks($fk['from_table']);
        $otherFk = collect($pivotFks)
            ->firstWhere('referenced_table', '!=', $ownTable);

        if (!$otherFk) {
            return $this->buildHasMany($fk); // fallback: treat as hasMany
        }

        $relatedClass = ucfirst(Str::camel(Str::singular($otherFk['referenced_table'])));
        $methodName = Str::camel($otherFk['referenced_table']); // plural

        $selfFkCol = $fk['fk_column'];
        $relatedFkCol = $otherFk['fk_column'];
        $pivotTable = $fk['from_table'];

        return <<<PHP
    public function {$methodName}(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return \$this->belongsToMany(
            \\App\\Models\\{$relatedClass}\\{$relatedClass}::class,
            '{$pivotTable}',
            '{$selfFkCol}',
            '{$relatedFkCol}'
        );
    }
PHP;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Existing helpers (unchanged)
    // ─────────────────────────────────────────────────────────────────────────

    private function getColumns(string $table): array
    {
        try {
            return DB::select("SHOW COLUMNS FROM `{$table}`");
        } catch (\Throwable) {
            return [];
        }
    }

    private function buildFillable(array $columns, bool $softDeletes, bool $timestamps): string
    {
        $skip = ['id'];
        if ($timestamps) {
            $skip[] = 'created_at';
            $skip[] = 'updated_at';
        }
        if ($softDeletes) {
            $skip[] = 'deleted_at';
        }

        $fields = collect($columns)
            ->pluck('Field')
            ->reject(fn($f) => in_array($f, $skip))
            ->map(fn($f) => "        '{$f}'")
            ->join(",\n");

        return "[\n{$fields},\n    ]";
    }

    private function buildCasts(array $columns, bool $softDeletes): string
    {
        $typeMap = [
            'tinyint(1)' => 'boolean',
            'tinyint' => 'integer',
            'smallint' => 'integer',
            'mediumint' => 'integer',
            'int' => 'integer',
            'bigint' => 'integer',
            'float' => 'float',
            'double' => 'float',
            'decimal' => 'decimal:2',
            'date' => 'date',
            'datetime' => 'datetime',
            'timestamp' => 'datetime',
            'json' => 'array',
        ];

        $lines = collect($columns)
            ->reject(fn($col) => in_array($col->Field, ['id', 'created_at', 'updated_at', 'deleted_at']))
            ->map(function ($col) use ($typeMap) {
                $baseType = strtolower(preg_replace('/\(.*\)/', '', $col->Type));
                $fullType = strtolower($col->Type);
                $cast = $typeMap[$fullType] ?? $typeMap[$baseType] ?? null;
                return $cast ? "        '{$col->Field}' => '{$cast}'" : null;
            })
            ->filter()
            ->values();

        if ($softDeletes) {
            $lines->push("        'deleted_at' => 'datetime'");
        }

        return $lines->isEmpty() ? '[]' : "[\n" . $lines->join(",\n") . ",\n    ]";
    }

}