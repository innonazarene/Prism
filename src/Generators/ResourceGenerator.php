<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

use Illuminate\Support\Facades\DB;

class ResourceGenerator extends BaseGenerator
{
    public function generate(string $className, bool $grouped, string $table = ''): string
    {
        $namespace = $grouped
            ? "App\\Http\\Resources\\{$className}"
            : "App\\Http\\Resources";

        $fields = $this->buildFields($table);

        $content = $this->render($this->getStub('resource'), [
            'Namespace' => $namespace,
            'ClassName' => $className,
            'Fields'    => $fields,
        ]);

        $dir  = $grouped ? app_path("Http/Resources/{$className}") : app_path("Http/Resources");
        $path = "{$dir}/{$className}Resource.php";
        $this->write($path, $content, true);
        return $path;
    }

    private function buildFields(string $table): string
    {
        if (! $table) {
            return "        'id' => \$this->id,\n        // TODO: add your fields here";
        }

        try {
            $columns = DB::select("SHOW COLUMNS FROM `{$table}`");
        } catch (\Throwable) {
            return "        'id' => \$this->id,\n        // TODO: add your fields here";
        }

        return collect($columns)
            ->map(fn ($col) => "        '{$col->Field}' => \$this->{$col->Field}")
            ->join(",\n") . ',';
    }
}
