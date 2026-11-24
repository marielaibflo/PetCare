<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\RegistroVeterinariaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\Propietario;
use App\Http\Controllers\VerificacionController;
use App\Http\Controllers\Veterinaria;
use Illuminate\Support\Facades\Route;

/*
| Capa de presentación → rutas. Cada grupo está protegido por el middleware «rol»,
| de modo que un perfil no puede abrir las pantallas de otro (error 403).
*/

// Acceso
Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'welcome'])->name('welcome');
    Route::get('/login/{rol}', [AuthController::class, 'showLogin'])->whereIn('rol', ['propietario', 'veterinaria', 'administrador'])->name('login');
    Route::post('/login/{rol}', [AuthController::class, 'login'])->whereIn('rol', ['propietario', 'veterinaria', 'administrador'])->middleware('throttle:10,1');
    // Iniciar sesión con la cuenta de Google
    Route::get('/login/{rol}/google', [GoogleController::class, 'redirigir'])->whereIn('rol', ['propietario', 'veterinaria', 'administrador'])->middleware('throttle:10,1')->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'volver'])->middleware('throttle:10,1')->name('google.callback');
    Route::get('/registro', [AuthController::class, 'showRegistro'])->name('registro');
    Route::post('/registro', [AuthController::class, 'registro']);
    // Registro en línea de clínicas veterinarias
    Route::get('/afiliar-veterinaria', [RegistroVeterinariaController::class, 'show'])->name('registro.veterinaria');
    Route::post('/afiliar-veterinaria', [RegistroVeterinariaController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/afiliar-veterinaria/confirmar-correo', [RegistroVeterinariaController::class, 'correo'])->name('registro.veterinaria.correo');
    Route::post('/afiliar-veterinaria/confirmar-correo', [RegistroVeterinariaController::class, 'confirmarCodigo'])->middleware('throttle:10,1')->name('registro.veterinaria.codigo');
    Route::post('/afiliar-veterinaria/confirmar-correo/reenviar', [RegistroVeterinariaController::class, 'reenviarCodigo'])->middleware('throttle:3,1')->name('registro.veterinaria.reenviar');
    Route::post('/registro/reenviar-enlace', [AuthController::class, 'reenviarEnlace'])->middleware('throttle:3,1')->name('registro.reenviar');
});
Route::get('/terminos', [RegistroVeterinariaController::class, 'terminos'])->name('terminos');
// Botón «Confirmar mi correo» del correo: funciona aunque se abra en otro navegador (enlace firmado, 24 h).
Route::get('/confirmar-correo/{usuario}/{hash}', [VerificacionController::class, 'enlace'])->middleware(['signed:relative', 'throttle:20,1'])->name('verificacion.enlace');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('/perfil/password', [PerfilController::class, 'password'])->name('perfil.password');
    Route::post('/perfil/correo', [PerfilController::class, 'confirmarCorreo'])->middleware('throttle:10,1')->name('perfil.correo');
    Route::delete('/perfil/correo', [PerfilController::class, 'cancelarCorreo'])->name('perfil.correo.cancelar');

    // Verificación de correo y celular (WhatsApp) con códigos de 6 dígitos
    Route::get('/verificar', [VerificacionController::class, 'show'])->name('verificacion.show');
    Route::post('/verificar/{canal}', [VerificacionController::class, 'verificar'])->whereIn('canal', ['email', 'whatsapp'])->middleware('throttle:10,1')->name('verificacion.verificar');
    Route::post('/verificar/{canal}/reenviar', [VerificacionController::class, 'reenviar'])->whereIn('canal', ['email', 'whatsapp'])->middleware('throttle:3,1')->name('verificacion.reenviar');
    Route::put('/verificar/telefono', [VerificacionController::class, 'cambiarTelefono'])->middleware('throttle:3,1')->name('verificacion.telefono');

    // Página de cada veterinaria (dueños, la propia clínica y el administrador)
    Route::get('/veterinarias/{veterinaria}', [Propietario\VeterinariaController::class, 'show'])->name('veterinarias.show');

    // Archivos privados (cada método revisa quién puede verlos)
    Route::get('/archivos/usuarios/{usuario}/foto', [ArchivoController::class, 'usuario'])->name('archivos.usuario');
    Route::get('/archivos/mascotas/{mascota}/foto', [ArchivoController::class, 'mascota'])->name('archivos.mascota');
    Route::get('/archivos/etiquetas/{atencion}', [ArchivoController::class, 'etiqueta'])->name('archivos.etiqueta');
    Route::get('/archivos/comprobantes/{pago}', [ArchivoController::class, 'comprobante'])->name('archivos.comprobante');
    Route::get('/archivos/certificados/{atencion}', [ArchivoController::class, 'certificado'])->name('archivos.certificado');
    Route::get('/archivos/veterinarias/{veterinaria}/logo', [ArchivoController::class, 'logo'])->name('archivos.logo');
    Route::get('/archivos/veterinarias/fotos/{foto}', [ArchivoController::class, 'fotoVeterinaria'])->name('archivos.foto-veterinaria');
    Route::get('/archivos/productos/{producto}/foto', [ArchivoController::class, 'fotoProducto'])->name('archivos.producto');
    Route::get('/archivos/documentos/{veterinaria}', [ArchivoController::class, 'documento'])->name('archivos.documento');
    Route::get('/archivos/qr-pago', [ArchivoController::class, 'qr'])->name('archivos.qr');
    Route::get('/archivos/veterinarias/{veterinaria}/qr', [ArchivoController::class, 'qrVeterinaria'])->name('archivos.qr-veterinaria');
});

