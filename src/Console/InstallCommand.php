<?php

namespace Entitlements\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'entitlements:install';

    protected $description = 'Publish entitlements config and migrations, then show next steps';

    public function handle(): int
    {
        $this->callSilent('vendor:publish', ['--tag' => 'entitlements-config']);
        $this->callSilent('vendor:publish', ['--tag' => 'entitlements-migrations']);

        $this->info('Entitlements scaffolding published.');

        $this->line('');
        $this->line('Next steps:');
        $this->line('  • Add <info>use Entitlements\Concerns\HasFeatures;</info> to your User model');
        $this->line('  • Run <info>php artisan migrate</info>');
        $this->line('  • Run <info>php artisan entitlements:make YourFeature</info>');

        return self::SUCCESS;
    }
}
