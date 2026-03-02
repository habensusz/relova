<?php

use Illuminate\Support\Facades\Route;
use Relova\Http\Controllers\Api\ConnectionController;
use Relova\Http\Controllers\Api\EntityReferenceController;
use Relova\Http\Controllers\Api\FieldMappingController;
use Relova\Http\Controllers\Api\QueryController;
use Relova\Http\Controllers\Api\SchemaController;
use Relova\Http\Middleware\RelovaApiAuth;

/*
|--------------------------------------------------------------------------
| Relova API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RelovaServiceProvider and use the
| api prefix and middleware defined in config/relova.php.
|
*/

Route::middleware([RelovaApiAuth::class])->group(function () {

    // Drivers info (public within auth)
    Route::get('drivers', [ConnectionController::class, 'drivers'])->name('relova.drivers');

    // Connection CRUD
    Route::apiResource('connections', ConnectionController::class)->parameters(['connections' => 'uid']);

    // Connection actions
    Route::post('connections/{uid}/test', [ConnectionController::class, 'test'])->name('relova.connections.test');
    Route::get('connections/{uid}/health', [ConnectionController::class, 'healthCheck'])->name('relova.connections.health');

    // Schema browsing
    Route::get('connections/{connectionUid}/tables', [SchemaController::class, 'tables'])->name('relova.schema.tables');
    Route::get('connections/{connectionUid}/tables/{table}/columns', [SchemaController::class, 'columns'])->name('relova.schema.columns');
    Route::get('connections/{connectionUid}/tables/{table}/preview', [SchemaController::class, 'preview'])->name('relova.schema.preview');
    Route::post('connections/{connectionUid}/flush-cache', [SchemaController::class, 'flushCache'])->name('relova.schema.flush');

    // Query execution
    Route::post('connections/{connectionUid}/query', [QueryController::class, 'execute'])->name('relova.query.execute');
    Route::post('connections/{connectionUid}/select', [QueryController::class, 'select'])->name('relova.query.select');

    // Entity references
    Route::get('connections/{connectionUid}/references', [EntityReferenceController::class, 'index'])->name('relova.references.index');
    Route::post('connections/{connectionUid}/references/resolve', [EntityReferenceController::class, 'resolve'])->name('relova.references.resolve');
    Route::post('connections/{connectionUid}/references/search', [EntityReferenceController::class, 'search'])->name('relova.references.search');
    Route::get('connections/{connectionUid}/references/{referenceUid}', [EntityReferenceController::class, 'show'])->name('relova.references.show');
    Route::post('connections/{connectionUid}/references/{referenceUid}/refresh', [EntityReferenceController::class, 'refreshSnapshot'])->name('relova.references.refresh');

    // Field mappings
    Route::apiResource('connections/{connectionUid}/mappings', FieldMappingController::class)
        ->parameters(['mappings' => 'mappingUid']);
    Route::get('connections/{connectionUid}/mappings/{mappingUid}/preview', [FieldMappingController::class, 'preview'])->name('relova.mappings.preview');
});
