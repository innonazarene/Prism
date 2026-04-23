<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

use Illuminate\Support\Str;

class ControllerGenerator extends BaseGenerator
{
    public function generate(
        string $className,
        bool   $grouped,
        bool   $withService,
        bool   $withResource
    ): string {
        $namespace = $grouped
            ? "App\\Http\\Controllers\\Api\\V1\\{$className}"
            : "App\\Http\\Controllers\\Api\\V1";
        $requestNs = $grouped
            ? "App\\Http\\Requests\\{$className}"
            : "App\\Http\\Requests";
        $resourceNs = $grouped
            ? "App\\Http\\Resources\\{$className}"
            : "App\\Http\\Resources";

        $serviceImport  = $withService  ? "\nuse App\\Services\\{$className}Service;"         : '';
        $resourceImport = $withResource ? "\nuse {$resourceNs}\\{$className}Resource;" : '';
        $constructor    = $withService
            ? "\n    public function __construct(\n        private readonly {$className}Service \$service\n    ) {}\n"
            : '';

        $collection = $withResource ? "{$className}Resource::collection(\$data)" : "\$data";
        $single     = $withResource ? "new {$className}Resource(\$data)"          : "\$data";

        if ($withService) {
            $indexReturn   = "\$data = \$this->service->list(\$request->all());\n        return \$this->successResponse({$collection}, '{$className} list retrieved.');";
            $storeReturn   = "\$data = \$this->service->create(\$request->validated());\n        return \$this->successResponse({$single}, '{$className} created.', 201);";
            $showReturn    = "\$data = \$this->service->findOrFail(\$id);\n        return \$this->successResponse({$single}, '{$className} retrieved.');";
            $updateReturn  = "\$data = \$this->service->update(\$id, \$request->validated());\n        return \$this->successResponse({$single}, '{$className} updated.');";
            $destroyBody   = "\$this->service->delete(\$id);\n        return \$this->successResponse(null, '{$className} deleted.');";
        } else {
            $indexReturn  = "return \$this->successResponse([], '{$className} list retrieved.');";
            $storeReturn  = "return \$this->successResponse(null, '{$className} created.', 201);";
            $showReturn   = "return \$this->successResponse(null, '{$className} retrieved.');";
            $updateReturn = "return \$this->successResponse(null, '{$className} updated.');";
            $destroyBody  = "return \$this->successResponse(null, '{$className} deleted.');";
        }

        $content = $this->render($this->getStub('controller'), [
            'Namespace'      => $namespace,
            'ClassName'      => $className,
            'RequestNs'      => $requestNs,
            'ServiceImport'  => $serviceImport,
            'ResourceImport' => $resourceImport,
            'Constructor'    => $constructor,
            'IndexReturn'    => $indexReturn,
            'StoreReturn'    => $storeReturn,
            'ShowReturn'     => $showReturn,
            'UpdateReturn'   => $updateReturn,
            'DestroyBody'    => $destroyBody,
        ]);

        $dir  = $grouped
            ? app_path("Http/Controllers/Api/V1/{$className}")
            : app_path("Http/Controllers/Api/V1");
        $path = "{$dir}/{$className}Controller.php";
        $this->write($path, $content, true);
        return $path;
    }
}
