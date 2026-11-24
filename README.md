# PetCare · Plataforma web de asistencia veterinaria

Proyecto de Sistema I · Paralelo “C” · Gestión II - 2026
Estudiante: Mariela Neyza Ibañez Flores · Docente: Ing. Torrico Camacho Pialy

PetCare conecta a **propietarios de mascotas**, **veterinarias afiliadas** y al **administrador** de la plataforma.
Es una aplicación web adaptable (computadora y celular) con un único diseño para los tres roles, construida con
**Laravel 13 + Blade + SQLite**. No necesita Node.js ni compilar nada del frontend: los estilos y scripts están en `public/`.

---

## 0. Novedades (noviembre 2026)

| Módulo | Qué cambió |
|---|---|
| **Registro seguro** | Correo validado (formato, dominio y errores de tipeo), celular con **código de país** validado según el país, **contraseña segura** (8+ caracteres con mayúscula, minúscula, número y símbolo) y **verificación con códigos** por correo y WhatsApp. Sin verificar, el propietario no entra al sistema. |
| **Citas + WhatsApp** | La cita llega a la clínica, que puede **aceptar, rechazar (con motivo), devolver a pendiente o marcar como atendida**. El dueño y la clínica reciben un **aviso por WhatsApp** en cada paso. Botón de WhatsApp para escribirse. |
| **Tarifario y medicamentos** | Cada veterinaria registra **el precio de sus servicios** y su **catálogo de medicamentos/vacunas/insumos**, marcando qué productos tienen descuento y con qué planes. |
| **Planes por porcentaje** | **Básico 40 % (Bs 35)**, **Estándar 60 % (Bs 79)** y **Premium 90 % (Bs 149)**, con tope mensual y anual, espera para cirugías y adicional **Cobertura Grave** (cáncer y crónicos, 50 %, +Bs 30). Al atender, el sistema calcula **cuánto paga PetCare y cuánto el dueño**. |
| **Cartilla digital** | Diseño de **libreta PetCare** (hoja de datos + tablas de vacunas y desparasitación). Cada registro muestra el **sello/logo de la clínica**, el veterinario con su **N.º de matrícula profesional**, la fecha, el lote y la próxima dosis. **Solo la veterinaria registra** vacunas (con matrícula del veterinario y N.º de registro de la clínica); el dueño puede **ver detalles** y **adjuntar la foto/escaneo** del certificado físico. Se **imprime o guarda en PDF**. |
| **Recordatorios** | **7 días antes** de cada próxima vacuna o desparasitación se envía **correo + WhatsApp** al dueño (una sola vez por dosis) y aparece el aviso en su perfil. Se ejecuta solo cada día a las 08:00 con `php artisan schedule:work`. |
| **Compra de planes** | El dueño llena sus datos y con **«Concluir pago por WhatsApp»** se abre el WhatsApp de PetCare (591 78539749) con el mensaje listo. El comprobante es **opcional**. Con **tarjeta (simulación)** se abre WhatsApp con «ya pagué, espero su confirmación». El administrador **verifica el pago**, el plan se activa y se envía el **comprobante en PDF por correo**. |
| **Citas con monto y urgencia** | Casilla **«Es urgente»** (la clínica la ve primero, en rojo). Se muestran los planes pero solo se puede usar el **plan activo** de la mascota. Cálculo en vivo de **cuánto paga el dueño**. Al confirmar se abre el **WhatsApp de la clínica** para pagar por QR (número de muestra 591 68186482 para todas). |
| **Página de cada veterinaria** | Lista ordenada por **mejor calificadas / más solicitadas** con insignias. Página con **mapa de Google y «Cómo llegar»**, fotos de las instalaciones, servicios y precios, servicios especiales, formas de pago, promoción del mes, **catálogo con fotos** y **calificaciones** (el dueño califica de 1 a 5 estrellas cada cita atendida). |
| **Pagos con tarjeta o QR** | Planes, suscripciones y citas se pagan con **tarjeta (Visa, Mastercard, American Express · simulación)**, **QR con comprobante** o WhatsApp. El comprobante llega al **administrador** (planes y suscripciones) o a la **veterinaria** (citas). |
| **Términos y límites** | Antes de pagar se leen los **términos en una ventana flotante** y se aceptan al llegar al final. Cada plan cubre un **máximo de atenciones al año** (Básico 4 · Estándar 8 · Premium 12) además de sus topes en Bs; después la clínica cobra el precio completo. |
| **Excel y contabilidad** | El administrador descarga **un solo Excel con varias hojas** (resumen, estado de resultados, ganancias, libro diario, propietarios, mascotas, veterinarias, citas, historial clínico, vacunas, pagos, siniestralidad, liquidación y servicios) del **día, mes o año** elegido. Cada lista se puede **imprimir**. |
| **Validaciones** | Cada casilla muestra **su propio mensaje** (qué falta o qué formato necesita) mientras se escribe y al enviar; los obligatorios llevan asterisco. |
| **Interfaz** | Ventanas flotantes que se **mueven y agrandan**; la **cartilla** se abre como **libreta física** (tapa y páginas que se pasan, zoom e impresión). |
| **Veterinarias** | **Registro en línea** con documento, **suscripción** (Inicial Bs 120, Profesional Bs 250, Clínica Plus Bs 400, primer mes gratis), aprobación del administrador y renovación por QR. |
| **Reportes** | **Siniestralidad por plan** (cobrado vs. coberturas) y **liquidación mensual** a cada veterinaria. Historial de mensajes de WhatsApp en Configuración. |

