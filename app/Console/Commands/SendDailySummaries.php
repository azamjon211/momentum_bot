<?php

namespace App\Console\Commands;

use App\Services\DailySummaryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-daily-summaries')]
#[Description('Send the evening completion summary for each user\'s daily plan')]
class SendDailySummaries extends Command
{
    public function __construct(private DailySummaryService $service)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->service->sendDueSummaries();
    }
}
