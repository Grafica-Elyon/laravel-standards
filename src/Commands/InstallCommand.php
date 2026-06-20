<?php

namespace Elyon\LaravelStandards\Commands;

use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'standards:install {--force : Overwrite standard files and Composer scripts without confirmation}';

    protected $description = 'Install Elyon Laravel standards into the host project.';

    public function handle(): int
    {
        $basePath = $this->laravel->basePath();

        $this->writeProjectFile(__DIR__.'/../../stubs/pint.json', $basePath.'/pint.json', 'pint.json');
        $this->writeProjectFile(__DIR__.'/../../stubs/phpstan.neon', $basePath.'/phpstan.neon', 'phpstan.neon');
        $this->writeProjectFile(__DIR__.'/../../stubs/hooks/pre-commit', $basePath.'/.githooks/pre-commit', '.githooks/pre-commit');
        $this->makeHookExecutable($basePath.'/.githooks/pre-commit');
        $this->configureGitHooksPath($basePath);

        $this->updateComposerScripts($basePath.'/composer.json');

        $this->info('Laravel standards installation completed.');
        $this->newLine();
        $this->line('Next step: run `php artisan boost:install` to activate the TDD');
        $this->line('skill (and any other Boost guidelines) in your AI agents.');

        return self::SUCCESS;
    }

    private function writeProjectFile(string $source, string $destination, string $label): void
    {
        $content = file_get_contents($source);

        if ($content === false) {
            throw new RuntimeException("Unable to read {$label} stub.");
        }

        $this->writeContent($destination, $content, $label);
    }

    private function writeContent(string $destination, string $content, string $label): void
    {
        if (is_file($destination)) {
            $existingContent = file_get_contents($destination);

            if ($existingContent === $content) {
                $this->line("<comment>Unchanged:</comment> {$label}");

                return;
            }

            if (! $this->option('force') && ! $this->confirm("{$label} already exists. Overwrite it?", false)) {
                $this->line("<comment>Skipped:</comment> {$label}");

                return;
            }
        }

        $directory = dirname($destination);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create {$directory}.");
        }

        if (file_put_contents($destination, $content) === false) {
            throw new RuntimeException("Unable to write {$label}.");
        }

        $this->line("<info>Written:</info> {$label}");
    }

    private function makeHookExecutable(string $hookPath): void
    {
        if (is_file($hookPath)) {
            chmod($hookPath, 0755);
        }
    }

    private function configureGitHooksPath(string $basePath): void
    {
        if (! is_dir($basePath.'/.git')) {
            $this->warn('Git is not initialized. Run git init && git config core.hooksPath .githooks to enable the pre-commit hook.');

            return;
        }

        $getHooksPath = new Process(['git', 'config', '--get', 'core.hooksPath'], $basePath);
        $getHooksPath->run();

        if ($getHooksPath->isSuccessful()) {
            $hooksPath = trim($getHooksPath->getOutput());

            if ($hooksPath === '.githooks') {
                return;
            }

            $this->warn("Git core.hooksPath is already set to \"{$hooksPath}\"; leaving it unchanged.");

            return;
        }

        if ($getHooksPath->getExitCode() !== 1) {
            $this->warn('Unable to inspect Git core.hooksPath; leaving it unchanged.');

            return;
        }

        $setHooksPath = new Process(['git', 'config', 'core.hooksPath', '.githooks'], $basePath);
        $setHooksPath->run();

        if (! $setHooksPath->isSuccessful()) {
            $this->warn('Unable to configure Git core.hooksPath. Run git config core.hooksPath .githooks manually.');

            return;
        }

        $this->line('Configured Git core.hooksPath to .githooks.');
    }

    private function updateComposerScripts(string $composerPath): void
    {
        if (! is_file($composerPath)) {
            $this->warn('Composer scripts skipped: composer.json was not found.');

            return;
        }

        try {
            $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->warn('Composer scripts skipped: composer.json is not valid JSON.');

            return;
        }

        if (! is_array($composer)) {
            $this->warn('Composer scripts skipped: composer.json does not contain an object.');

            return;
        }

        $scripts = is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];
        $standardScripts = [
            'lint' => 'pint',
            'lint:check' => 'pint --test',
            'analyse' => 'phpstan analyse --memory-limit=1G',
            'quality' => [
                '@lint:check',
                '@analyse',
            ],
        ];
        $changed = false;
        $mismatchedScripts = [];

        foreach ($standardScripts as $name => $command) {
            if (($scripts[$name] ?? null) === $command) {
                continue;
            }

            if (array_key_exists($name, $scripts) && ! $this->option('force')) {
                $mismatchedScripts[] = $name;

                continue;
            }

            $scripts[$name] = $command;
            $changed = true;
        }

        if ($changed) {
            $composer['scripts'] = $scripts;
            $encoded = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            if (file_put_contents($composerPath, $encoded.PHP_EOL) === false) {
                throw new RuntimeException('Unable to update composer.json scripts.');
            }
        }

        if ($mismatchedScripts === []) {
            return;
        }

        $this->line('Note: the following Composer scripts already exist with different content');
        $this->line('and were kept as-is. Re-run with --force to overwrite.');

        foreach ($mismatchedScripts as $name) {
            $this->line("  - {$name}");
        }
    }
}