// Propietario
Route::middleware(['auth', 'rol:propietario', 'verificado'])->prefix('propietario')->name('propietario.')->group(function () {
    Route::get('/', Propietario\InicioController::class)->name('inicio');
    Route::get('/mascotas', [Propietario\MascotaController::class, 'index'])->name('mascotas.index');
    Route::post('/mascotas', [Propietario\MascotaController::class, 'store'])->name('mascotas.store');
    Route::get('/mascotas/{mascota}/{tab?}', [Propietario\MascotaController::class, 'show'])->whereIn('tab', ['info', 'vacunas', 'historial', 'plan', 'citas'])->name('mascotas.show');
    Route::put('/mascotas/{mascota}', [Propietario\MascotaController::class, 'update'])->name('mascotas.update');
    Route::delete('/mascotas/{mascota}', [Propietario\MascotaController::class, 'destroy'])->name('mascotas.destroy');
    Route::get('/mascotas/{mascota}/cartilla/imprimir', [Propietario\MascotaController::class, 'cartilla'])->name('mascotas.cartilla');
    Route::post('/mascotas/{mascota}/cartilla/{atencion}/documento', [Propietario\MascotaController::class, 'adjuntarDocumento'])->name('mascotas.cartilla.documento');
    Route::delete('/mascotas/{mascota}/plan', [Propietario\PlanController::class, 'cancelar'])->name('planes.cancelar');
    Route::get('/mascotas/{mascota}/contrato.pdf', [Propietario\PlanController::class, 'contrato'])->name('planes.contrato');
    Route::get('/planes', [Propietario\PlanController::class, 'index'])->name('planes.index');
    // Tienda: vitrina, carrito, pago por clínica y pedidos
    Route::get('/tienda', [Propietario\TiendaController::class, 'index'])->name('tienda.index');
    Route::get('/tienda/carrito', [Propietario\TiendaController::class, 'carrito'])->name('tienda.carrito');
    Route::post('/tienda/carrito/{producto}', [Propietario\TiendaController::class, 'agregar'])->middleware('throttle:60,1')->name('tienda.agregar');
    Route::patch('/tienda/carrito/{producto}', [Propietario\TiendaController::class, 'cambiar'])->name('tienda.cambiar');
    Route::get('/tienda/pagar/{veterinaria}', [Propietario\TiendaController::class, 'checkout'])->name('tienda.checkout');
    Route::post('/tienda/pagar/{veterinaria}', [Propietario\TiendaController::class, 'comprar'])->middleware('throttle:10,1')->name('tienda.comprar');
    Route::get('/tienda/pedidos', [Propietario\TiendaController::class, 'pedidos'])->name('tienda.pedidos');
    Route::delete('/tienda/pedidos/{pedido}', [Propietario\TiendaController::class, 'cancelar'])->name('tienda.cancelar');
    Route::get('/planes/{plan}/solicitar', [Propietario\PlanController::class, 'solicitar'])->name('planes.solicitar');
    Route::post('/planes/{plan}/solicitar', [Propietario\PlanController::class, 'pagar'])->middleware('throttle:10,1')->name('planes.pagar');
    Route::get('/pagos', [Propietario\PlanController::class, 'pagos'])->name('pagos.index');
    Route::get('/pagos/{pago}/comprobante.pdf', [Propietario\PlanController::class, 'comprobantePdf'])->name('pagos.pdf');
    Route::get('/veterinarias', [Propietario\VeterinariaController::class, 'index'])->name('veterinarias.index');
    Route::get('/citas', [Propietario\CitaController::class, 'index'])->name('citas.index');
    Route::post('/citas', [Propietario\CitaController::class, 'store'])->name('citas.store');
    Route::get('/citas/estimar', [Propietario\CitaController::class, 'estimar'])->name('citas.estimar');
    Route::post('/citas/{cita}/calificar', [Propietario\VeterinariaController::class, 'calificar'])->name('citas.calificar');
    Route::patch('/citas/{cita}/cancelar', [Propietario\CitaController::class, 'cancelar'])->name('citas.cancelar');
    Route::get('/citas/{cita}/pago', [Propietario\CitaController::class, 'pago'])->name('citas.pago');
    Route::post('/citas/{cita}/pago', [Propietario\CitaController::class, 'pagar'])->name('citas.pagar');
    Route::get('/perfil', [PerfilController::class, 'show'])->name('perfil');
});

