<?php

namespace Elyon\LaravelStandards\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class QueryFilter
{
    /**
     * @param  class-string<Model>  $model
     * @param  array{
     *     filters?: array<int|string, AllowedFilter|string>,
     *     sorts?: array<int|string, AllowedSort|string>,
     *     fields?: array<int|string, string>,
     *     includes?: array<int|string, AllowedInclude|string>,
     *     defaultSort?: AllowedSort|string|array<int|string, AllowedSort|string>,
     *     defaultPerPage?: int,
     *     maxPerPage?: int
     * }  $config
     * @return LengthAwarePaginator<int, Model>
     */
    public static function for(string $model, Request $request, array $config): LengthAwarePaginator
    {
        $query = QueryBuilder::for($model, $request);

        if (array_key_exists('filters', $config)) {
            $query->allowedFilters(...array_values($config['filters']));
        }

        if (array_key_exists('sorts', $config)) {
            $query->allowedSorts(...array_values($config['sorts']));
        }

        if (array_key_exists('fields', $config)) {
            $query->allowedFields(...array_values($config['fields']));
        }

        if (array_key_exists('includes', $config)) {
            $query->allowedIncludes(...array_values($config['includes']));
        }

        if (array_key_exists('defaultSort', $config)) {
            $defaultSort = $config['defaultSort'];
            $defaultSorts = is_array($defaultSort) ? array_values($defaultSort) : [$defaultSort];

            $query->defaultSort(...$defaultSorts);
        }

        $defaultPerPage = max(1, (int) ($config['defaultPerPage'] ?? config('elyon-standards.query_filter.default_per_page', 25)));
        $maxPerPage = max(1, (int) ($config['maxPerPage'] ?? config('elyon-standards.query_filter.max_per_page', 100)));
        $perPage = min($maxPerPage, max(1, $request->integer('per_page', $defaultPerPage)));

        /** @var LengthAwarePaginator<int, Model> $paginator */
        $paginator = $query->paginate($perPage);

        return $paginator->appends($request->query());
    }
}
