<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

class ResourceGenerator extends BaseGenerator
{
    public function generate(string $className, bool $grouped): string
    {
        $namespace = $grouped
            ? "App\\Http\\Resources\\{$className}"
            : "App\\Http\\Resources";

        $content = $this->render($this->getStub('resource'), [
            'Namespace' => $namespace,
            'ClassName' => $className,
        ]);

        $dir  = $grouped ? app_path("Http/Resources/{$className}") : app_path("Http/Resources");
        $path = "{$dir}/{$className}Resource.php";
        $this->write($path, $content, true);
        return $path;
    }
}
