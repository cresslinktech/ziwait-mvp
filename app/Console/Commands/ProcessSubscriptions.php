<?php

namespace App\Console\Commands;

use App\Domain\Billing\Actions\ProcessSubscriptionLifecycle;
use Illuminate\Console\Command;

class ProcessSubscriptions extends Command
{
    protected $signature = 'billing:process';

    protected $description = 'Move subscriptions through grace and expiry and send renewal reminders';

    public function handle(ProcessSubscriptionLifecycle $lifecycle): int
    {
        $c = $lifecycle();
        $this->info("past due: {$c['grace']}, expired: {$c['expired']}, reminders: {$c['reminded']}");

        return self::SUCCESS;
    }
}
