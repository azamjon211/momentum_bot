<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWeeklyPlanRequest;
use App\Models\WeeklyPlan;
use App\Services\WeeklyPlanService;

class WeeklyPlanController extends Controller
{
    public function __construct(Private WeeklyPlanService $service){}
    public function store(StoreWeeklyPlanRequest $request){
        $plan = $this->service->create($request->user(), $request->validated('name'));
        return response()->json($plan, 201);
    }
    public function activate(WeeklyPlan $plan){
        return response()->json($this->service->activate($plan));
    }
}
