<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function __construct(private TelegramService $service)
    {
    }
    public function handle(Request $request){
        $this->service->handleUpdate($request->all());
        return response()->json(['Ok' => true]);
    }
}
