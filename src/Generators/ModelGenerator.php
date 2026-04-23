<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Generators;

class ModelGenerator extends BaseGenerator
{
    public function generate(string $className, string $table, bool $timestamps, bool $softDeletes): string
    {
        $softDeletesUse   = $softDeletes ? "\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;" : '';
        $softDeletesTrait = $softDeletes ? "\n    use SoftDeletes;\n" : '';
        $deletedAtCast    = $softDeletes ? "\n        'deleted_at' => 'datetime'," : '';
        $timestampsLine   = $timestamps  ? '' : "\n    public \$timestamps = false;";

        $content = $this->render($this->getStub('model'), [
            'ClassName'        => $className,
            'tableName'        => $table,
            'SoftDeletesUse'   => $softDeletesUse,
            'SoftDeletesTrait' => $softDeletesTrait,
            'DeletedAtCast'    => $deletedAtCast,
            'TimestampsLine'   => $timestampsLine,
        ]);

        $path = app_path("Models/{$className}.php");
        $this->write($path, $content, true);
        return $path;
    }
}
