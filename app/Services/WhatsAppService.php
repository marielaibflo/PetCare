<?php

namespace App\Services;

use App\Models\MensajeWhatsapp;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envío de mensajes por WhatsApp.
 *  - driver «demo»: no envía nada; guarda el mensaje en el historial (Administrador → Configuración) y en el log.
 *  - driver «meta»: usa la API oficial de WhatsApp Business (Meta Cloud API).
 * Si un envío falla, se registra el error y el sistema sigue funcionando.
 */
class WhatsAppService
{
    /** Mensajes enviados en esta ejecución (para las pruebas automáticas). */
    public static array $enviados = [];

    public function enviarTexto(?string $numeroInternacional, string $mensaje, ?User $destinatario = null): bool
    {
        if (! $numeroInternacional) {
            return false;
        }

        return $this->enviar($numeroInternacional, [
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $mensaje],
        ], $mensaje, $destinatario);
    }

    /** Código de verificación. Meta exige una plantilla aprobada de «Autenticación» para este tipo de mensaje. */
    public function enviarCodigo(string $numeroInternacional, string $codigo, ?User $destinatario = null): bool
    {
        $minutos = config('petcare.verificacion.minutos');
        $texto = fn ($c) => "PetCare: tu código es {$c}. Vence en {$minutos} min. No lo compartas.";
        $plantilla = config('petcare.whatsapp.plantilla_codigo');
        $contenido = $plantilla ? [
            'type' => 'template',
            'template' => [
                'name' => $plantilla,
                'language' => ['code' => config('petcare.whatsapp.idioma')],
                'components' => [
                    ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $codigo]]],
                    ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $codigo]]],
                ],
            ],
        ] : ['type' => 'text', 'text' => ['preview_url' => false, 'body' => $texto($codigo)]];

        // En el historial el código queda oculto.
        return $this->enviar($numeroInternacional, $contenido, $texto('••••••'), $destinatario, $codigo);
    }

    /** Enlace «clic para chatear»: abre WhatsApp con el mensaje escrito (no necesita API). */
    public static function enlace(?string $numeroInternacional, string $mensaje = ''): ?string
    {
        if (! $numeroInternacional) {
            return null;
        }

        return 'https://wa.me/'.$numeroInternacional.($mensaje !== '' ? '?text='.rawurlencode($mensaje) : '');
    }

    private function enviar(string $numero, array $contenido, string $resumen, ?User $destinatario, ?string $codigo = null): bool
    {
        self::$enviados[] = ['para' => $numero, 'mensaje' => $codigo ? str_replace('••••••', $codigo, $resumen) : $resumen];
        $cfg = config('petcare.whatsapp');
        $estado = 'demo';
        $ok = true;

        if ($cfg['driver'] === 'meta') {
            try {
                $respuesta = Http::withToken((string) $cfg['token'])->acceptJson()->timeout(10)
                    ->post("https://graph.facebook.com/{$cfg['version']}/{$cfg['phone_id']}/messages",
                        ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $numero] + $contenido);
                $ok = $respuesta->successful();
                if (! $ok) {
                    Log::warning("[WhatsApp] No se pudo enviar a +{$numero}", ['respuesta' => $respuesta->json()]);
                }
            } catch (Throwable $e) {
                $ok = false;
                Log::warning("[WhatsApp] Error de conexión al enviar a +{$numero}: {$e->getMessage()}");
            }
            $estado = $ok ? 'enviado' : 'fallido';
        } else {
            Log::info("[WhatsApp demo] Para +{$numero}: {$resumen}");
        }

        try {
            MensajeWhatsapp::create(['user_id' => $destinatario?->id, 'para' => $numero, 'mensaje' => $resumen, 'estado' => $estado]);
        } catch (Throwable) {
            // El historial es informativo: si falla no se interrumpe el envío.
        }

        return $ok;
    }
}
