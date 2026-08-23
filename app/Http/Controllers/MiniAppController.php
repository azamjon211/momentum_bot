<?php

namespace App\Http\Controllers;

use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Services\DailyPlanService;
use Illuminate\Http\Request;

class MiniAppController extends Controller
{
    public function __construct(private DailyPlanService $service) {}

    public function todayTasks(Request $request)
    {
        $user = $request->attributes->get('telegramUser');

        $dailyPlan = DailyPlan::where('user_id', $user->id)
            ->whereDate('date', now($user->timezone)->toDateString())
            ->with('tasks')
            ->first();

        return response()->json([
            'tasks' => $dailyPlan?->tasks ?? [],
        ]);
    }

    public function completeTask(Request $request, DailyPlanTask $task)
    {
        $user = $request->attributes->get('telegramUser');

        abort_unless($task->dailyPlan->user_id === $user->id, 403);

        return response()->json($this->service->complete($task));
    }
}
