<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AsistenteProyectoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí es donde registras las rutas de tu API. Estas rutas se cargan
| automáticamente en un grupo que aplica el middleware "api".
|
*/

Route::middleware('api')->group(function () {
    // Ruta para validar el plan de proyecto mediante OpenAI
    Route::post('/proyectos/validar', [AsistenteProyectoController::class, 'validarPlan']);

    // (Opcional) Si vas a usar autenticación Sanctum, podrías conservar esta ruta:
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
});
