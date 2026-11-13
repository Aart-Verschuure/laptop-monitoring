<?php

use App\Http\Controllers\Api\ReportController;
use App\Http\Middleware\AuthenticateDevice;
use Illuminate\Support\Facades\Route;

Route::post('/report', ReportController::class)
    ->middleware([AuthenticateDevice::class, 'throttle:120,1']);
