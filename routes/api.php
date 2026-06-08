<?php

use App\Http\Controllers\AuthController;//por quitar
use App\Http\Controllers\ClientePasswordController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\JWTAuthController;
use App\Http\Controllers\UsuarioAuthController;
use App\Http\Middleware\JwtClienteMiddleware;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\DiagnosticoController;
use App\Http\Controllers\DispositivoController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\ModeloController;
use App\Http\Controllers\ReparacionController;
use App\Http\Controllers\ReparacionMultipleController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\PiezaController;
use App\Http\Controllers\PrecioReparacionController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\EstadisticaController;
use App\Http\Controllers\CompatibilidadController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\PresupuestoDetalleController;
use App\Http\Controllers\GarantiaController;
use App\Http\Controllers\GarantiaPiezaController;
use App\Http\Controllers\ReclamoGarantiaController;
use App\Http\Controllers\TestimonioController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;
use App\Http\Middleware\JwtMiddleware;
use App\Http\Middleware\JwtAdminTecnicoMiddleware;
use App\Http\Middleware\JwtRoleMiddleware;
use App\Http\Controllers\ReporteGlobalController;




// ============================================
// USUARIOS (ADMINISTRACIÓN INTERNA)
// ============================================
Route::prefix('auth/usuario')->group(function () {
    Route::post('login', [UsuarioAuthController::class, 'login']);
      Route::post('logout', [UsuarioAuthController::class, 'logout']); 
    Route::get('/tecnicos/disponibles', [DiagnosticoController::class, 'tecnicosDisponibles']);
    Route::get('/diagnosticos/pendientes/{idTecnico}', [DiagnosticoController::class, 'pendientesPorTecnico']);
    Route::put('/diagnosticos/{id}/reasignar', [DiagnosticoController::class, 'reasignar']);
    Route::post('/diagnosticos/reasignar-multiples', [DiagnosticoController::class, 'reasignarMultiples']);
    // api.php — dentro del prefix 'auth/usuario'
    Route::post('/reparaciones/reasignar-multiples', [ReparacionController::class, 'reasignarMultiples']);
    Route::get('/reparaciones/tecnicos-disponibles', [ReparacionController::class, 'tecnicosDisponiblesReparaciones']);

    });

Route::prefix('usuario')->group(function () {
    Route::get('/',           [UsuarioController::class, 'index']);
    Route::post('/',          [UsuarioController::class, 'store']);
    Route::get('/{id}',       [UsuarioController::class, 'show']);
    Route::patch('/{id}/toggle-activo', [UsuarioController::class, 'toggleActivo']); 
    Route::put('/{id}',       [UsuarioController::class, 'update']);
    Route::delete('/{id}',    [UsuarioController::class, 'destroy']);
});
 
// Rutas especiales de usuarios (roles)
Route::get('/usuarios/activos', [UsuarioController::class, 'activos']);
Route::get('/usuarios/inactivos', [UsuarioController::class, 'inactivos']);
Route::get('/usuarios/tecnicos',               [UsuarioController::class, 'tecnicos']);
Route::get('/usuarios/recepcionistas',         [UsuarioController::class, 'recepcionistas']);
Route::get('/usuarios/administradores',        [UsuarioController::class, 'administradores']);
Route::get('/usuarios/tecnico-disponible',     [UsuarioController::class, 'obtenerTecnicoDisponible']);
Route::get('/usuarios/carga-trabajo-tecnicos', [UsuarioController::class, 'obtenerCargaTrabajoTecnicos']);

// ============================================
// 1. RUTAS PÚBLICAS DE CLIENTE (SIN AUTENTICACIÓN)
// ============================================

Route::post('/inicioAgregar',  [JWTAuthController::class, 'register']); // Registro de cliente
Route::post('/inicio-sesion',  [JWTAuthController::class, 'login']);    // Login admin/usuario
Route::post('/cliente-sesion', [JWTAuthController::class, 'login']);    // Login cliente (alias)
// Route::get('/crear-preferencia', [PagoController::class, 'crearPreferencia']);
Route::post('/crear-preferencia', [PagoController::class, 'crearPreferencia']);
Route::get('/verificar-pago', [PagoController::class, 'verificarPorPreferencia']);
Route::get('/ultimoCliente',   [ClienteController::class, 'ultimoCliente']); // Último cliente registrado
Route::post('/cliente-agregar',[ClienteController::class, 'store']);    // Crear cliente sin auth

// ============================================
// 2. RUTAS PROTEGIDAS PARA CLIENTES (JWT)
// IMPORTANTE: Las rutas FIJAS deben ir SIEMPRE antes
// que las rutas con parámetros dinámicos {id},
// de lo contrario Laravel captura 'mis-dispositivos'
// como valor del parámetro y genera error SQL.
// ============================================

