<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-task-reminders')]
#[Description('Send due reminders for incomplete daily tasks')]
class SendTaskReminders extends Command
{
    public function __construct(private ReminderService $service)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->service->sendDueReminders();
    }
}
