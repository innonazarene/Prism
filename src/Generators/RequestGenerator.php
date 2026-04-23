<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

use Illuminate\Support\Facades\DB;

class RequestGenerator extends BaseGenerator
{
    public function generateStore(string $className, bool $grouped, string $table = ''): string
    {
        return $this->generate('Store', $className, $grouped, $table, required: true);
    }

    public function generateUpdate(string $className, bool $grouped, string $table = ''): string
    {
        return $this->generate('Update', $className, $grouped, $table, required: false);
    }

    private function generate(string $action, string $className, bool $grouped, string $table, bool $required): string
    {
        $namespace = $grouped
            ? "App\\Http\\Requests\\{$className}"
            : "App\\Http\\Requests";

        $rules = $this->buildRules($table, $required);

        $content = $this->render($this->getStub("{$action}-request"), [
            'Namespace' => $namespace,
            'ClassName' => $className,
            'Action'    => $action,
            'Rules'     => $rules,
        ]);

        $dir  = $grouped ? app_path("Http/Requests/{$className}") : app_path("Http/Requests");
        $path = "{$dir}/{$action}{$className}Request.php";
        $this->write($path, $content, true);
        return $path;
    }

    private function buildRules(string $table, bool $required): string
    {
        if (! $table) {
            return "            // 'field' => 'required|string|max:255',";
        }

        try {
            $columns = DB::select("SHOW COLUMNS FROM `{$table}`");
        } catch (\Throwable) {
            return "            // 'field' => 'required|string|max:255',";
        }

        $skip = ['id', 'created_at', 'updated_at', 'deleted_at'];

        return collect($columns)
            ->reject(fn ($col) => in_array($col->Field, $skip))
            ->map(function ($col) use ($required) {
                $rules   = $this->inferRules($col, $required);
                $req     = $required ? 'required' : 'sometimes';
                $ruleStr = $req . '|' . implode('|', $rules);
                return "            '{$col->Field}' => '{$ruleStr}'";
            })
            ->join(",\n") . ',';
    }

    private function inferRules(object $col, bool $required): array
    {
        $type  = strtolower($col->Type);
        $rules = [];

        if (str_contains($type, 'tinyint(1)')) {
            $rules[] = 'boolean';
        } elseif (str_contains($type, 'int')) {
            $rules[] = 'integer';
        } elseif (str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) {
            $rules[] = 'numeric';
        } elseif (str_contains($type, 'date') || str_contains($type, 'time')) {
            $rules[] = 'date';
        } elseif (str_contains($type, 'json')) {
            $rules[] = 'array';
        } elseif (str_contains($type, 'text')) {
            $rules[] = 'string';
        } elseif (str_contains($type, 'varchar')) {
            preg_match('/varchar\((\d+)\)/', $type, $m);
            $max     = $m[1] ?? 255;
            $rules[] = 'string';
            $rules[] = "max:{$max}";
        } else {
            $rules[] = 'string';
            $rules[] = 'max:255';
        }

        if ($col->Null === 'YES') {
            array_unshift($rules, 'nullable');
        }

        return $rules;
    }
}