Route::get('/cliente-buscar', [ClienteController::class, 'buscarConDispositivos']);
 Route::put('/cliente/{idCliente}',      [ClienteController::class, 'update']);
Route::get('/cliente',                 [ClienteController::class,  'index']);    
Route::middleware([JwtClienteMiddleware::class])->group(function () {

    // ---- 2a. RUTAS FIJAS (SIN PARÁMETROS) ----
        // Listar todos los clientes
    Route::get('/cliente/mis-dispositivos',[ClienteController::class,  'misDispositivos']); // ← Dispositivos del cliente autenticado
    Route::get('/cliente/perfil',          [JWTAuthController::class,  'getUser']);      // Perfil del cliente autenticado
    Route::post('/cerrar-sesion',          [JWTAuthController::class,  'logout']);       // Cerrar sesión
    Route::post('/refresh',                [JWTAuthController::class,  'refresh']);      // Refrescar token
    Route::post('/filtro',                 [ClienteController::class,  'filtro']);       // Filtrar clientes
    Route::get('/diagnosticos/dispositivo/{idDispositivo}', [DiagnosticoController::class, 'porDispositivoCliente']);
     Route::get('/cliente/historial-reparaciones', [ClienteController::class, 'historialReparaciones']);
    // Las rutas con parámetros al final
    Route::get('/cliente/dispositivo/{id}', [ClienteController::class, 'detalleDispositivo']);
    // ---- 2b. RUTAS CON PARÁMETROS (DESPUÉS DE LAS FIJAS) ----
    // IMPORTANTE: /cliente/dispositivo/{id} debe ir ANTES de /cliente/{idCliente}
    // para que no sea capturada como un idCliente con valor 'dispositivo'
    Route::get('/cliente/dispositivo/{id}', [ClienteController::class, 'detalleDispositivo']); // Detalle de un dispositivo del cliente
    Route::get('/cliente/{idCliente}',      [ClienteController::class, 'show']);               // Ver un cliente por ID
    Route::get('/cliente-cond/{idCliente}', [ClienteController::class, 'condicion']);          // Cliente por condición
    Route::put('/cliente/{idCliente}',      [ClienteController::class, 'update']);
                 // Actualizar cliente
      Route::get('/reparaciones/cliente/pendientes/{idDispositivo}', 
        [ReparacionController::class, 'getPendientesPorDispositivo']);
    Route::delete('/cliente-delete/{idCliente}', [ClienteController::class, 'destroy']);       // Eliminar cliente

});

// ============================================
// INGRESOS
// ============================================
// ============================================
// RUTAS FIJAS (sin parámetros) - VAN PRIMERO
// ============================================

// Rutas de estado (fijas)
Route::get('/ingresos/en-taller', [IngresoController::class, 'enTaller']);
Route::get('/ingresos/retirados', [IngresoController::class, 'retirados']);
Route::get('/ingresoD/revision', [IngresoController::class, 'revision']);

// CRUD básico
Route::get('/ingresoD', [IngresoController::class, 'index']);
Route::post('/ingresoD-agregar', [IngresoController::class, 'store']);

// ============================================
// RUTAS CON PARÁMETROS - VAN DESPUÉS
// ============================================
// Rutas para obtener ingresos por cliente (debe ir ANTES de /ingresos/{id})
Route::get('/ingresos/cliente/{idCliente}', [IngresoController::class, 'porCliente']);
Route::post('/ingresos/{id}/pagar-local', [IngresoController::class, 'pagarLocal']);
Route::get('/ingresos/cliente/{idCliente}/listos', [IngresoController::class, 'listosPorCliente']);

// Rutas para listar dispositivos listos para retirar
Route::get('/ingresos/listos-para-retirar', [IngresoController::class, 'listosParaRetirar']);

// Ruta para retirar dispositivo (marcar como retirado)
Route::patch('/ingresos/{id}/retirar', [IngresoController::class, 'retirarDispositivo']);
// Actualizar (PUT)
Route::put('/ingresoD/{id_ingresoD}', [IngresoController::class, 'update']);

// Cambios de estado (con parámetro ID)
Route::patch('/ingresos/{id}/cambiar-estado', [IngresoController::class, 'cambiarEstado']);
Route::patch('/ingresos/{id}/marcar-retirado', [IngresoController::class, 'marcarRetirado']);

// Rutas con parámetros opcionales
Route::get('/ingresos/dispositivo/{idDispositivo}/garantias-retiradas', 
    [IngresoController::class, 'garantiasRetiradasPorDispositivo']);
Route::get('/ingresos/dispositivo/{idDispositivo}/{idTecnico?}', [IngresoController::class, 'porDispositivo']);
// ============================================
// DISPOSITIVOS
// ============================================
Route::post('/dispositivo-agregar',   [DispositivoController::class, 'store']);
Route::get('/dispositivo',            [DispositivoController::class, 'index']);

