<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreWeeklyPlanTaskRequest;
use App\Models\WeeklyPlan;
use App\Services\WeeklyPlanService;

class WeeklyPlanTaskController extends Controller
{
    public function __construct(private WeeklyPlanService $service) {}
    public function store(StoreWeeklyPlanTaskRequest $request, WeeklyPlan $plan){
        $task = $this->service->addTask($plan, $request->validated());
        return response()->json($task, 201);
    }

}
