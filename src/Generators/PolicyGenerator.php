<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

class PolicyGenerator extends BaseGenerator
{
    public function generate(string $className): string
    {
        $content = $this->render($this->getStub('policy'), [
            'Namespace' => "App\\Policies\\{$className}",
            'ClassName' => $className,
        ]);

        $dir  = app_path("Policies/{$className}");
        $path = "{$dir}/{$className}Policy.php";
        $this->write($path, $content, true);
        return $path;
    }
}