Route::get('/ultimoDispositivo',      [DispositivoController::class, 'ultimoDispositivo']);
Route::get('/dispositivos/{id}', [DispositivoController::class, 'show']);


// ============================================
// MARCAS
// ============================================
Route::get('/marca',        [MarcaController::class, 'index']);
Route::post('/marca',       [MarcaController::class, 'store']);
Route::get('/marca/{id}',   [MarcaController::class, 'show']);
Route::put('/marca/{id}',   [MarcaController::class, 'update']);
Route::patch('/marca/{id}', [MarcaController::class, 'update']);
Route::delete('/marca/{id}',[MarcaController::class, 'destroy']);

// ============================================
// MODELOS
// ============================================
// NOTA: 'por-marca/{id_marca}' va ANTES de '/{id}' para evitar conflictos
Route::get('/modelo/por-marca/{id_marca}', [ModeloController::class, 'porMarca']);
Route::get('/modelo',              [ModeloController::class, 'index']);
Route::post('/modelo',             [ModeloController::class, 'store']);
Route::get('/modelo/{id}',         [ModeloController::class, 'show']);
Route::put('/modelo/{id}',         [ModeloController::class, 'update']);
Route::patch('/modelo/{id}',       [ModeloController::class, 'update']);
Route::delete('/modelo/{id}',      [ModeloController::class, 'destroy']);
Route::prefix('modelos')->group(function () {
    Route::get('/{modeloId}/compatibilidades', [CompatibilidadController::class, 'getByModelo']);
    Route::get('/{modeloId}/piezas-compatibles', [CompatibilidadController::class, 'getPiezasCompatibles']);
    Route::post('/{modeloId}/compatibilidades/sync', [CompatibilidadController::class, 'syncForModelo']);
});
// ============================================
// REPARACIONES
// ============================================
Route::get('/reparaciones/tecnico/{idTecnico}', [ReparacionController::class, 'porTecnico']);
Route::prefix('reparaciones')->group(function () {

    // Rutas fijas primero
    Route::get('/mis-reparaciones',      [ReparacionController::class, 'misReparaciones']);
    Route::get('/estadisticas/generales',[ReparacionController::class, 'estadisticas']);

    Route::get('/pendientes', function (Request $request) {
        $request->merge(['estado_general' => 'PENDIENTE']);
        return app(ReparacionController::class)->index($request);
    });
    Route::get('/en-reparacion', function (Request $request) {
        $request->merge(['estado_general' => 'EN_REPARACION']);
        return app(ReparacionController::class)->index($request);
    });
    Route::get('/terminadas', function (Request $request) {
        $request->merge(['estado_general' => 'TERMINADA']);
        return app(ReparacionController::class)->index($request);
    });
         Route::get('/cliente/pendientes/{idDispositivo}', [ReparacionMultipleController::class, 'getPendientesPorDispositivo']);

     Route::get('/activas-tecnico/{idTecnico}', [ReparacionController::class, 'reparacionesActivasPorTecnico']);
      Route::get('/ingreso-con-diagnostico/{idIngreso}', [ReparacionController::class, 'getPorIngresoConDiagnostico']);


    // CRUD básico
    Route::get('/',    [ReparacionController::class, 'index']);
    Route::post('/',   [ReparacionController::class, 'store']);

    // Rutas con parámetros después
    Route::get('/{id}',                    [ReparacionController::class, 'show']);
    Route::put('/{id}',                    [ReparacionController::class, 'update']);
    Route::patch('/{id}',                  [ReparacionController::class, 'update']);
    Route::delete('/{id}',                 [ReparacionController::class, 'destroy']);
    Route::get('/{id}/resumen',            [ReparacionController::class, 'resumen']);
    Route::post('/{id}/asignar-tecnico',   [ReparacionController::class, 'asignarTecnico']);
    Route::post('/{id}/agregar-diagnostico',[ReparacionController::class, 'agregarDiagnostico']);
});

// ============================================
// DIAGNÓSTICOS
// ============================================

