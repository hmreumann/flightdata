<?php

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;

Route::middleware([InitializeTenancyBySubdomain::class])->group(function(){
    Route::get('/', function () {
        if(tenancy()->initialized){
            return to_route('dashboard');
        }

        return view('welcome');
    });

    Route::middleware([
        'auth:sanctum',
        config('jetstream.auth_session'),
        'verified',
    ])->group(function () {
        Route::get('/dashboard', function () {
            if(!tenancy()->initialized){
                return to_route('filament.admin.pages.dashboard');
            }

            return view('dashboard');
        })->name('dashboard');
    });
});