**Después de copiar la nueva versión ejecuta una vez:**

```powershell
composer run reset-db
```

(crea las tablas nuevas y carga los datos de demostración con los planes nuevos; borra los datos de prueba anteriores).
Si prefieres conservar tus datos: `php artisan migrate` (los planes antiguos no tendrán porcentajes hasta que los edites en Administrador → Planes).

### Activar los envíos reales (opcional)
En modo demostración los códigos se muestran en pantalla, los WhatsApp quedan en **Administrador → Configuración → Mensajes de WhatsApp** y los correos en `storage/logs/laravel.log`. Para enviarlos de verdad, agrega en `.env`:

```
# WhatsApp Business (Meta Cloud API): https://developers.facebook.com/docs/whatsapp/cloud-api
WHATSAPP_DRIVER=meta
WHATSAPP_TOKEN=tu_token
WHATSAPP_PHONE_ID=id_del_numero
WHATSAPP_TEMPLATE_CODIGO=nombre_de_la_plantilla_de_autenticacion

# Gmail (usa una «contraseña de aplicación» de tu cuenta de Google)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tucorreo@gmail.com
MAIL_PASSWORD=contraseña_de_aplicacion
MAIL_FROM_ADDRESS=tucorreo@gmail.com

PETCARE_DEMO=false
```

Luego `php artisan config:clear`. El QR de cobro y los datos del banco se cambian desde **Administrador → Configuración** (los QR de los bancos tienen fecha de vencimiento).

---

## 1. Funcionalidades

**Propietario**
- Registro de cuenta e inicio de sesión.
- Mascotas: registrar, editar, eliminar y ver ficha (datos, vacunas, historial clínico, plan y citas).
- Carnet de vacunas con próxima dosis y aviso de vencidas / por vencer.
- Planes de asistencia: contratar (una mascota solo puede tener **un plan activo**), cambiar, renovar (solo cuando faltan 30 días o menos) y cancelar.
- Veterinarias afiliadas: buscar por nombre, zona o servicio y filtrar por zona.
- Citas: agendar, ver y cancelar. Reglas: no fechas pasadas, no domingos, sin horarios ocupados, una cita por mascota por día y solo servicios que la clínica ofrece.
- Notificaciones (campana): vacunas próximas o vencidas, planes por vencer y citas próximas.
- Perfil: editar datos y cambiar contraseña.

**Veterinaria**
- Panel del día: citas de hoy y solicitudes por confirmar.
- Agenda con filtros por estado; confirmar o cancelar citas.
- Registrar atención (consulta, vacuna, desparasitación, tratamiento, control de pulgas, urgencia) con diagnóstico, peso, temperatura, producto y **próxima dosis**. Indica si el plan de la mascota cubre el servicio y marca la cita como atendida.
- Pacientes: listado e historial clínico completo de cada mascota.
- Mi clínica: editar datos, horario y servicios ofrecidos.

**Administrador**
- Panel con indicadores.
- Propietarios: crear, editar, activar/desactivar (una cuenta desactivada no puede ingresar) y eliminar.
- Mascotas registradas (consulta y búsqueda).
- Veterinarias: afiliar (crea también la cuenta de acceso), editar y activar/desactivar.
- Planes: crear, editar, activar/desactivar y eliminar (no se elimina un plan con afiliados).
- Servicios y citas de toda la plataforma.
- Reportes con descarga CSV (planes, veterinarias y servicios) y respaldo de datos en JSON.

**Seguridad**: contraseñas cifradas (bcrypt), protección CSRF, rutas protegidas por rol (error 403), cada propietario solo ve sus mascotas (error 404 con mascotas ajenas) y cada veterinaria solo gestiona sus citas.

