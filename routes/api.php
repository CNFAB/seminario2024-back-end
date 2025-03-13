<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\JWTAuthController;
use App\Http\Controllers\DiagnosticoController;
use App\Http\Controllers\DispositivoController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\ModeloController;
use App\Http\Controllers\PrecioReparacionController;
use App\Http\Controllers\ReparacionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;
use App\Http\Middleware\JwtMiddleware;
use App\Models\Diagnostico;
use App\Models\Ingreso_d;

Route::get('/usuario', [UsuarioController::class, 'index']);

Route::get('/usuario/{id}', function () {
    return 'obtencion del usuario';
});

Route::post('/usuario', [UsuarioController::class, 'store']);

Route::put('/usuario/{id}', function () {
    return 'actualizando usuario';
});
Route::delete('/usuario/{id}', function () {
    return 'eliminado usuario';
});


// ----------------------parte cliente ---------------------------

// php artisan make:controller ClienteController --api crea una api

Route::get('/cliente', [ClienteController::class, 'index']);
// cliente muestra todos los datos de la tabla cliente

Route::get('/cliente/{idCliente}', [ClienteController::class, 'show']);
// $cliente = Cliente::where('nombre','Fabiel')->get();
//muestra solo 1 dato que coincida con el idCliente

Route::get('/cliente-cond/{idCliente}', [ClienteController::class, 'condicion']);
//condicion del cliente pareciod al 1 solo que eta tiene condicion de oto campo
Route::put('/cliente/{idCliente}', [ClienteController::class, 'update']);
//actualiza los campos puestos del cliente 

Route::post('/cliente-agregar', [ClienteController::class, 'store']);
//post agrega datos de los clientes
Route::resource('/controlador', ClienteController::class);
Route::delete('/cliente-delete/{idCliente}', [ClienteController::class, 'destroy']);
// borrra todo el registro del cliente;
Route::post('/filtro', [ClienteController::class, 'filtro']);
//-------------------------parte-Diagnostico---------------------
Route::post('/diagnostico-agregar', [DiagnosticoController::class, 'store']);
Route::get('/diagnostico', [DiagnosticoController::class, 'index']);

//--------------------------parte-Ingreso-D---------------------------------
Route::post('/ingresoD-agregar', [IngresoController::class, 'store']);//medio listo las cosas
Route::get('/ingresoD', [IngresoController::class, 'index']);//medio- listo las cossas
//---------------------------parte-Dispositivo----------------
Route::post('/dispositivo-agregar', [DispositivoController::class, 'store']);//listo
Route::get('/dispositivo', [DispositivoController::class, 'index']);//listo
//-------------------------------parte-marca---------------------------
Route::post('/marca-agregar', [MarcaController::class, 'store']); //listo
Route::get('/marca', [MarcaController::class, 'index']);//listo
//--------------------------------parte modelo------------------------
Route::get('/modelo', [ModeloController::class, 'index']);//listo
Route::post('/modelo-agregar', [ModeloController::class, 'store']);//listo
//--------------------------------parte reparacion----------------------
Route::get('/reparacion', [ReparacionController::class, 'index']);//ahora nop
Route::post('/reparacion-agregar', [ReparacionController::class, 'store']);//ahora nop
//----------------------------parte precio de la reparacion-------------
Route::get('/precioReparacion', [PrecioReparacionController::class, 'index']);//ahora nop
Route::post('/precioRe-agregar', [PrecioReparacionController::class, 'store']);//ahora nop


//--------------------------------ruta de prueba xd
Route:: post('/inicioAgregar',[JWTAuthController::class,'register']);
Route:: post('/inicio-sesion',[JWTAuthController::class,'login']);
Route:: post('/cliente-sesion',[JWTAuthController::class,'getUser']);
//Route:: post('/cerrar-sesion',[JWTAuthController::class,'logout']);
//--7------------------.-----------ruta de authoController-----
//Route ::post ('inicioAut',[AuthController::class,'login']);
//Route:: post('/inicio-sesion2',[AuthController::class,'login']);
Route :: middleware([JwtMiddleware::class])->group(function(){
    Route::post('/cliente-sesion',[JWTAuthController::class,'getUser']);
    Route ::post('/cerrar-sesion',[JWTAuthController::class,'logout']);
});