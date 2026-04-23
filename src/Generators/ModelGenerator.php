<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModelGenerator extends BaseGenerator
{
    public function generate(string $className, string $table, bool $timestamps, bool $softDeletes): string
    {
        $columns  = $this->getColumns($table);
        $fillable = $this->buildFillable($columns, $softDeletes, $timestamps);
        $casts    = $this->buildCasts($columns, $softDeletes);

        $softDeletesUse   = $softDeletes ? "\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;" : '';
        $softDeletesTrait = $softDeletes ? "\n    use SoftDeletes;\n" : '';
        $timestampsLine   = $timestamps  ? '' : "\n    public \$timestamps = false;";

        $content = $this->render($this->getStub('model'), [
            'Namespace'        => "App\\Models\\{$className}",
            'ClassName'        => $className,
            'tableName'        => $table,
            'SoftDeletesUse'   => $softDeletesUse,
            'SoftDeletesTrait' => $softDeletesTrait,
            'Fillable'         => $fillable,
            'Casts'            => $casts,
            'TimestampsLine'   => $timestampsLine,
        ]);

        $dir  = app_path("Models/{$className}");
        $path = "{$dir}/{$className}.php";
        $this->write($path, $content, true);
        return $path;
    }

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
            ->reject(fn ($f) => in_array($f, $skip))
            ->map(fn ($f) => "        '{$f}'")
            ->join(",\n");

        return "[\n{$fields},\n    ]";
    }

    private function buildCasts(array $columns, bool $softDeletes): string
    {
        $typeMap = [
            'tinyint(1)' => 'boolean',
            'tinyint'    => 'integer',
            'smallint'   => 'integer',
            'mediumint'  => 'integer',
            'int'        => 'integer',
            'bigint'     => 'integer',
            'float'      => 'float',
            'double'     => 'float',
            'decimal'    => 'decimal:2',
            'date'       => 'date',
            'datetime'   => 'datetime',
            'timestamp'  => 'datetime',
            'json'       => 'array',
        ];

        $lines = collect($columns)
            ->reject(fn ($col) => in_array($col->Field, ['id', 'created_at', 'updated_at', 'deleted_at']))
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

        if ($lines->isEmpty()) {
            return '[]';
        }

        return "[\n" . $lines->join(",\n") . ",\n    ]";
    }
}