Route::prefix('diagnosticos')->group(function () {
    // 1. RUTAS FIJAS (sin parámetros)
    Route::get('/carga-trabajo-tecnicos', [DiagnosticoController::class, 'obtenerCargaTrabajoTecnicos']);
    Route::get('/mis-pendientes', [DiagnosticoController::class, 'misPendientes']);
    Route::get('/estadisticas/generales', [DiagnosticoController::class, 'estadisticas']);
    Route::post('/crear-automatico', [DiagnosticoController::class, 'crearAutomatico']);
    Route::get('/pendientes', function (Request $request) {
        $request->merge(['estado' => 'PENDIENTE']);
        return app(DiagnosticoController::class)->index($request);
    });
    Route::get('/completados', function (Request $request) {
        $request->merge(['estado' => 'COMPLETADO']);
        return app(DiagnosticoController::class)->index($request);
    });

    // 2. RUTAS CON PARÁMETROS NOMBRADOS (con texto fijo)
    Route::get('/tecnico/{idTecnico}', [DiagnosticoController::class, 'porTecnico']);
    Route::get('/ingreso/{ingresoId}', [DiagnosticoController::class, 'porIngreso']);
    Route::get('/usuario/{usuarioId}', [DiagnosticoController::class, 'porUsuario']);
    Route::get('/estado/{estado}', [DiagnosticoController::class, 'index']);
    Route::get('/gravedad/{gravedad}', [DiagnosticoController::class, 'index']);

    // 3. RUTAS PARA GESTIÓN DE PIEZAS (ANTES DE /{id})
    Route::get('/{id}/con-piezas', [DiagnosticoController::class, 'showWithPiezas']);
    Route::post('/{id}/piezas', [DiagnosticoController::class, 'agregarPieza']);
    Route::delete('/{id}/piezas/{piezaId}', [DiagnosticoController::class, 'eliminarPieza']);
    Route::patch('/{id}/piezas/{piezaId}', [DiagnosticoController::class, 'actualizarPieza']);
    Route::post('/{id}/enviar-aprobacion', [DiagnosticoController::class, 'enviarAprobacion']);
    Route::get('/{id}/calcular-costo', [DiagnosticoController::class, 'calcularCosto']);
    Route::patch('/{id}/cambiar-estado', [DiagnosticoController::class, 'cambiarEstado']);
    
    // ✅ NUEVAS RUTAS PARA APROBAR/RECHAZAR PIEZAS
    Route::patch('/{id}/piezas/{piezaId}/aprobar', [DiagnosticoController::class, 'aprobarPiezaDiagnostico']);
    Route::patch('/{id}/piezas/{piezaId}/rechazar', [DiagnosticoController::class, 'rechazarPiezaDiagnostico']);


    // 4. CRUD BÁSICO (AL FINAL - con parámetro genérico {id})
    Route::get('/', [DiagnosticoController::class, 'index']);
    Route::post('/', [DiagnosticoController::class, 'store']);
    Route::get('/{id}', [DiagnosticoController::class, 'show']);
    Route::put('/{id}', [DiagnosticoController::class, 'update']);
    Route::delete('/{id}', [DiagnosticoController::class, 'destroy']);
});
// ============================================
// CATEGORÍAS
// ============================================
// Rutas fijas antes de las que tienen parámetros
Route::get('/categoria-buscar/search',    [CategoriaController::class, 'search']);
Route::get('/categoria-con-mano-obra',    [CategoriaController::class, 'conManoObra']);
Route::get('/categoria-sin-mano-obra',    [CategoriaController::class, 'sinManoObra']);
Route::get('/categoria',                  [CategoriaController::class, 'index']);
Route::post('/categoria',                 [CategoriaController::class, 'store']);
Route::get('/categoria/{nombre}',         [CategoriaController::class, 'show']);
Route::put('/categoria/{id}',             [CategoriaController::class, 'update']);
Route::delete('/categoria/{nombre}',      [CategoriaController::class, 'destroy']);
Route::get('/categoria-verificar/{nombre}',[CategoriaController::class, 'exists']);
Route::get('categorias-con-garantia', [CategoriaController::class, 'conGarantia']);
Route::get('categorias/{id}/garantia', [CategoriaController::class, 'getGarantia']);
Route::put('categorias/{id}/garantia', [CategoriaController::class, 'actualizarGarantia']);


// ============================================
// PIEZAS
// ============================================
// Rutas fijas antes de las que tienen parámetros
Route::get('/pieza-con-stock',            [PiezaController::class, 'conStock']);
Route::get('/pieza-buscar/search',        [PiezaController::class, 'search']);
Route::get('/pieza-categoria/{categoriaId}',[PiezaController::class, 'porCategoria']);
Route::get('/pieza',                      [PiezaController::class, 'index']);
Route::post('/pieza',                     [PiezaController::class, 'store']);
Route::get('/pieza/{id}',                 [PiezaController::class, 'show']);
Route::put('/pieza/{id}',                 [PiezaController::class, 'update']);
Route::delete('/pieza/{id}',              [PiezaController::class, 'destroy']);
Route::post('/pieza/{id}/stock',          [PiezaController::class, 'actualizarStock']);

