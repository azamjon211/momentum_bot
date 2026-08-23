<?php

namespace App\Http\Controllers;

use App\Models\DailyPlan;
use App\Models\DailyPlanTask;
use App\Services\DailyPlanService;
use Illuminate\Http\Request;

class DailyPlanTaskController extends Controller
{
    public function __construct(private DailyPlanService $service){}
    public function complete(DailyPlanTask $task){
        return response()->json(
            $this->service->complete($task));
    }
}