---

## 2. Requisitos (instalar una sola vez)

| Programa | Versión | Para qué |
|---|---|---|
| PHP | 8.3 o superior (recomendado 8.4) con extensiones `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`, `zip` | Ejecutar Laravel |
| Composer | 2.x | Descargar las dependencias |
| Visual Studio Code | cualquiera | Editar y abrir la terminal |
| DB Browser for SQLite | opcional | Ver la base de datos |

**Forma más sencilla en Windows (recomendada):** instalar **Laravel Herd** desde <https://herd.laravel.com/windows>. Trae PHP y Composer ya configurados.

**Alternativa por comandos:** abre PowerShell **como administrador** y ejecuta (instalador oficial que indica la documentación de Laravel):

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.4'))
```

Cierra y vuelve a abrir VS Code después de instalar.

**Comprobar que todo está instalado** (en la terminal de VS Code):

```powershell
php -v
composer -V
php -m | Select-String sqlite
```

Debe mostrar PHP 8.3 o superior, Composer 2.x y las líneas `pdo_sqlite` y `sqlite3`.
Si falta `pdo_sqlite`, abre el `php.ini` (la ruta aparece con `php --ini`) y quita el `;` de las líneas `extension=pdo_sqlite` y `extension=sqlite3`.

---

## 3. Instalación (una sola vez)

1. Descomprime `PetCare.zip`, por ejemplo en `C:\Proyectos\`. Quedará la carpeta `C:\Proyectos\PetCare`.
2. En VS Code: **Archivo → Abrir carpeta…** y elige la carpeta `PetCare` (la que contiene `artisan` y `composer.json`).
3. Abre la terminal: **Terminal → Nuevo terminal** (es PowerShell).
4. Ejecuta:

```powershell
composer run setup
```

Ese comando hace todo lo necesario:
- instala las dependencias (`vendor/`),
- crea el archivo `.env` a partir de `.env.example`,
- genera la clave de la aplicación (`APP_KEY`),
- crea la base de datos `database/database.sqlite`,
- crea las tablas (migraciones) y carga los datos de demostración (seeders).

> La primera vez tarda algunos minutos porque descarga Laravel.

**Variables de entorno**: no necesitas cambiar nada. El archivo `.env` ya queda con SQLite, zona horaria `America/La_Paz` e idioma español.

---

## 4. Ejecución (cada vez que quieras usar la aplicación)

En la terminal de VS Code, dentro de la carpeta `PetCare`:

```powershell
php artisan serve
```

(o su equivalente `composer run dev`). Luego abre en el navegador:

**http://127.0.0.1:8000**

Para que se envíen los **recordatorios de vacunas** todos los días, abre **otra terminal** y deja corriendo:

```powershell
php artisan schedule:work
```

(para enviarlos al instante una vez: `php artisan petcare:recordatorios`).

Para **detener** la aplicación: haz clic en la terminal y presiona `Ctrl + C`.
Para **volver a iniciarla**: ejecuta otra vez `php artisan serve`.

### Ver desde el celular (opcional, misma red Wi-Fi)

```powershell
php artisan serve --host=0.0.0.0 --port=8000
ipconfig
```

En el celular abre `http://<tu IPv4>:8000` (ejemplo `http://192.168.1.10:8000`). Si no carga, permite PHP en el Firewall de Windows.

---

## 5. Cuentas de demostración

| Rol | Correo | Contraseña |
|---|---|---|
| Propietario | juan@correo.com (base limpia) · maria@correo.com (`composer run demo-db`) | 123456 |
| Propietario | carlos@correo.com / ana@correo.com | 123456 |
| Veterinaria (Veterinaria Patitas) | veterinaria@clinica.com | vet123 |
| Veterinaria (Centro Veterinario Vida) | vida@clinica.com | vet123 |
| Administrador | admin@petcare.com | admin123 |
| Veterinaria en revisión (San Roque) | sanroque@clinica.com | Clave#123 |

Las contraseñas de demostración son simples para facilitar la presentación; **las cuentas nuevas exigen contraseña segura**.
Para probar el pago con tarjeta usa el número de prueba `4242 4242 4242 4242` (no se cobra nada).

En la pantalla de inicio se elige el perfil y luego se ingresa con su cuenta. La cuenta `huellitas@clinica.com` está desactivada a propósito para probar el bloqueo.

Los datos de demostración se crean con fechas relativas al día de instalación (citas de hoy, vacunas próximas, un plan por vencer).