Route::prefix('piezas')->group(function () {
    Route::get('/{piezaId}/compatibilidades', [CompatibilidadController::class, 'getByPieza']);
    Route::get('/{piezaId}/modelos-compatibles', [CompatibilidadController::class, 'getModelosCompatibles']);
    Route::post('/{piezaId}/compatibilidades/sync', [CompatibilidadController::class, 'syncForPieza']);
});
// ============================================
// COMPATIBILIDAD
// ============================================
Route::prefix('compatibilidades')->group(function () {
 
    Route::get('/', [CompatibilidadController::class, 'index']);           // Listar todas
    Route::get('/todas', [CompatibilidadController::class, 'getAll']);
    Route::post('/', [CompatibilidadController::class, 'store']);          // Crear una
    Route::get('/estadisticas', [CompatibilidadController::class, 'estadisticas']); // Estadísticas
    Route::get('/verificar', [CompatibilidadController::class, 'verificar']); // Verificar compatibilidad
    Route::get('/{id}', [CompatibilidadController::class, 'show']);        // Ver una
    Route::put('/{id}', [CompatibilidadController::class, 'update']);      // Actualizar
    Route::patch('/{id}', [CompatibilidadController::class, 'update']);    // Actualizar parcial
    Route::delete('/{id}', [CompatibilidadController::class, 'destroy']);  // Eliminar una
    Route::delete('/', [CompatibilidadController::class, 'destroyMultiple']); // Eliminar varias
});

// ============================================
// PRECIO REPARACIÓN
// ============================================
Route::get('/precioReparacion',            [PrecioReparacionController::class, 'index']);
Route::post('/precioRe-agregar',           [PrecioReparacionController::class, 'store']);
Route::get('/precio-reparacion',           [PrecioReparacionController::class, 'index']);
Route::post('/precio-reparacion',          [PrecioReparacionController::class, 'store']);
Route::put('/precio-reparacion/{id}',      [PrecioReparacionController::class, 'update']);
Route::patch('/precio-reparacion/{id}/costo',[PrecioReparacionController::class, 'updateCosto']);

// ============================================
// REPARACIONES MÚLTIPLES
// ============================================

  Route::prefix('reparacion-multiple')->group(function () {

    // ============================================
    // 1. RUTAS FIJAS (sin parámetros dinámicos)
    // ============================================
    Route::get('/mis-reparaciones', [ReparacionMultipleController::class, 'misReparaciones']);
    Route::get('/estadisticas', [ReparacionMultipleController::class, 'estadisticas']);
    
    // ============================================
    // 2. RUTAS CON PARÁMETROS FIJOS (no colisionan con /{id})
    // ============================================
    Route::get('/reparacion/{id_reparacion}', [ReparacionMultipleController::class, 'getByReparacionId']);
    Route::get('/resumen-ingreso/{idIngreso}', [ReparacionMultipleController::class, 'resumenPorIngreso']);
    Route::get('/pendientes-dispositivo/{idDispositivo}', [ReparacionMultipleController::class, 'getPendientesPorDispositivo']);
    Route::get('/terminadas-dispositivo/{idDispositivo}', 
    [ReparacionMultipleController::class, 'getTerminadasPorDispositivo']);

     Route::get('/en-progreso-dispositivo/{idDispositivo}', 
        [ReparacionMultipleController::class, 'getEnProgresoPorDispositivo']);
      Route::get('/canceladas-dispositivo/{idDispositivo}', 
        [ReparacionMultipleController::class, 'getCanceladasPorDispositivo']);


    // ============================================
    // 3. CRUD BÁSICO
    // ============================================
    Route::get('/', [ReparacionMultipleController::class, 'index']);
    Route::post('/', [ReparacionMultipleController::class, 'store']);
    Route::get('/{id}', [ReparacionMultipleController::class, 'show']);  // ← ¡AGREGAR ESTA!
    Route::put('/{id}', [ReparacionMultipleController::class, 'update']);
    Route::patch('/{id}', [ReparacionMultipleController::class, 'update']);
    Route::delete('/{id}', [ReparacionMultipleController::class, 'destroy']);

    // ============================================
    // 4. ACCIONES ESPECÍFICAS (usan /{id})
    // ============================================
    // ✅ Nombres corregidos
    Route::post('/{id}/aprobar', [ReparacionMultipleController::class, 'aprobarPieza']);
    Route::post('/{id}/rechazar', [ReparacionMultipleController::class, 'rechazarPieza']);
    Route::post('/{id}/enviar-aprobacion', [ReparacionMultipleController::class, 'enviarAprobacion']);
    Route::post('/{id}/comenzar', [ReparacionMultipleController::class, 'comenzar']);
    Route::post('/{id}/terminar', [ReparacionMultipleController::class, 'terminar']);
    Route::post('/{id}/esperar-pieza', [ReparacionMultipleController::class, 'esperarPieza']);
    Route::patch('/{id}/retomar', [ReparacionMultipleController::class, 'retomarDesdEsperaPieza']);
    Route::post('/{id}/cancelar', [ReparacionMultipleController::class, 'cancelar']);
    Route::post('/{id}/cambiar-estado', [ReparacionMultipleController::class, 'cambiarEstado']); // opcional
    


    // 4. CRUD con parámetros al final
    Route::get('/{id}',    [ReparacionMultipleController::class, 'show']);
    Route::put('/{id}',    [ReparacionMultipleController::class, 'update']);
    Route::patch('/{id}',  [ReparacionMultipleController::class, 'update']);
    Route::delete('/{id}', [ReparacionMultipleController::class, 'destroy']);


    // CRUD básico
    Route::get('/',    [ReparacionMultipleController::class, 'index']);
    Route::post('/',   [ReparacionMultipleController::class, 'store']);

    // Rutas con parámetros después
    Route::get('/reparacion/{id_reparacion}',  [ReparacionMultipleController::class, 'getByReparacionId']);
    Route::get('/{id}',                        [ReparacionMultipleController::class, 'show']);
    Route::put('/{id}',                        [ReparacionMultipleController::class, 'update']);
    Route::patch('/{id}',                      [ReparacionMultipleController::class, 'update']);
    Route::delete('/{id}',                     [ReparacionMultipleController::class, 'destroy']);
  
    // Route::get('/por-reparacion/{idReparacion}',[ReparacionMultipleController::class, 'porReparacion']);
    Route::get('/estado/{estado}',             [ReparacionMultipleController::class, 'index']);
    Route::post('/{id}/comenzar',              [ReparacionMultipleController::class, 'comenzar']);
    Route::post('/{id}/terminar',              [ReparacionMultipleController::class, 'terminar']);
    Route::post('/{id}/esperar-pieza',         [ReparacionMultipleController::class, 'esperarPieza']);
});

