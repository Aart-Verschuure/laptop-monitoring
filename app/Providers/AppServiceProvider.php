<?php

namespace App\Providers;

use App\Models\Device;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('nl');

        View::composer('layouts.app', function ($view) {
            $view->with('navDevices', auth()->check()
                ? Device::orderByRaw("type = 'laptop' desc")->orderBy('name')->get()
                : collect());
        });
    }
}
