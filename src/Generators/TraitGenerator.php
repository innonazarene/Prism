<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

class TraitGenerator extends BaseGenerator
{
    public function generateApiResponse(): string
    {
        $content = $this->getStub('api-response');
        $path    = app_path('Traits/ApiResponse.php');
        $this->write($path, $content); // never overwrite a customised trait
        return $path;
    }
}