// ============================================
// ESTADÍSTICAS
// ============================================
Route::prefix('estadisticas')->group(function () {
    Route::get('/dashboard',              [EstadisticaController::class, 'dashboard']);
    Route::get('/inventario',             [EstadisticaController::class, 'inventario']);
    Route::get('/inventario-basico',      [EstadisticaController::class, 'inventarioBasico']);
    Route::get('/top-tecnicos',           [EstadisticaController::class, 'topTecnicos']);
    Route::get('/top-recepcionistas', [EstadisticaController::class, 'topRecepcionistas']);
    Route::get('/tecnicos',               [EstadisticaController::class, 'topTecnicos']);       // alias
    Route::get('/piezas-mas-usadas',      [EstadisticaController::class, 'piezasMasUsadas']);
    Route::get('/piezas',                 [EstadisticaController::class, 'piezasMasUsadas']);   // alias
     Route::get('/top-marcas',             [EstadisticaController::class, 'topMarcas']);
    Route::get('/ingresos-mensuales',     [EstadisticaController::class, 'ingresosMensuales']);
    Route::get('/reparaciones-por-estado',[EstadisticaController::class, 'reparacionesPorEstado']);
    Route::get('/tiempos-por-categoria',  [EstadisticaController::class, 'tiemposPorCategoria']);
});

// ============================================
// REPORTES
// ============================================
Route::prefix('reportes')->group(function () {
    Route::get('/dashboard',           [ReporteController::class, 'dashboard']);
    Route::get('/inventario',          [ReporteController::class, 'inventario']);
    Route::get('/comparativa-mensual', [ReporteController::class, 'comparativaMensual']);
    Route::post('/piezas',             [ReporteController::class, 'reportePiezas']);
    Route::post('/ingresos',           [ReporteController::class, 'ingresosPorPeriodo']);
    Route::post('/tecnico',            [ReporteController::class, 'reportePorTecnico']);
});
Route::get('/test-email', function () {
    Mail::raw('hola mamasita rika k ase :)', function($message) {
        $message->to('micaelalpez21@gmail.com')  // ← Cambia por tu correo real
                ->subject('Prueba de  embarazo postivo ');
    });
    
    return 'Correo enviado! Revisa tu bandeja de entrada (y SPAM)';
});
Route::prefix('presupuestos')->group(function () {
    
    // Rutas principales
    Route::get('/', [PresupuestoController::class, 'index']);          // Listar
    Route::post('/', [PresupuestoController::class, 'store']);         // Crear
    Route::get('/estadisticas/resumen', [PresupuestoController::class, 'estadisticas']); // Estadísticas
    Route::get('/tecnicos/pendientes', [PresupuestoController::class, 'presupuestosPendientesPorTecnico']);
     // Nuevas rutas
    Route::post('/{id}/calcular', [PresupuestoController::class, 'calcular']); // ← Calcular presupuesto
    Route::post('/{id}/reabrir', [PresupuestoController::class, 'reabrir']); // ← Reabrir (admin)
    Route::get('/{id}', [PresupuestoController::class, 'show']);       // Ver uno
    Route::put('/{id}', [PresupuestoController::class, 'update']);     // Actualizar
    Route::patch('/{id}', [PresupuestoController::class, 'update']);   // Actualizar parcial
    Route::delete('/{id}', [PresupuestoController::class, 'destroy']); // Eliminar
    
    // Aprobar/Rechazar
    Route::post('/{id}/aprobar', [PresupuestoController::class, 'aprobar']);
});

