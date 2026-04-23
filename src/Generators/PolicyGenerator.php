<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

class PolicyGenerator extends BaseGenerator
{
    public function generate(string $className): string
    {
        $content = $this->render($this->getStub('policy'), [
            'ClassName' => $className,
        ]);
        $path = app_path("Policies/{$className}Policy.php");
        $this->write($path, $content, true);
        return $path;
    }
}
