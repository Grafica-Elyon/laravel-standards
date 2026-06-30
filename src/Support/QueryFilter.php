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
        $filters = array_key_exists('filters', $config)
            ? self::normalizeFilters($config['filters'])
            : [];

        $queryRequest = self::requestWithFlatFilters($request, $filters);
        $query = QueryBuilder::for($model, $queryRequest);

        if ($filters !== []) {
            $query->allowedFilters(...$filters);
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

    /**
     * @param  array<int|string, AllowedFilter|string>  $filters
     * @return array<int, AllowedFilter>
     */
    private static function normalizeFilters(array $filters): array
    {
        return array_map(
            fn (AllowedFilter|string $filter): AllowedFilter => $filter instanceof AllowedFilter
                ? $filter
                : AllowedFilter::exact($filter),
            array_values($filters),
        );
    }

    /**
     * @param  array<int, AllowedFilter>  $filters
     */
    private static function requestWithFlatFilters(Request $request, array $filters): Request
    {
        if ($filters === []) {
            return $request;
        }

        $query = $request->query->all();
        $filterParameterName = (string) config('query-builder.parameters.filter', 'filter');
        $nestedFilters = self::nestedFilters($query, $filterParameterName);
        $flatFilters = [];

        foreach ($filters as $filter) {
            $filterName = $filter->getName();

            if (array_key_exists($filterName, $nestedFilters) || ! array_key_exists($filterName, $query)) {
                continue;
            }

            $flatFilters[$filterName] = $query[$filterName];
        }

        if ($flatFilters === []) {
            return $request;
        }

        $query[$filterParameterName] = array_replace($nestedFilters, $flatFilters);

        $normalizedRequest = Request::createFrom($request);
        $normalizedRequest->query->replace($query);

        return $normalizedRequest;
    }

    /**
     * @param  array<mixed>  $query
     * @return array<mixed>
     */
    private static function nestedFilters(array $query, string $filterParameterName): array
    {
        $filters = $query[$filterParameterName] ?? [];

        if (! is_array($filters)) {
            return [];
        }

        return $filters;
    }
}
