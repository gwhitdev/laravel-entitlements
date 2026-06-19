<?php

namespace Entitlements\Console;

use Entitlements\Contracts\FeatureCatalog;
use Entitlements\Support\PrerequisiteChecker;
use Illuminate\Console\Command;

/**
 * Audits all known plans for features whose declared prerequisites are not also mapped to that plan.
 *
 * Returns a non-zero exit code when any unmet prerequisites are found, making it suitable for CI.
 */
class LintCommand extends Command
{
    protected $signature = 'entitlements:lint';

    protected $description = 'Check that all plan → feature mappings satisfy declared prerequisites.';

    public function handle(FeatureCatalog $catalog): int
    {
        $checker = new PrerequisiteChecker($catalog);
        $issues = $checker->all();

        if (empty($issues)) {
            $this->info('All plans satisfy their declared prerequisites.');

            return self::SUCCESS;
        }

        foreach ($issues as $planIdentifier => $features) {
            foreach ($features as $featureKey => $missing) {
                $list = implode(', ', $missing);
                $this->warn(
                    "Plan '{$planIdentifier}': feature '{$featureKey}' is missing prerequisite(s): {$list}"
                );
            }
        }

        return self::FAILURE;
    }
}
