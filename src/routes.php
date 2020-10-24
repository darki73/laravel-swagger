<?php

use Illuminate\Support\Facades\Route;
use FreedomCore\Swagger\Http\Controllers\SwaggerController;


Route::prefix(config('swagger.path', '/documentation'))->group(static function() {
    Route::get('', [SwaggerController::class, 'api']);
    Route::get('content', [SwaggerController::class, 'documentation']);
});