### Cómo comprobar las funcionalidades principales
1. **Propietario** (juan@correo.com): Mascotas → Agregar mascota → deja campos vacíos y guarda (verás los errores) → complétalos y guarda. Planes → Contratar para la nueva mascota. Veterinarias → elige una → Agendar cita. Citas → Cancelar. Revisa la campana de notificaciones.
2. **Veterinaria** (veterinaria@clinica.com): Inicio → Confirmar una solicitud → Atender una cita → registra la atención. Registra una **Vacuna** con próxima dosis y verifica que aparezca en el carnet del propietario.
3. **Administrador** (admin@petcare.com): crea un plan, afilia una veterinaria nueva e ingresa con su cuenta, desactiva un propietario e intenta ingresar con él, descarga un reporte CSV.
4. **Flujo completo nuevo:** regístrate como propietario (verás los códigos en pantalla) → agrega tu mascota con foto → solicita el Plan Estándar y paga con QR → como administrador, aprueba el pago en **Pagos** → agenda una cita → como veterinaria, acéptala y atiéndela con productos: verás cuánto cubre PetCare y cuánto paga el dueño.

### Volver a los datos iniciales

```powershell
composer run reset-db
```

(borra todo y carga de nuevo los datos de demostración).

---

## 6. Pruebas automáticas

```powershell
php artisan test
```

Ejecuta 66 pruebas (`tests/Feature`) sobre una base SQLite en memoria (no toca tus datos): autenticación y roles, registro seguro, mascotas, planes y cobertura, citas, vacunas y recordatorios, pagos, página de veterinarias, calificaciones, administración y reportes.

---

## 7. Ver la base de datos con DB Browser for SQLite

Abre DB Browser → **Abrir base de datos** → `PetCare\database\database.sqlite` → pestaña **Hojas de datos**.
Tablas principales: `users`, `veterinarias`, `planes`, `mascotas`, `afiliaciones`, `citas`, `atenciones`.
Si editas datos ahí, presiona **Escribir cambios** y cierra DB Browser antes de usar la aplicación.

---

## 8. Estructura del proyecto

```
app/
  Http/Controllers/        AuthController, PerfilController
    Propietario/           Inicio, Mascota, Plan, Veterinaria, Cita
    Veterinaria/           Inicio, Agenda, Atencion, Paciente, Clinica, Cita
    Admin/                 Dashboard, Propietario, Mascota, Veterinaria, Plan, Servicio, Reporte, Configuracion
  Http/Middleware/EnsureRol.php   control de acceso por rol
  Models/                  User, Veterinaria, Plan, Mascota, Afiliacion, Cita, Atencion
  Services/                lógica de negocio (planes, citas, historial, notificaciones, reportes)
  Support/                 catálogos, íconos, formato y menú
database/migrations/       tablas
database/seeders/          datos de demostración
lang/es/                   mensajes de validación en español
public/css/petcare.css     estilos (diseño del prototipo)
public/js/petcare.js       diálogos, confirmaciones y filtros
resources/views/           pantallas Blade (propietario, veterinaria, admin, auth, errors)
routes/web.php             rutas
tests/Feature/             pruebas automáticas
```

---

## 9. Decisiones tomadas

- **Autenticación propia de Laravel** (sesiones + `Auth`) en lugar de Breeze, para conservar exactamente el diseño del prototipo sin plantillas genéricas ni Node.js.
- **SQLite** en desarrollo. Para pasar a **MySQL** en producción solo se cambian en `.env` las variables `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` y se ejecuta `php artisan migrate --seed`.
- La tabla de usuarios se llama `users` (convención de Laravel) e incluye el campo `rol` (propietario, veterinaria, administrador).
- Las mascotas se eliminan con *borrado lógico* para no perder su historial clínico.
- Los recordatorios de vacunas se envían por correo y WhatsApp 7 días antes (tarea programada `petcare:recordatorios`, con `php artisan schedule:work`) y también se muestran en la campana.
- No hay pasarela de pago real: los pagos se concluyen por WhatsApp de PetCare (o tarjeta simulada) y el administrador los verifica.

---

## 10. Problemas frecuentes

| Problema | Solución |
|---|---|
| `php` o `composer` no se reconoce | Reinicia VS Code después de instalar; verifica con `php -v`. |
| `could not find driver` | Activa `pdo_sqlite` y `sqlite3` en `php.ini`. |
| `No application encryption key` | `php artisan key:generate` |
| `Failed to listen on 127.0.0.1:8000` | El puerto está ocupado: `php artisan serve --port=8001` y abre http://127.0.0.1:8001 |
| `database is locked` | Cierra DB Browser for SQLite (o presiona “Escribir cambios”). |
| Cambios en `.env` no se aplican | `php artisan config:clear` |
