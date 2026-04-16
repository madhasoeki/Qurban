<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::middleware(config('fortify.middleware', ['web']))->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->middleware(['guest:'.config('fortify.guard')])
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware(array_filter([
            'guest:'.config('fortify.guard'),
            config('fortify.limiters.login') ? 'throttle:'.config('fortify.limiters.login') : null,
        ]))
        ->name('login.store');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')])
        ->name('logout');
});

Route::livewire('/', 'pages::dashboard')->name('home');
Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::livewire('users', 'pages::user.index')
        ->middleware('role:admin')
        ->name('users.index');

    Route::livewire('sohibul', 'pages::sohibul.index')
        ->middleware('role:admin')
        ->name('sohibul.index');

    Route::livewire('jagal', 'pages::hewan.jagal')
        ->middleware('role:admin|jagal')
        ->name('workflow.jagal');

    Route::livewire('kuliti', 'pages::hewan.kuliti')
        ->middleware('role:admin|kuliti')
        ->name('workflow.kuliti');

    Route::livewire('cacah-daging', 'pages::hewan.cacah-daging')
        ->middleware('role:admin|cacah_daging')
        ->name('workflow.cacah_daging');

    Route::livewire('cacah-tulang', 'pages::hewan.cacah-tulang')
        ->middleware('role:admin|cacah_tulang')
        ->name('workflow.cacah_tulang');

    Route::livewire('jeroan', 'pages::hewan.jeroan')
        ->middleware('role:admin|jeroan')
        ->name('workflow.jeroan');

    Route::livewire('packing', 'pages::hewan.packing')
        ->middleware('role:admin|packing')
        ->name('workflow.packing');

    Route::livewire('distribusi', 'pages::hewan.distribusi')
        ->middleware('role:admin|distribusi')
        ->name('workflow.distribusi');

    Route::livewire('penimbang', 'pages::hewan.penimbang')
        ->middleware('role:admin|penimbang')
        ->name('workflow.penimbang');
});

require __DIR__.'/settings.php';
