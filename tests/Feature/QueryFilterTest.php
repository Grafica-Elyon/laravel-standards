<?php

namespace Elyon\LaravelStandards\Tests\Feature;

use Elyon\LaravelStandards\Support\QueryFilter;
use Elyon\LaravelStandards\Tests\Fixtures\QueryFilterPost;
use Elyon\LaravelStandards\Tests\TestCase;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;

class QueryFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app->make('config');

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Fixtures/migrations');
    }

    public function test_it_uses_package_default_pagination_settings(): void
    {
        $this->createPosts(130);

        $defaultPaginator = QueryFilter::for(QueryFilterPost::class, $this->request(), []);
        $maximumPaginator = QueryFilter::for(QueryFilterPost::class, $this->request(['per_page' => 999]), []);

        $this->assertSame(25, $defaultPaginator->perPage());
        $this->assertCount(25, $defaultPaginator->items());
        $this->assertSame(130, $defaultPaginator->total());
        $this->assertSame(100, $maximumPaginator->perPage());
        $this->assertCount(100, $maximumPaginator->items());
    }

    public function test_it_uses_project_config_pagination_settings(): void
    {
        $this->configRepository()->set('elyon-standards.query_filter.default_per_page', 6);
        $this->configRepository()->set('elyon-standards.query_filter.max_per_page', 8);
        $this->createPosts(20);

        $defaultPaginator = QueryFilter::for(QueryFilterPost::class, $this->request(), []);
        $maximumPaginator = QueryFilter::for(QueryFilterPost::class, $this->request(['per_page' => 50]), []);

        $this->assertSame(6, $defaultPaginator->perPage());
        $this->assertCount(6, $defaultPaginator->items());
        $this->assertSame(8, $maximumPaginator->perPage());
        $this->assertCount(8, $maximumPaginator->items());
    }

    public function test_controller_pagination_settings_override_project_config(): void
    {
        $this->configRepository()->set('elyon-standards.query_filter.default_per_page', 6);
        $this->configRepository()->set('elyon-standards.query_filter.max_per_page', 8);
        $this->createPosts(20);

        $defaultPaginator = QueryFilter::for(QueryFilterPost::class, $this->request(), [
            'defaultPerPage' => 9,
            'maxPerPage' => 20,
        ]);
        $maximumPaginator = QueryFilter::for(QueryFilterPost::class, $this->request(['per_page' => 50]), [
            'maxPerPage' => 11,
        ]);

        $this->assertSame(9, $defaultPaginator->perPage());
        $this->assertCount(9, $defaultPaginator->items());
        $this->assertSame(11, $maximumPaginator->perPage());
        $this->assertCount(11, $maximumPaginator->items());
    }

    public function test_it_uses_custom_per_page_parameter(): void
    {
        $this->createPosts(10);

        $paginator = QueryFilter::for(QueryFilterPost::class, $this->request(['per_page' => 7]), []);

        $this->assertSame(7, $paginator->perPage());
        $this->assertCount(7, $paginator->items());
    }

    public function test_it_clamps_per_page_parameter(): void
    {
        $this->createPosts(10);

        $belowMinimum = QueryFilter::for(QueryFilterPost::class, $this->request(['per_page' => 0]), []);
        $aboveMaximum = QueryFilter::for(QueryFilterPost::class, $this->request(['per_page' => 999]), [
            'maxPerPage' => 3,
        ]);

        $this->assertSame(1, $belowMinimum->perPage());
        $this->assertCount(1, $belowMinimum->items());
        $this->assertSame(3, $aboveMaximum->perPage());
        $this->assertCount(3, $aboveMaximum->items());
    }

    public function test_it_applies_allowed_filters(): void
    {
        $this->createPost('Alpha', 'published', 2);
        $this->createPost('Beta', 'draft', 1);
        $this->createPost('Gamma', 'published', 3);

        $paginator = QueryFilter::for(
            QueryFilterPost::class,
            $this->request(['filter' => ['status' => 'published']]),
            ['filters' => [AllowedFilter::exact('status')]],
        );

        $this->assertSame(2, $paginator->total());
        $this->assertSame(['Alpha', 'Gamma'], $paginator->getCollection()->pluck('name')->all());
    }

    public function test_it_auto_detects_and_applies_flat_filters(): void
    {
        $this->createPost('Alpha', 'published', 2);
        $this->createPost('Beta', 'draft', 1);
        $this->createPost('Gamma', 'published', 3);

        $paginator = QueryFilter::for(
            QueryFilterPost::class,
            $this->request(['status' => 'published']),
            ['filters' => ['status']],
        );

        $this->assertSame(2, $paginator->total());
        $this->assertSame(['Alpha', 'Gamma'], $paginator->getCollection()->pluck('name')->all());
    }

    public function test_it_applies_nested_filters_with_string_config(): void
    {
        $this->createPost('Alpha', 'published', 2);
        $this->createPost('Beta', 'draft', 1);
        $this->createPost('Gamma', 'published', 3);

        $paginator = QueryFilter::for(
            QueryFilterPost::class,
            $this->request(['filter' => ['status' => 'published']]),
            ['filters' => ['status']],
        );

        $this->assertSame(2, $paginator->total());
        $this->assertSame(['Alpha', 'Gamma'], $paginator->getCollection()->pluck('name')->all());
    }

    public function test_it_applies_flat_and_nested_filters_together(): void
    {
        $this->createPost('Alpha', 'published', 2);
        $this->createPost('Beta', 'draft', 2);
        $this->createPost('Gamma', 'published', 3);

        $paginator = QueryFilter::for(
            QueryFilterPost::class,
            $this->request([
                'status' => 'published',
                'filter' => ['priority' => '2'],
            ]),
            ['filters' => ['status', 'priority']],
        );

        $this->assertSame(1, $paginator->total());
        $this->assertSame(['Alpha'], $paginator->getCollection()->pluck('name')->all());
    }

    public function test_plain_string_filters_are_treated_as_exact_filters(): void
    {
        $this->createPost('Alpha', 'active', 1);
        $this->createPost('Beta', 'inactive', 2);
        $this->createPost('Gamma', 'active-archived', 3);

        $paginator = QueryFilter::for(
            QueryFilterPost::class,
            $this->request(['filter' => ['status' => 'active']]),
            ['filters' => ['status']],
        );

        $this->assertSame(1, $paginator->total());
        $this->assertSame(['Alpha'], $paginator->getCollection()->pluck('name')->all());
    }

    public function test_allowed_filter_instances_are_preserved_as_configured(): void
    {
        $this->createPost('Alpha', 'published', 1);
        $this->createPost('Alpine', 'published', 2);
        $this->createPost('Beta', 'published', 3);

        $paginator = QueryFilter::for(
            QueryFilterPost::class,
            $this->request(['filter' => ['name' => 'Al']]),
            ['filters' => [AllowedFilter::partial('name')]],
        );

        $this->assertSame(2, $paginator->total());
        $this->assertSame(['Alpha', 'Alpine'], $paginator->getCollection()->pluck('name')->all());
    }

    public function test_it_applies_allowed_sorts(): void
    {
        $this->createPost('Alpha', 'published', 2);
        $this->createPost('Beta', 'draft', 1);
        $this->createPost('Gamma', 'published', 3);

        $paginator = QueryFilter::for(
            QueryFilterPost::class,
            $this->request(['sort' => '-priority']),
            ['sorts' => ['priority']],
        );

        $this->assertSame([3, 2, 1], $paginator->getCollection()->pluck('priority')->all());
    }

    public function test_it_applies_default_sort_when_no_sort_parameter_is_provided(): void
    {
        $this->createPost('Alpha', 'published', 2);
        $this->createPost('Beta', 'draft', 1);
        $this->createPost('Gamma', 'published', 3);

        $paginator = QueryFilter::for(
            QueryFilterPost::class,
            $this->request(),
            ['defaultSort' => '-priority'],
        );

        $this->assertSame([3, 2, 1], $paginator->getCollection()->pluck('priority')->all());
    }

    public function test_it_appends_query_parameters_to_pagination_links(): void
    {
        $this->createPost('Alpha', 'published', 2);
        $this->createPost('Beta', 'published', 1);
        $this->createPost('Gamma', 'published', 3);

        $paginator = QueryFilter::for(
            QueryFilterPost::class,
            $this->request([
                'filter' => ['status' => 'published'],
                'per_page' => 1,
                'sort' => '-priority',
            ]),
            [
                'filters' => [AllowedFilter::exact('status')],
                'sorts' => ['priority'],
            ],
        );

        $nextPageUrl = $paginator->nextPageUrl();

        $this->assertIsString($nextPageUrl);
        $this->assertStringContainsString('filter%5Bstatus%5D=published', $nextPageUrl);
        $this->assertStringContainsString('per_page=1', $nextPageUrl);
        $this->assertStringContainsString('sort=-priority', $nextPageUrl);
    }

    public function test_it_paginates_with_empty_config(): void
    {
        $this->createPosts(3);

        $paginator = QueryFilter::for(QueryFilterPost::class, $this->request(), []);

        $this->assertSame(3, $paginator->total());
        $this->assertCount(3, $paginator->items());
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(array $query = []): Request
    {
        return Request::create('/query-filter-posts', 'GET', $query);
    }

    private function createPosts(int $count): void
    {
        foreach (range(1, $count) as $index) {
            $this->createPost("Post {$index}", 'published', $index);
        }
    }

    private function createPost(string $name, string $status, int $priority): void
    {
        QueryFilterPost::query()->create([
            'name' => $name,
            'status' => $status,
            'priority' => $priority,
        ]);
    }

    private function configRepository(): Repository
    {
        /** @var Repository $config */
        $config = $this->app->make('config');

        return $config;
    }
}
