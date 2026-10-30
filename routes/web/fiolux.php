<?php

use App\Http\Controllers\Fiolux\NotebookController;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

Route::middleware('auth')->prefix('fiolux')->group(function (): void {
    Route::get('/', [NotebookController::class, 'index'])->name('fiolux.index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(trans('fiolux.title'), route('fiolux.index')));
    Route::get('/assets/{asset}/checklist', [NotebookController::class, 'create'])->name('fiolux.create')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('fiolux.index')->push(trans('fiolux.checklist')));
    Route::post('/assets/{asset}/checklist', [NotebookController::class, 'store'])->name('fiolux.store');
    Route::get('/cycles/{cycle}', [NotebookController::class, 'show'])->name('fiolux.show')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('fiolux.index')->push(trans('fiolux.cycle')));
    Route::post('/cycles/{cycle}/technical', [NotebookController::class, 'technical'])->name('fiolux.technical');
    Route::post('/inspections/{inspection}/pdf', [NotebookController::class, 'generate'])->name('fiolux.pdf');
    Route::post('/inspections/{inspection}/signed', [NotebookController::class, 'upload'])->name('fiolux.upload');
    Route::post('/documents/{document}/review', [NotebookController::class, 'review'])->name('fiolux.review');
    Route::get('/documents/{document}', [NotebookController::class, 'download'])->name('fiolux.download')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('fiolux.index')->push(trans('fiolux.documents')));
});
