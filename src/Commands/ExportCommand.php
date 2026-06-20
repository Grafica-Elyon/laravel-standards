<?php

namespace Elyon\LaravelStandards\Commands;

use Illuminate\Console\Command;

class ExportCommand extends Command
{
    protected $signature = 'standards:export';

    protected $description = 'Export the Laravel standards configuration stubs.';

    public function handle(): int
    {
        $this->info('Exporting Laravel standards stubs...');

        $this->call('vendor:publish', [
            '--tag' => 'standards-config',
        ]);

        return self::SUCCESS;
    }
}