// Veterinaria
Route::middleware(['auth', 'rol:veterinaria', 'verificado'])->prefix('veterinaria')->name('veterinaria.')->group(function () {
    Route::get('/', Veterinaria\InicioController::class)->name('inicio');
    Route::get('/agenda', [Veterinaria\AgendaController::class, 'index'])->name('agenda');
    Route::patch('/citas/{cita}/confirmar', [Veterinaria\AgendaController::class, 'confirmar'])->name('citas.confirmar');
    Route::patch('/citas/{cita}/rechazar', [Veterinaria\AgendaController::class, 'rechazar'])->name('citas.rechazar');
    Route::patch('/citas/{cita}/pendiente', [Veterinaria\AgendaController::class, 'pendiente'])->name('citas.pendiente');
    Route::patch('/citas/{cita}/atendida', [Veterinaria\AgendaController::class, 'atendida'])->name('citas.atendida');
    Route::patch('/citas/{cita}/cancelar', [Veterinaria\AgendaController::class, 'cancelar'])->name('citas.cancelar');
    Route::patch('/pagos/{pago}/aprobar', [Veterinaria\AgendaController::class, 'aprobarPago'])->name('citas.pago.aprobar');
    Route::patch('/pagos/{pago}/rechazar', [Veterinaria\AgendaController::class, 'rechazarPago'])->name('citas.pago.rechazar');
    Route::get('/atenciones/nueva', [Veterinaria\AtencionController::class, 'create'])->name('atenciones.create');
    Route::post('/atenciones', [Veterinaria\AtencionController::class, 'store'])->name('atenciones.store');
    Route::get('/atenciones/cobertura', [Veterinaria\AtencionController::class, 'cobertura'])->name('atenciones.cobertura');
    Route::get('/historial/{mascota}', [Veterinaria\AtencionController::class, 'historial'])->name('historial.parcial');
    Route::get('/pacientes', [Veterinaria\PacienteController::class, 'index'])->name('pacientes.index');
    Route::get('/pacientes/{mascota}', [Veterinaria\PacienteController::class, 'show'])->name('pacientes.show');
    Route::get('/pacientes/{mascota}/cartilla', [Veterinaria\PacienteController::class, 'cartilla'])->name('pacientes.cartilla');
    Route::get('/clinica', [Veterinaria\ClinicaController::class, 'edit'])->name('clinica');
    Route::put('/clinica', [Veterinaria\ClinicaController::class, 'update'])->name('clinica.update');
    Route::put('/clinica/tarifario', [Veterinaria\ClinicaController::class, 'tarifario'])->name('clinica.tarifario');
    Route::post('/clinica/fotos', [Veterinaria\ClinicaController::class, 'agregarFoto'])->name('clinica.fotos');
    Route::delete('/clinica/fotos/{foto}', [Veterinaria\ClinicaController::class, 'quitarFoto'])->name('clinica.fotos.destroy');
    Route::get('/productos', [Veterinaria\ProductoController::class, 'index'])->name('productos.index');
    Route::post('/productos', [Veterinaria\ProductoController::class, 'store'])->name('productos.store');
    Route::put('/productos/{producto}', [Veterinaria\ProductoController::class, 'update'])->name('productos.update');
    Route::patch('/productos/{producto}/estado', [Veterinaria\ProductoController::class, 'toggle'])->name('productos.toggle');
    Route::get('/pedidos', [Veterinaria\PedidoController::class, 'index'])->name('pedidos');
    Route::put('/pedidos/envio', [Veterinaria\PedidoController::class, 'envio'])->name('pedidos.envio');
    Route::post('/pedidos/{pedido}/pago/aprobar', [Veterinaria\PedidoController::class, 'aprobarPago'])->name('pedidos.pago.aprobar');
    Route::post('/pedidos/{pedido}/pago/rechazar', [Veterinaria\PedidoController::class, 'rechazarPago'])->name('pedidos.pago.rechazar');
    Route::post('/pedidos/{pedido}/avanzar', [Veterinaria\PedidoController::class, 'avanzar'])->name('pedidos.avanzar');
    Route::post('/pedidos/{pedido}/cancelar', [Veterinaria\PedidoController::class, 'cancelar'])->name('pedidos.cancelar');
    Route::get('/suscripcion', [Veterinaria\SuscripcionController::class, 'show'])->name('suscripcion');
    Route::post('/suscripcion', [Veterinaria\SuscripcionController::class, 'pagar'])->middleware('throttle:10,1')->name('suscripcion.pagar');
    Route::put('/suscripcion/cobro', [Veterinaria\SuscripcionController::class, 'cobro'])->middleware('throttle:10,1')->name('suscripcion.cobro');
    Route::post('/suscripcion/cancelar', [Veterinaria\SuscripcionController::class, 'cancelar'])->name('suscripcion.cancelar');
    Route::post('/suscripcion/reactivar', [Veterinaria\SuscripcionController::class, 'reactivar'])->name('suscripcion.reactivar');
    Route::delete('/cuenta', [Veterinaria\SuscripcionController::class, 'eliminarCuenta'])->middleware('throttle:5,1')->name('cuenta.eliminar');
    Route::get('/suscripcion/{pago}/comprobante.pdf', [Veterinaria\SuscripcionController::class, 'comprobantePdf'])->name('suscripcion.pdf');
});

