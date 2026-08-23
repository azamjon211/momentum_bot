<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use App\Exports\ApplicationsExport;
use Maatwebsite\Excel\Facades\Excel;

class ApplicationController extends Controller
{
    public function __construct(
        private Container $app
    ) {
    }

    public function index(){
        return Application::query()
            ->where('user_id', auth()->id())
            ->get();

    }
    public function store(Request $request){
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $application = Application::create(
            [
                'user_id' => auth()->id(),
                'title' => $data['title'],
                'status' => 'pending',
                'description' => $request->description,
            ]);
            return response()->json($application, 201);
    }
    public function show(Application $application){
        if ($application->user_id !== auth()->user()->id) {
            return response()->json([
                'message' => 'You do not have access to this application.',
            ], 403);
        }        return response()->json($application, 200);
    }
    public function export(Request $request){
        $export = $this->app->makeWith(ApplicationsExport::class, ['user' => $request->user()]);
        $content = Excel::raw(
            $export,
            \Maatwebsite\Excel\Excel::XLSX
        );
        return response()->json([
            'filename' => 'applications.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheet',
            'file' => base64_encode($content)
        ]);
    }

}
