<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Innonazarene\PrismInit\Generators\ControllerGenerator;
use Innonazarene\PrismInit\Generators\ModelGenerator;
use Innonazarene\PrismInit\Generators\PolicyGenerator;
use Innonazarene\PrismInit\Generators\RequestGenerator;
use Innonazarene\PrismInit\Generators\ResourceGenerator;
use Innonazarene\PrismInit\Generators\RouteGenerator;
use Innonazarene\PrismInit\Generators\ServiceGenerator;
use Innonazarene\PrismInit\Generators\TraitGenerator;

class PrismInitCommand extends Command
{
    protected $signature = 'prism:init
        {--prefix=         : API route prefix, e.g. v1 (overrides config)}
        {--grouped         : Group files per entity (Controllers/Api/V1/Employee/…). Default: config value}
        {--flat            : Put all files flat, ignoring the grouped config}
        {--no-timestamps   : Set $timestamps = false on all generated models}
        {--no-soft-deletes : Skip SoftDeletes on generated models}
        {--skip-migrate    : Skip migration generation}
        {--skip-seed       : Skip seeding}
        {--skip-services   : Skip Service class generation}
        {--skip-resources  : Skip API Resource generation}
        {--skip-policies   : Skip Policy generation}
        {--tables=         : Comma-separated list of tables to scaffold (skips all others)}
        {--force           : Overwrite existing files}';

    protected $description = 'Scaffold models, services, controllers, requests, resources, policies and API routes from your database.';