// Rutas para PresupuestoDetalle
Route::prefix('presupuestos-detalles')->group(function () {
    
    // Rutas principales
    Route::get('/', [PresupuestoDetalleController::class, 'index']);           // Listar
    Route::post('/', [PresupuestoDetalleController::class, 'store']);          // Crear
    Route::get('/estadisticas/resumen', [PresupuestoDetalleController::class, 'estadisticas']); // Estadísticas
    Route::get('/{id}', [PresupuestoDetalleController::class, 'show']);        // Ver uno
    Route::put('/{id}', [PresupuestoDetalleController::class, 'update']);      // Actualizar
    Route::patch('/{id}', [PresupuestoDetalleController::class, 'update']);    // Actualizar parcial
    Route::delete('/{id}', [PresupuestoDetalleController::class, 'destroy']);  // Eliminar
    
    // Acciones especiales
    Route::patch('/{id}/aprobar', [PresupuestoDetalleController::class, 'toggleAprobado']); // Aprobar/Desaprobar
    Route::post('/copiar', [PresupuestoDetalleController::class, 'copiarDetalles']);        // Copiar detalles
});

use App\Http\Controllers\DiagnosticoPiezaController;

// DiagnosticoPieza routes

Route::prefix('diagnostico-piezas')->group(function () {
    // CRUD Básico
    Route::get('/', [DiagnosticoPiezaController::class, 'index']);
    Route::post('/', [DiagnosticoPiezaController::class, 'store']);
    Route::get('/{id}', [DiagnosticoPiezaController::class, 'show']);
    Route::put('/{id}', [DiagnosticoPiezaController::class, 'update']);
    Route::delete('/{id}', [DiagnosticoPiezaController::class, 'destroy']);
    
    // Rutas por diagnóstico y pieza
    Route::get('/diagnostico/{id_diagnostico}', [DiagnosticoPiezaController::class, 'indexByDiagnostico']);
    Route::get('/pieza/{id_pieza}', [DiagnosticoPiezaController::class, 'indexByPieza']);
    
    // ✅ Rutas de estado (SIN DUPLICAR)
    Route::patch('/{id}/status', [DiagnosticoPiezaController::class, 'updateStatus']);
    Route::patch('/{id}/approve', [DiagnosticoPiezaController::class, 'approve']);
    Route::patch('/{id}/reject', [DiagnosticoPiezaController::class, 'reject']);
    
    // Ruta para calcular costo total
    Route::get('/diagnostico/{id_diagnostico}/total-cost', [DiagnosticoPiezaController::class, 'getTotalCostByDiagnostico']);
});
//--------------------------------garantias--------------------------------
Route::post('/reclamos-garantia/{id}/registrar-ingreso', [ReclamoGarantiaController::class, 'registrarIngreso']);
Route::get('/reclamos-garantia/cliente/{email}/aprobados', [ReclamoGarantiaController::class, 'reclamosAprobadosPorCliente']);
// ============================================
// GARANTÍAS (PROTEGIDAS CON JWT)
// ============================================
Route::middleware([JwtClienteMiddleware::class])->group(function () {
    
    // Garantías
    Route::prefix('garantias')->group(function () {
        Route::get('/', [GarantiaController::class, 'index']);
        Route::post('/', [GarantiaController::class, 'store']);
        Route::get('/activas', [GarantiaController::class, 'activas']);
        Route::get('/vencidas', [GarantiaController::class, 'vencidas']);
        Route::get('/resumen', [GarantiaController::class, 'resumen']);
        Route::get('/cliente/{idCliente}', [GarantiaController::class, 'porCliente']);
        Route::get('/dispositivo/{idDispositivo}', [GarantiaController::class, 'porDispositivo']);
        Route::get('/reparacion/{idReparacion}/verificar', [GarantiaController::class, 'verificarPorReparacion']);
        Route::get('/{id}', [GarantiaController::class, 'show']);
        Route::put('/{id}', [GarantiaController::class, 'update']);
        Route::delete('/{id}', [GarantiaController::class, 'destroy']);
        Route::post('/{id}/regenerar-piezas', [GarantiaController::class, 'regenerarPiezas']);
    });
    
    // Garantías por pieza
    Route::prefix('garantias-piezas')->group(function () {
        Route::get('/', [GarantiaPiezaController::class, 'index']);
        Route::post('/', [GarantiaPiezaController::class, 'store']);
        Route::get('/activas', [GarantiaPiezaController::class, 'activas']);
        Route::get('/vencidas', [GarantiaPiezaController::class, 'vencidas']);
        Route::get('/resumen', [GarantiaPiezaController::class, 'resumen']);
        Route::get('/por-vencer', [GarantiaPiezaController::class, 'porVencer']);
        Route::get('/garantia/{idGarantia}', [GarantiaPiezaController::class, 'porGarantia']);
        Route::get('/categoria/{idCategoria}', [GarantiaPiezaController::class, 'porCategoria']);
        Route::get('/pieza/{idPieza}', [GarantiaPiezaController::class, 'porPieza']);
        Route::get('/pieza/{idPieza}/verificar', [GarantiaPiezaController::class, 'verificarPieza']);
        Route::get('/{id}', [GarantiaPiezaController::class, 'show']);
        Route::put('/{id}', [GarantiaPiezaController::class, 'update']);
        Route::delete('/{id}', [GarantiaPiezaController::class, 'destroy']);
    });
     Route::prefix('reclamos-garantia')->group(function () {
        Route::get('/mis-reclamos', [ReclamoGarantiaController::class, 'misReclamos']);
        Route::get('/{id}', [ReclamoGarantiaController::class, 'show']);
        Route::put('/cliente/{id}', [ReclamoGarantiaController::class, 'updateByCliente']);

    });
      Route::prefix('garantias')->group(function () {
        Route::post('/{id}/reclamar', [ReclamoGarantiaController::class, 'store']);
    });


});
// ============================================
//recuperacioan de contraseña
// ============================================

