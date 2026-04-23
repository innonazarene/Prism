<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

class RequestGenerator extends BaseGenerator
{
    public function generateStore(string $className, bool $grouped): string
    {
        return $this->generate('Store', $className, $grouped);
    }

    public function generateUpdate(string $className, bool $grouped): string
    {
        return $this->generate('Update', $className, $grouped);
    }

    private function generate(string $action, string $className, bool $grouped): string
    {
        $namespace = $grouped
            ? "App\\Http\\Requests\\{$className}"
            : "App\\Http\\Requests";

        $content = $this->render($this->getStub("{$action}-request"), [
            'Namespace' => $namespace,
            'ClassName' => $className,
            'Action'    => $action,
        ]);

        $dir  = $grouped ? app_path("Http/Requests/{$className}") : app_path("Http/Requests");
        $path = "{$dir}/{$action}{$className}Request.php";
        $this->write($path, $content, true);
        return $path;
    }
}