    public function handle(): int
    {
        $this->info('');
        $this->info('  ██████╗ ██████╗ ██╗███████╗███╗   ███╗');
        $this->info('  ██╔══██╗██╔══██╗██║██╔════╝████╗ ████║');
        $this->info('  ██████╔╝██████╔╝██║███████╗██╔████╔██║');
        $this->info('  ██╔═══╝ ██╔══██╗██║╚════██║██║╚██╔╝██║');
        $this->info('  ██║     ██║  ██║██║███████║██║ ╚═╝ ██║');
        $this->info('  ╚═╝     ╚═╝  ╚═╝╚═╝╚══════╝╚═╝     ╚═╝  init');
        $this->info('');

        $tables = $this->getDatabaseTables();

        if ($tables->isEmpty()) {
            $this->error('No tables found. Check your .env database settings.');
            return self::FAILURE;
        }

        $this->info("Found {$tables->count()} table(s) to scaffold.");
        $this->newLine();

        $this->cleanFiles();

        if (!$this->option('skip-migrate')) {
            $this->migrateDatabase();
        }

        // Generate ApiResponse trait once
        (new TraitGenerator)->generateApiResponse();
        $this->line('  ✓ app/Traits/ApiResponse.php');

        $grouped = $this->resolveGrouped();
        $timestamps = !$this->option('no-timestamps') && (bool) config('prism-init.timestamps', true);
        $softDel = !$this->option('no-soft-deletes') && (bool) config('prism-init.soft_deletes', true);
        $services = !$this->option('skip-services') && (bool) config('prism-init.generate_services', true);
        $resources = !$this->option('skip-resources') && (bool) config('prism-init.generate_resources', true);
        $policies = !$this->option('skip-policies') && (bool) config('prism-init.generate_policies', true);

        $modelGen = new ModelGenerator;
        $svcGen = new ServiceGenerator;
        $ctrlGen = new ControllerGenerator;
        $reqGen = new RequestGenerator;
        $resGen = new ResourceGenerator;
        $polGen = new PolicyGenerator;

        foreach ($tables as $table) {
            $className = ucfirst(Str::camel(Str::singular($table)));
            $this->line("  → <comment>{$className}</comment> (from `{$table}`)");

            $modelGen->generate($className, $table, $timestamps, $softDel);
            $this->line("     ✓ Models/{$className}/{$className}.php");

            if ($services) {
                $svcGen->generate($className);
                $this->line("     ✓ Services/{$className}/{$className}Service.php");
            }

            $ctrlGen->generate($className, $grouped, $services, $resources);
            $folder = $grouped ? "Api/V1/{$className}/" : "Api/V1/";
            $this->line("     ✓ Controllers/{$folder}{$className}Controller.php");

            $reqDir = $grouped ? "{$className}/" : '';
            $reqGen->generateStore($className, $grouped, $table);
            $reqGen->generateUpdate($className, $grouped, $table);
            $this->line("     ✓ Requests/{$reqDir}Store|Update{$className}Request.php");

            if ($resources) {
                $resGen->generate($className, $grouped, $table);
                $resDir = $grouped ? "{$className}/" : '';
                $this->line("     ✓ Resources/{$resDir}{$className}Resource.php");
            }

            if ($policies) {
                $polGen->generate($className);
                $this->line("     ✓ Policies/{$className}/{$className}Policy.php");
            }
        }

        $this->newLine();
        $this->info('Updating API routes...');
        (new RouteGenerator)->generate($tables, $this->resolvePrefix(), $grouped);
        $this->line('  ✓ routes/api.php updated');

        if (!$this->option('skip-seed')) {
            $this->startSeeding($tables);
        }

        Artisan::call('route:clear');

        $this->newLine();
        $this->info('✅  Prism Init complete!');
        $this->line("    Scaffolded {$tables->count()} entit" . ($tables->count() === 1 ? 'y' : 'ies') . '.');
        $this->info('');

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getDatabaseTables(): \Illuminate\Support\Collection
    {
        $only = $this->option('tables')
            ? collect(explode(',', $this->option('tables')))->map(fn($t) => trim($t))
            : null;

        $dbName = config('database.connections.' . config('database.default') . '.database');
        $raw = DB::select('SHOW TABLES');

        return collect($raw)
            ->pluck('Tables_in_' . $dbName)
            ->reject(fn($t) => in_array($t, config('prism-init.exclude_tables', ['migrations'])))
            ->when($only, fn($col) => $col->intersect($only))
            ->values();
    }

    private function migrateDatabase(): void
    {
        $this->info('Generating migrations...');

        if (array_key_exists('migrate:generate', Artisan::all())) {
            Artisan::call('migrate:generate', [], $this->output);
            $this->info('Done: Migrations');
        } else {
            $this->warn('  ⚠  Migration generation skipped.');
            $this->line('     Install <comment>kitloong/laravel-migrations-generator</comment> to enable it:');
            $this->line('     <comment>composer require kitloong/laravel-migrations-generator --dev</comment>');
        }
    }

    private function startSeeding(\Illuminate\Support\Collection $tables): void
    {
        $this->info('Seeding tables...');

        if (!array_key_exists('iseed', Artisan::all())) {
            $this->warn('  ⚠  Seeding skipped.');
            $this->line('     Install <comment>orangehill/iseed</comment> to enable it:');
            $this->line('     <comment>composer require orangehill/iseed --dev</comment>');
            return;
        }

        $include = config('prism-init.seed_tables', []);
        $toSeed = count($include) > 0 ? $tables->intersect($include) : $tables;

        foreach ($toSeed as $table) {
            $this->line("  Seeding: {$table}");
            Artisan::call('iseed', ['tables' => $table]);
        }
        $this->info('Done: Seeding');
    }

    private function cleanFiles(): void
    {
        $this->info('Cleaning previous scaffold...');

        $backupDir = base_path('public/backup');
        $apiSrc = base_path('routes/api.php');
        $ctrlSrc = app_path('Http/Controllers/Controller.php');
        $apiBackup = "{$backupDir}/api.backup.php";
        $ctrlBackup = "{$backupDir}/Controller.backup.php";
        $hasBackup = File::exists($apiBackup) && File::exists($ctrlBackup);

        $apiOrig = $hasBackup ? File::get($apiBackup) : (File::exists($apiSrc) ? File::get($apiSrc) : '<?php' . PHP_EOL);
        $ctrlOrig = $hasBackup ? File::get($ctrlBackup) : (File::exists($ctrlSrc) ? File::get($ctrlSrc) : null);

        File::deleteDirectory($backupDir);
        File::makeDirectory($backupDir, 0755, true);
        File::put($apiBackup, $apiOrig);
        if ($ctrlOrig !== null) {
            File::put($ctrlBackup, $ctrlOrig);
        }
        $this->line('  ✓ Backups saved → public/backup/');

        $this->cleanDir(app_path('Models'));
        $this->cleanDir(database_path('migrations'));
        $this->cleanControllers(app_path('Http/Controllers'));
        $this->cleanDir(app_path('Http/Requests'));
        $this->cleanDir(app_path('Http/Resources'));
        $this->cleanDir(app_path('Services'));
        $this->cleanDir(app_path('Policies'));

        File::put($apiSrc, $apiOrig);
        if ($ctrlOrig !== null) {
            File::put($ctrlSrc, $ctrlOrig);
        }

        $this->info('Done: Clean');
    }

    private function cleanDir(string $path): void
    {
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
            return;
        }
        foreach (File::allFiles($path) as $file) {
            File::delete($file->getPathname());
        }
        foreach (File::directories($path) as $dir) {
            File::deleteDirectory($dir);
        }
    }

    private function cleanControllers(string $path): void
    {
        if (!File::isDirectory($path)) {
            return;
        }
        foreach (File::files($path) as $file) {
            if ($file->getFilename() !== 'Controller.php') {
                File::delete($file->getPathname());
            }
        }
        foreach (File::directories($path) as $dir) {
            File::deleteDirectory($dir);
        }
    }

    private function resolvePrefix(): string
    {
        return $this->option('prefix') ?? config('prism-init.route_prefix', 'v1');
    }

    private function resolveGrouped(): bool
    {
        if ($this->option('flat')) {
            return false;
        }
        if ($this->option('grouped')) {
            return true;
        }
        return (bool) config('prism-init.grouped', true);
    }
}
