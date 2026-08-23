<?php

namespace App\Console\Commands;

use App\Services\DailyPlanService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\DailyPlan;

#[Signature('app:generate-daily-plans')]
#[Description('Command description')]
class GenerateDailyPlans extends Command
{
    /**
     * Execute the console command.
     */
    public function __construct(private DailyPlanService $service)
    {
        parent::__construct();
    }
    public function handle()
    {
        $this->service->generateForAllActiveusers(now());
        $this->info('Daily plans generated.');
    }
}