// Administrador
Route::middleware(['auth', 'rol:administrador'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('/propietarios', [Admin\PropietarioController::class, 'index'])->name('propietarios.index');
    Route::post('/propietarios', [Admin\PropietarioController::class, 'store'])->name('propietarios.store');
    Route::put('/propietarios/{usuario}', [Admin\PropietarioController::class, 'update'])->name('propietarios.update');
    Route::patch('/propietarios/{usuario}/estado', [Admin\PropietarioController::class, 'toggle'])->name('propietarios.toggle');
    Route::delete('/propietarios/{usuario}', [Admin\PropietarioController::class, 'destroy'])->name('propietarios.destroy');
    Route::get('/mascotas', [Admin\MascotaController::class, 'index'])->name('mascotas.index');
    Route::get('/veterinarias', [Admin\VeterinariaController::class, 'index'])->name('veterinarias.index');
    Route::post('/veterinarias', [Admin\VeterinariaController::class, 'store'])->name('veterinarias.store');
    Route::put('/veterinarias/{veterinaria}', [Admin\VeterinariaController::class, 'update'])->name('veterinarias.update');
    Route::patch('/veterinarias/{veterinaria}/estado', [Admin\VeterinariaController::class, 'toggle'])->name('veterinarias.toggle');
    Route::patch('/veterinarias/{veterinaria}/aprobar', [Admin\VeterinariaController::class, 'aprobar'])->name('veterinarias.aprobar');
    Route::patch('/veterinarias/{veterinaria}/rechazar', [Admin\VeterinariaController::class, 'rechazar'])->name('veterinarias.rechazar');
    Route::get('/pagos', [Admin\PagoController::class, 'index'])->name('pagos.index');
    Route::patch('/pagos/{pago}/aprobar', [Admin\PagoController::class, 'aprobar'])->name('pagos.aprobar');
    Route::patch('/pagos/{pago}/rechazar', [Admin\PagoController::class, 'rechazar'])->name('pagos.rechazar');
    Route::post('/pagos/{pago}/reenviar', [Admin\PagoController::class, 'reenviar'])->name('pagos.reenviar');
    Route::get('/pagos/{pago}/comprobante.pdf', [Admin\PagoController::class, 'comprobantePdf'])->name('pagos.pdf');
    Route::get('/planes', [Admin\PlanController::class, 'index'])->name('planes.index');
    Route::get('/planes/{plan}/afiliados.xlsx', [Admin\PlanController::class, 'afiliadosExcel'])->name('planes.afiliados');
    Route::post('/planes', [Admin\PlanController::class, 'store'])->name('planes.store');
    Route::put('/planes/{plan}', [Admin\PlanController::class, 'update'])->name('planes.update');
    Route::patch('/planes/{plan}/estado', [Admin\PlanController::class, 'toggle'])->name('planes.toggle');
    Route::delete('/planes/{plan}', [Admin\PlanController::class, 'destroy'])->name('planes.destroy');
    Route::get('/servicios', Admin\ServicioController::class)->name('servicios');
    Route::get('/reportes', [Admin\ReporteController::class, 'index'])->name('reportes');
    Route::get('/reportes/excel', [Admin\ReporteController::class, 'excel'])->name('reportes.excel');
    Route::get('/configuracion', [Admin\ConfiguracionController::class, 'index'])->name('configuracion');
    Route::get('/configuracion/respaldo', [Admin\ConfiguracionController::class, 'respaldo'])->name('respaldo');
    Route::put('/configuracion/pago', [Admin\ConfiguracionController::class, 'pago'])->name('configuracion.pago');
    Route::put('/configuracion/redes', [Admin\ConfiguracionController::class, 'redes'])->name('configuracion.redes');
});
