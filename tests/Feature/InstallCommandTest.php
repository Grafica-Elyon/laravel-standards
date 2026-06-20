<?php

namespace Elyon\LaravelStandards\Tests\Feature;

use Elyon\LaravelStandards\Tests\TestCase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;

class InstallCommandTest extends TestCase
{
    private string $hostPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostPath = sys_get_temp_dir().'/laravel-standards-'.uniqid('', true);
        mkdir($this->hostPath, 0755, true);
        file_put_contents($this->hostPath.'/composer.json', "{}\n");
        $this->app->setBasePath($this->hostPath);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->hostPath);

        parent::tearDown();
    }

    public function test_it_publishes_pint_json_to_the_host_root(): void
    {
        // Arrange
        $expected = file_get_contents(__DIR__.'/../../stubs/pint.json');

        // Act
        $this->artisan('standards:install', ['--force' => true])->assertExitCode(0);

        // Assert
        $this->assertSame($expected, file_get_contents($this->hostPath.'/pint.json'));
    }

    public function test_it_publishes_phpstan_neon_to_the_host_root(): void
    {
        // Arrange
        $expected = file_get_contents(__DIR__.'/../../stubs/phpstan.neon');

        // Act
        $this->artisan('standards:install', ['--force' => true])->assertExitCode(0);

        // Assert
        $this->assertSame($expected, file_get_contents($this->hostPath.'/phpstan.neon'));
    }

    public function test_it_writes_the_pre_commit_script_and_configures_core_hookspath(): void
    {
        // Arrange
        $this->initializeGitRepository();
        $expectedHook = <<<'SCRIPT'
#!/bin/sh

echo "🔍 Running pre-commit checks..."
echo ""

composer quality
if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Quality checks failed. Run 'composer lint' to fix style issues, then re-commit."
    exit 1
fi

echo ""
echo "✅ All checks passed!"
SCRIPT;
        $expectedHook .= PHP_EOL;

        // Act
        $this->artisan('standards:install', ['--force' => true])->assertExitCode(0);

        // Assert
        $hook = $this->hostPath.'/.githooks/pre-commit';
        $this->assertSame($expectedHook, file_get_contents($hook));
        $this->assertSame($expectedHook, file_get_contents(__DIR__.'/../../stubs/hooks/pre-commit'));
        $this->assertTrue(is_executable($hook));
        $this->assertSame('.githooks', $this->gitConfig('core.hooksPath'));
    }

    public function test_it_warns_when_git_is_not_initialized(): void
    {
        // Arrange
        $warning = 'Git is not initialized. Run git init && git config core.hooksPath .githooks to enable the pre-commit hook.';

        // Act
        $this->artisan('standards:install', ['--force' => true])
            ->expectsOutput($warning)
            ->assertExitCode(0);

        // Assert
        $this->assertDirectoryDoesNotExist($this->hostPath.'/.git');
    }

    public function test_it_does_not_overwrite_existing_core_hookspath(): void
    {
        // Arrange
        $this->initializeGitRepository();
        (new Process(['git', 'config', 'core.hooksPath', 'custom-hooks'], $this->hostPath))->mustRun();

        // Act
        $this->artisan('standards:install', ['--force' => true])
            ->expectsOutput('Git core.hooksPath is already set to "custom-hooks"; leaving it unchanged.')
            ->assertExitCode(0);

        // Assert
        $this->assertSame('custom-hooks', $this->gitConfig('core.hooksPath'));
    }

    public function test_it_ships_the_tdd_skill_at_the_boost_extension_path(): void
    {
        // Arrange
        $path = __DIR__.'/../../resources/boost/skills/tdd-phpunit-laravel/SKILL.md';

        // Act
        $content = file_get_contents($path);

        // Assert
        $this->assertFileExists($path);
        $this->assertIsString($content);
        $this->assertMatchesRegularExpression('/\A---\n(?<frontmatter>.*?)\n---/s', $content);
        $this->assertStringContainsString('name:', $content);
        $this->assertStringContainsString('description:', $content);
    }

    public function test_it_prints_the_boost_install_hint_after_success(): void
    {
        // Arrange
        $this->artisan('standards:install', ['--force' => true])
            ->expectsOutput('Laravel standards installation completed.')
            ->expectsOutput('Next step: run `php artisan boost:install` to activate the TDD')
            ->expectsOutput('skill (and any other Boost guidelines) in your AI agents.')
            ->assertExitCode(0);

        // Assert
        $this->assertFileDoesNotExist($this->hostPath.'/AGENTS.md');
    }

    public function test_it_adds_quality_scripts_when_missing(): void
    {
        // Arrange
        $this->artisan('standards:install')
            ->assertExitCode(0);

        // Assert
        $composer = json_decode((string) file_get_contents($this->hostPath.'/composer.json'), true);
        $this->assertSame([
            'lint' => 'pint',
            'lint:check' => 'pint --test',
            'analyse' => 'phpstan analyse --memory-limit=1G',
            'quality' => [
                '@lint:check',
                '@analyse',
            ],
        ], $composer['scripts']);
    }

    public function test_it_silently_skips_existing_composer_scripts_with_same_content(): void
    {
        // Arrange
        $scripts = self::qualityScripts();
        file_put_contents($this->hostPath.'/composer.json', json_encode(['scripts' => $scripts], JSON_PRETTY_PRINT).PHP_EOL);

        // Act
        $this->artisan('standards:install')->assertExitCode(0);

        // Assert
        $composer = json_decode((string) file_get_contents($this->hostPath.'/composer.json'), true);
        $this->assertSame($scripts, $composer['scripts']);
    }

    public function test_it_keeps_existing_composer_scripts_with_different_content_and_reports(): void
    {
        // Arrange
        file_put_contents($this->hostPath.'/composer.json', json_encode([
            'scripts' => ['lint' => 'custom-linter'],
        ], JSON_PRETTY_PRINT).PHP_EOL);

        // Act
        $this->artisan('standards:install')
            ->expectsOutput('Note: the following Composer scripts already exist with different content')
            ->expectsOutput('and were kept as-is. Re-run with --force to overwrite.')
            ->expectsOutput('  - lint')
            ->assertExitCode(0);

        // Assert
        $composer = json_decode((string) file_get_contents($this->hostPath.'/composer.json'), true);
        $this->assertSame('custom-linter', $composer['scripts']['lint']);
        $this->assertArrayHasKey('quality', $composer['scripts']);
    }

    public function test_it_never_adds_a_test_composer_script(): void
    {
        // Arrange
        $this->artisan('standards:install')->assertExitCode(0);

        // Act
        $composer = json_decode((string) file_get_contents($this->hostPath.'/composer.json'), true);

        // Assert
        $this->assertArrayNotHasKey('test', $composer['scripts']);
    }

    public function test_force_overwrites_mismatched_composer_scripts(): void
    {
        // Arrange
        file_put_contents($this->hostPath.'/composer.json', json_encode([
            'scripts' => ['quality' => 'custom-quality'],
        ], JSON_PRETTY_PRINT).PHP_EOL);

        // Act
        $this->artisan('standards:install', ['--force' => true])->assertExitCode(0);

        // Assert
        $composer = json_decode((string) file_get_contents($this->hostPath.'/composer.json'), true);
        $this->assertSame(self::qualityScripts()['quality'], $composer['scripts']['quality']);
    }

    public function test_it_does_not_overwrite_existing_files_when_confirmation_is_denied(): void
    {
        // Arrange
        file_put_contents($this->hostPath.'/pint.json', "{\"custom\": true}\n");
        unlink($this->hostPath.'/composer.json');

        // Act
        $this->artisan('standards:install')
            ->expectsConfirmation('pint.json already exists. Overwrite it?', 'no')
            ->assertExitCode(0);

        // Assert
        $this->assertSame("{\"custom\": true}\n", file_get_contents($this->hostPath.'/pint.json'));
    }

    public function test_force_overwrites_without_prompts(): void
    {
        // Arrange
        file_put_contents($this->hostPath.'/pint.json', "{\"custom\": true}\n");

        // Act
        $this->artisan('standards:install', ['--force' => true])->assertExitCode(0);

        // Assert
        $this->assertSame(file_get_contents(__DIR__.'/../../stubs/pint.json'), file_get_contents($this->hostPath.'/pint.json'));
    }

    public function test_it_is_idempotent_when_run_twice(): void
    {
        // Arrange
        $this->initializeGitRepository();
        $this->artisan('standards:install', ['--force' => true])->assertExitCode(0);

        // Act
        $this->artisan('standards:install')->assertExitCode(0);

        // Assert
        $this->assertSame('.githooks', $this->gitConfig('core.hooksPath'));
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

    private static function qualityScripts(): array
    {
        return [
            'lint' => 'pint',
            'lint:check' => 'pint --test',
            'analyse' => 'phpstan analyse --memory-limit=1G',
            'quality' => [
                '@lint:check',
                '@analyse',
            ],
        ];
    }

    private function initializeGitRepository(): void
    {
        (new Process(['git', 'init', '--quiet'], $this->hostPath))->mustRun();
    }

    private function gitConfig(string $key): string
    {
        $process = new Process(['git', 'config', '--get', $key], $this->hostPath);
        $process->mustRun();

        return trim($process->getOutput());
    }
}
