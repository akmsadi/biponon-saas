<?php

namespace App\Console\Commands;

use App\Actions\Demo\BuildDemoCompany;
use App\Models\PlatformSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Creates the demo organization and its shared accounts, which the login page offers while the site is in demo
 * mode. With --fresh it deletes the demo organization first, undoing whatever visitors changed; the scheduler runs
 * that every night while demo mode is on.
 */
#[Signature('biponon:demo {--fresh : Delete the demo organization and build it again}')]
#[Description('Create (or with --fresh, rebuild) the demo organization and its demo accounts')]
class DemoCommand extends Command
{
    public function handle(BuildDemoCompany $buildDemoCompany): int
    {
        if (BuildDemoCompany::organization() !== null) {
            if (! $this->option('fresh')) {
                $this->components->info('The demo organization already exists. Use --fresh to rebuild it.');

                return self::SUCCESS;
            }

            $buildDemoCompany->delete();
        }

        $organization = $buildDemoCompany->handle();

        $this->components->info(sprintf(
            '%s built: %d products, %d units in stock, %d movements, %d purchase orders, %d sales orders.',
            $organization->name,
            $organization->products()->count(),
            $organization->stockBalances()->sum('quantity'),
            $organization->stockMovements()->count(),
            $organization->purchaseOrders()->count(),
            $organization->salesOrders()->count(),
        ));

        $this->components->twoColumnDetail('<fg=gray>Password for every demo account</>', BuildDemoCompany::PASSWORD);

        foreach (BuildDemoCompany::ACCOUNTS as $account) {
            $this->components->twoColumnDetail($account['label'], $account['email']);
        }

        if (! PlatformSetting::demoMode()) {
            $this->components->warn('The site is in production mode, so demo accounts cannot log in yet. A super-admin can switch to demo mode under Settings → Appearance.');
        }

        return self::SUCCESS;
    }
}
