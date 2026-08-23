<?php

namespace App\Console\Commands;

use App\Services\ChallengeReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-challenge-reminders')]
#[Description('Remind challenge participants who have not logged today\'s progress yet')]
class SendChallengeReminders extends Command
{
    public function __construct(private ChallengeReminderService $service)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->service->sendDueReminders();
    }
}
