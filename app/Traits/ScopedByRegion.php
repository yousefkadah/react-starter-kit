<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait ScopedByRegion
{
    protected static array $regionScopeCache = [];

    protected static function bootScopedByRegion(): void
    {
        static::addGlobalScope('region', function (Builder $builder) {
            $user = auth()->user();

            if (! $user || $user->is_admin) {
                return;
            }

            $model = $builder->getModel();
            $table = $model->getTable();

            $hasRegionColumn = self::$regionScopeCache[$table] ??= Schema::hasColumn($table, 'region');
            if ($hasRegionColumn) {
                $builder->where("{$table}.region", $user->region);

                return;
            }

            if (method_exists($model, 'user')) {
                $builder->whereHas('user', function (Builder $query) use ($user) {
                    $query->where('region', $user->region);
                });
            }
        });
    }
}
