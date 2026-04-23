<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RouteGenerator extends BaseGenerator
{
    public function generate(
        \Illuminate\Support\Collection $tables,
        string $prefix,
        bool   $grouped
    ): void {
        $apiPath = base_path('routes/api.php');
        if (! File::exists($apiPath)) {
            return;
        }

        $content    = File::get($apiPath);
        $routeLines = '';

        foreach ($tables as $table) {
            $className = ucfirst(Str::camel(Str::singular($table)));
            $routeName = strtolower(Str::plural($className));
            $ctrlFqn   = $grouped
                ? "App\\Http\\Controllers\\Api\\V1\\{$className}\\{$className}Controller"
                : "App\\Http\\Controllers\\Api\\V1\\{$className}Controller";
            $use       = "use {$ctrlFqn};";

            if (strpos($content, $use) === false) {
                $content = str_replace('<?php', "<?php\n{$use}", $content);
            }

            $routeLines .= "\n    Route::apiResource('{$routeName}', {$className}Controller::class);";
        }

        $authBlock  = "\n// Protected routes (auth:sanctum) — configure as needed\n"
                    . "// Route::prefix('protected')->middleware('auth:sanctum')->group(function () {});\n";
        $routeBlock = "\nRoute::prefix('{$prefix}')->group(function () {{$routeLines}\n});\n";

        $content .= $authBlock . $routeBlock;
        File::put($apiPath, $content);
    }
}
