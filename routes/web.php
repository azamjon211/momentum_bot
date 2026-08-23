<?php

use App\Pipelines\Order\GeneratingInvoice;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Lottery;
use Illuminate\View\ViewServiceProvider;

Route::get('/', function () {
    return view('layout');
});
