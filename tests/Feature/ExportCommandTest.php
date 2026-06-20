<?php

namespace Elyon\LaravelStandards\Tests\Feature;

use Elyon\LaravelStandards\StandardsServiceProvider;
use Elyon\LaravelStandards\Tests\TestCase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ExportCommandTest extends TestCase
{
    private string $hostPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostPath = sys_get_temp_dir().'/laravel-standards-'.uniqid('', true);
        mkdir($this->hostPath, 0755, true);
        $this->app->setBasePath($this->hostPath);
        (new StandardsServiceProvider($this->app))->boot();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->hostPath);

        parent::tearDown();
    }

    public function test_it_republishes_the_standard_stubs(): void
    {
        // Arrange
        $pintPath = $this->hostPath.'/pint.json';

        // Act
        $this->artisan('standards:export')->assertExitCode(0);

        // Assert
        $this->assertSame(file_get_contents(__DIR__.'/../../stubs/pint.json'), file_get_contents($pintPath));
        $this->assertSame(file_get_contents(__DIR__.'/../../stubs/phpstan.neon'), file_get_contents($this->hostPath.'/phpstan.neon'));
        $this->assertSame(file_get_contents(__DIR__.'/../../stubs/hooks/pre-commit'), file_get_contents($this->hostPath.'/.githooks/pre-commit'));
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
