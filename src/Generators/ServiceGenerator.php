<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

class ServiceGenerator extends BaseGenerator
{
    public function generate(string $className): string
    {
        $content = $this->render($this->getStub('service'), [
            'ClassName' => $className,
        ]);
        $path = app_path("Services/{$className}Service.php");
        $this->write($path, $content, true);
        return $path;
    }
}
