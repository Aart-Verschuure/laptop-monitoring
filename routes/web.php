<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrowsingImportController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\MonitorController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DeviceController::class, 'home'])->name('home');

    Route::get('/apparaten', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('/apparaten', [DeviceController::class, 'store'])->name('devices.store');
    Route::post('/apparaten/{device}/token', [DeviceController::class, 'rotateToken'])->name('devices.token');
    Route::delete('/apparaten/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

    Route::prefix('/apparaten/{device}')->name('devices.')->controller(MonitorController::class)->group(function () {
        Route::get('/dashboard', 'dashboard')->name('dashboard');
        Route::get('/browsen', 'browsing')->name('browsing');
        Route::get('/systeem', 'system')->name('system');
        Route::get('/inloggen', 'logins')->name('logins');
    });

    Route::post('/apparaten/{device}/browsen/import', BrowsingImportController::class)->name('devices.browsing.import');
});
