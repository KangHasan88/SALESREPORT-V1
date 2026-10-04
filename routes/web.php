<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SkbdReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/logout-success', function () {
    return view('auth.logout-success');
})->middleware('guest')->name('logout.success');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/admin', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/report/skbd', [SkbdReportController::class, 'salesOrder'])->name('reports.skbd');
    Route::get('/report/skbd/export', [SkbdReportController::class, 'exportSalesOrder'])->name('reports.skbd.export');
    Route::get('/report/skb3', [SkbdReportController::class, 'salesOrderSkb3'])->name('reports.skb3');
    Route::get('/report/skb3/export', [SkbdReportController::class, 'exportSalesOrderSkb3'])->name('reports.skb3.export');
    Route::get('/report/konsolidasi', [SkbdReportController::class, 'salesOrderConsolidated'])->name('reports.konsolidasi');
    Route::get('/report/konsolidasi/export', [SkbdReportController::class, 'exportSalesOrderConsolidated'])->name('reports.konsolidasi.export');
    Route::get('/admin/report/konsolidasi-v1', [SkbdReportController::class, 'salesOrderConsolidatedV1'])->name('reports.konsolidasi-v1');
    Route::get('/admin/report/konsolidasi-v1/export', [SkbdReportController::class, 'exportSalesOrderConsolidatedV1'])->name('reports.konsolidasi-v1.export');
    Route::get('/admin/report/konsolidasi-query', [SkbdReportController::class, 'salesOrderConsolidatedQuery'])->name('reports.konsolidasi-query');
    Route::get('/admin/report/konsolidasi-query/export', [SkbdReportController::class, 'exportSalesOrderConsolidatedQuery'])->name('reports.konsolidasi-query.export');
});

require __DIR__.'/auth.php';
