<?php
namespace App\Services;
use App\Models\DailyPlanTask;

class ReminderService
{
    public function __construct(private TelegramService $telegramService) {}

    public function sendDueReminders(): void
    {
        $tasks = DailyPlanTask::whereHas('dailyPlan', fn ($q) => $q->whereDate('date', now()->toDateString()))
            ->where('is_done', false)
            ->whereNotNull('remind_at')
            ->with(['dailyPlan.user', 'reminders'])
            ->get();

        $dueTasks = collect();

        foreach ($tasks as $task) {
            $user = $task->dailyPlan->user;
            $localNow = now($user->timezone);

            if ($localNow->format('H:i:s') < $task->remind_at) {
                continue;
            }

            $lastReminder = $task->reminders->sortByDesc('sent_at')->first();
            if ($lastReminder && $lastReminder->sent_at->diffInMinutes(now()) < 60) {
                continue;
            }

            $dueTasks->push($task);
        }

        foreach ($dueTasks->groupBy(fn ($task) => $task->dailyPlan->user_id) as $userTasks) {
            $user = $userTasks->first()->dailyPlan->user;
            $this->telegramService->sendReminderBatch($user->telegram_id, $userTasks->sortBy('title')->values());

            foreach ($userTasks as $task) {
                $task->reminders()->create(['sent_at' => now()]);
            }
        }
    }

}
