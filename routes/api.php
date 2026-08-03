<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\DemoMetricsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReportPdfController;
use App\Http\Controllers\RewriteController;
use Illuminate\Support\Facades\Route;

// The numbers that pre-fill the metrics form, so the demo needs no typing.
Route::get('/demo-metrics', DemoMetricsController::class);

Route::get('/pages',  [PageController::class, 'index']);
Route::post('/pages', [PageController::class, 'store']);

Route::post('/pages/{page}/audits', [AuditController::class, 'store']);

Route::get('/audits/{audit}',         [AuditController::class, 'show']);
Route::get('/audits/{audit}/report',  [AuditController::class, 'report']);
Route::post('/audits/{audit}/retry',  [AuditController::class, 'retry']);

// On click, after the report exists — not in the pipeline, where it would be
// billed on every audit whether or not anyone reads it.
Route::post('/audits/{audit}/rewrite', RewriteController::class);
Route::get('/audits/{audit}/pdf',     ReportPdfController::class);
