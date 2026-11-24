<?php

/*
| Configuración propia de PetCare. Los valores se cambian desde el archivo .env.
*/

return [

    // Modo demostración: muestra en pantalla los códigos de verificación
    // (útil para presentar el proyecto sin WhatsApp ni correo configurados).
    'demo' => env('PETCARE_DEMO', true),

    // Comprueba que el dominio del correo exista (registros DNS). Requiere internet.
    'validar_dns' => env('PETCARE_VALIDAR_DNS', true),

    // WhatsApp de PetCare: aquí se concluyen los pagos de planes y suscripciones (cobro por QR).
    'whatsapp_petcare' => env('PETCARE_WHATSAPP', '59178539749'),

    // Número de muestra para las veterinarias mientras no tengan su WhatsApp real.
    // Si se deja vacío, cada cita va al WhatsApp registrado por la clínica.
    'whatsapp_veterinarias_demo' => env('PETCARE_WHATSAPP_VETERINARIAS', '59168186482'),

    // Días de anticipación para el recordatorio de vacunas y desparasitaciones.
    'dias_recordatorio' => 7,

    'verificacion' => [
        'minutos' => 15,   // vigencia de cada código
        'intentos' => 5,   // intentos permitidos por código
    ],

    /*
    | WhatsApp
    | driver = demo  → los mensajes se guardan en storage/logs/laravel.log
    | driver = meta  → envío real con la API de WhatsApp Business (Meta Cloud API)
    */
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'demo'),
        'token' => env('WHATSAPP_TOKEN'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
        'version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        // Plantilla aprobada por Meta para enviar códigos (categoría «Autenticación»).
        'plantilla_codigo' => env('WHATSAPP_TEMPLATE_CODIGO'),
        'idioma' => env('WHATSAPP_TEMPLATE_IDIOMA', 'es'),
    ],
];
