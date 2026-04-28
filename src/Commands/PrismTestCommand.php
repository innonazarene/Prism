<?php
namespace Innonazarene\PrismInit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class PrismTestCommand extends Command
{
    protected $signature = "prism:test {--prefix=v1} {--tables=}";
    protected $description = "Test generated API endpoints internally.";

    public function handle()
    {
        $tables = explode(",", $this->option("tables"));
        $prefix = $this->option("prefix");
        
        $kernel = app()->make(\Illuminate\Contracts\Http\Kernel::class);

        $this->info("Running API Endpoint Tests...");
        
        foreach ($tables as $table) {
            $className = ucfirst(Str::camel(Str::singular($table)));
            $routeName = strtolower(Str::plural($className));
            
            $uri = "/api/{$prefix}/{$routeName}";
            $request = Request::create($uri, "GET");
            $request->headers->set("Accept", "application/json");
            
            $response = $kernel->handle($request);
            $status = $response->getStatusCode();
            
            if ($status >= 200 && $status < 300) {
                $this->line("  <info>[OK]</info> GET {$uri} [{$status}]");
            } else {
                $this->line("  <error>[FAIL]</error> GET {$uri} [{$status}]");
            }
        }
        
        $this->info("");
    }
}