Route::post('/cliente/forgot-password', [ClientePasswordController::class, 'forgotPassword']);
Route::post('/cliente/reset-password', [ClientePasswordController::class, 'resetPassword']);

// ============================================
// RUTAS PARA ADMIN/TÉCNICO
// ============================================
Route::middleware([JwtAdminTecnicoMiddleware::class])->group(function () {
    
    Route::prefix('admin/reclamos-garantia')->group(function () {
        Route::get('/mis-reclamos', [ReclamoGarantiaController::class, 'misReclamosAsignados']);
        Route::get('/garantia/{idGarantia}', [ReclamoGarantiaController::class, 'porGarantia']);
        Route::get('/', [ReclamoGarantiaController::class, 'index']);
        Route::get('/{id}', [ReclamoGarantiaController::class, 'show']);
        Route::put('/{id}', [ReclamoGarantiaController::class, 'update']);
        Route::delete('/{id}', [ReclamoGarantiaController::class, 'destroy']);
    });
    Route::get('/admin/garantias/evolucion-mensual', [GarantiaController::class, 'evolucionMensual']);

    Route::get('/admin/garantias/por-vencer', [GarantiaController::class, 'porVencer']);
       Route::get('/admin/garantias/control-calidad', [GarantiaController::class, 'controlCalidad']); 
    Route::get('/admin/garantias', [GarantiaController::class, 'index']);
    Route::get('/admin/garantias/{id}', [GarantiaController::class, 'show']);
    Route::put('/admin/garantias/{id}', [GarantiaController::class, 'update']);
});// ============================================
// TESTIMONIOS
// ============================================

// Rutas públicas (sin autenticación)
Route::prefix('testimonios')->group(function () {
    // Obtener testimonios aprobados (para mostrar en la web pública)
    Route::get('/', [TestimonioController::class, 'index']);
     Route::get('/estadisticas', [TestimonioController::class, 'estadisticas']);
});


// Rutas protegidas para CLIENTES autenticados
Route::middleware([JwtClienteMiddleware::class])->prefix('testimonios')->group(function () {
    // Verificar si hay reparaciones pendientes de calificar
    Route::get('/verificar-pendientes', [TestimonioController::class, 'verificarPendientes']);
    
    // Guardar un nuevo testimonio
    Route::post('/', [TestimonioController::class, 'store']);
    
    // Guardar "no volver a preguntar"
    Route::post('/skip', [TestimonioController::class, 'setSkip']);
});

// Rutas protegidas para ADMINISTRADORES/TÉCNICOS (moderación)
Route::middleware([JwtAdminTecnicoMiddleware::class])->prefix('admin/testimonios')->group(function () {
    // Listar todos los testimonios (para moderar)
    Route::get('/', [TestimonioController::class, 'indexAdmin']);
    
    // Cambiar estado (aprobar/rechazar)
    Route::put('/{id}/estado', [TestimonioController::class, 'updateEstado']);
    
    // Eliminar testimonio
    Route::delete('/{id}', [TestimonioController::class, 'destroy']);
});

Route::prefix('reportes')->group(function () {
    Route::get('/estadisticas-globales', [ReporteGlobalController::class, 'estadisticasGlobales']);
 Route::get('/ingresos-mensuales', [ReporteGlobalController::class, 'ingresosMensuales']);
   Route::get('/reparaciones-mensuales', [ReporteGlobalController::class, 'reparacionesMensuales']);
    Route::post('/comparar-meses', [ReporteGlobalController::class, 'compararMeses']);
    Route::post('/comparar-anios', [ReporteGlobalController::class, 'compararAnios']);
    Route::post('/comparar-meses-resumen', [ReporteGlobalController::class, 'compararMesesResumen']);
    Route::post('/top-marcas-mensual', [ReporteGlobalController::class, 'topMarcasMensual']);
});

