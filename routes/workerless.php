<?php

use HimaCode\Workerless\Http\DashboardController;
use HimaCode\Workerless\Http\Middleware\Authorize;
use HimaCode\Workerless\Http\TickController;
use Illuminate\Support\Facades\Route;

// Signed self-call. Not in the "web" group: no session, no cookies, no CSRF.
// Every request without a valid one-time signature gets a 404.
Route::post(TickController::PATH, TickController::class)->name('workerless.tick');

// Dashboard. "web" gives sessions and CSRF protection; Authorize closes it outside "local".
if (config('workerless.dashboard.enabled', true)) {
    Route::prefix(trim((string) config('workerless.dashboard.path', 'workerless'), '/'))
        ->middleware(array_merge((array) config('workerless.dashboard.middleware', ['web']), [Authorize::class]))
        ->name('workerless.')
        ->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('index');
            Route::post('failed/retry-all', [DashboardController::class, 'retryAll'])->name('failed.retry-all');
            Route::post('failed/{id}/retry', [DashboardController::class, 'retry'])->name('failed.retry');
            Route::post('failed/{id}/delete', [DashboardController::class, 'forget'])->name('failed.delete');
            Route::post('history/clear', [DashboardController::class, 'clearHistory'])->name('history.clear');
            Route::post('diagnostics', [DashboardController::class, 'runDiagnostics'])->name('diagnostics.run');
            Route::post('mode', [DashboardController::class, 'setMode'])->name('mode');
        });
}
