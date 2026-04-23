<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

use Illuminate\Support\Facades\File;

abstract class BaseGenerator
{
    protected string $stubsPath;

    public function __construct()
    {
        $this->stubsPath = __DIR__ . '/../../stubs';
    }

    protected function getStub(string $name): string
    {
        $custom  = base_path("stubs/prism-init/{$name}.stub");
        $default = "{$this->stubsPath}/{$name}.stub";
        return File::get(File::exists($custom) ? $custom : $default);
    }

    protected function render(string $stub, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $stub = str_replace("{{ {$key} }}", $value, $stub);
        }
        return $stub;
    }

    protected function ensureDir(string $path): void
    {
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    protected function write(string $path, string $content, bool $force = false): bool
    {
        if (File::exists($path) && ! $force) {
            return false;
        }
        $this->ensureDir(dirname($path));
        File::put($path, $content);
        return true;
    }
}
