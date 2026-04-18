<?php

use Illuminate\Support\Facades\Route;
use Relova\Http\Controllers\Api\ConnectionController;
use Relova\Http\Controllers\Api\ModuleMappingController;
use Relova\Http\Controllers\Api\QueryController;
use Relova\Http\Controllers\Api\ReferenceController;
use Relova\Http\Controllers\Api\SchemaController;
use Relova\Http\Middleware\RelovaApiAuth;

Route::middleware([RelovaApiAuth::class])->group(function () {

    Route::get('drivers', [ConnectionController::class, 'drivers'])->name('relova.drivers');

    Route::apiResource('connections', ConnectionController::class)
        ->parameters(['connections' => 'uid']);

    Route::post('connections/{uid}/test', [ConnectionController::class, 'test'])->name('relova.connections.test');
    Route::get('connections/{uid}/health', [ConnectionController::class, 'healthCheck'])->name('relova.connections.health');

    Route::get('connections/{connectionUid}/tables', [SchemaController::class, 'tables'])->name('relova.schema.tables');
    Route::get('connections/{connectionUid}/tables/{table}/columns', [SchemaController::class, 'columns'])->name('relova.schema.columns');
    Route::get('connections/{connectionUid}/tables/{table}/preview', [SchemaController::class, 'preview'])->name('relova.schema.preview');
    Route::post('connections/{connectionUid}/flush-cache', [SchemaController::class, 'flushCache'])->name('relova.schema.flush');

    Route::post('connections/{connectionUid}/query', [QueryController::class, 'execute'])->name('relova.query.execute');
    Route::post('connections/{connectionUid}/search', [QueryController::class, 'search'])->name('relova.query.search');

    Route::get('connections/{connectionUid}/references', [ReferenceController::class, 'index'])->name('relova.references.index');
    Route::post('connections/{connectionUid}/references/resolve', [ReferenceController::class, 'resolve'])->name('relova.references.resolve');
    Route::post('connections/{connectionUid}/references/search', [ReferenceController::class, 'search'])->name('relova.references.search');
    Route::get('connections/{connectionUid}/references/{referenceUid}', [ReferenceController::class, 'show'])->name('relova.references.show');
    Route::post('connections/{connectionUid}/references/{referenceUid}/refresh', [ReferenceController::class, 'refresh'])->name('relova.references.refresh');

    Route::apiResource('connections.mappings', ModuleMappingController::class)
        ->parameters(['connections' => 'connectionUid', 'mappings' => 'mappingUid']);
});
