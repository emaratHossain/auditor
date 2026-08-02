<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReportPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/pages',  [PageController::class, 'index']);
Route::post('/pages', [PageController::class, 'store']);

Route::post('/pages/{page}/audits', [AuditController::class, 'store']);

Route::get('/audits/{audit}',         [AuditController::class, 'show']);
Route::get('/audits/{audit}/report',  [AuditController::class, 'report']);
Route::post('/audits/{audit}/retry',  [AuditController::class, 'retry']);
Route::get('/audits/{audit}/pdf',     ReportPdfController::class);
