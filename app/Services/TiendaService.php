<?php

namespace App\Services;

use App\Models\Mascota;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Models\Veterinaria;
use App\Support\Catalogo;
use App\Support\Formato;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tienda PetCare: cada clínica vende su catálogo (con stock y según el límite de su suscripción).
 * El carrito vive en la sesión y se separa en un pedido por clínica (cada una cobra con su QR y entrega lo suyo).
 * Entrega: recoger en la clínica o envío a domicilio (costo que define la clínica).
 * El plan de la mascota descuenta medicamentos, vacunas y antiparasitarios comprados en SU veterinaria.
 */
class TiendaService
{
    use ReglaNegocio;

    private const CLAVE = 'carrito';

    public function __construct(private PagoService $pagos, private WhatsAppService $whatsapp, private CoberturaService $cobertura) {}

    // ---------------- Vitrina ----------------

    /** Productos visibles en la tienda (respeta el límite de cada suscripción; los agotados se muestran como «Agotado»). */
    public function vitrina(array $f = []): Collection
    {
        $vets = Veterinaria::activas()->where('estado', 'aprobada')->get()->keyBy('id');
        $visibles = $vets->flatMap(fn ($v) => $v->idsVisiblesEnTienda())->all();
        $agotados = Producto::whereIn('veterinaria_id', $vets->keys())->where('activo', true)->where('en_tienda', true)->where('stock', 0)->pluck('id')->all();

        return Producto::with('veterinaria')->whereIn('id', array_merge($visibles, $agotados))
            ->when($f['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->whereLike('nombre', "%{$t}%")->orWhereLike('descripcion', "%{$t}%")))
            ->when($f['categoria'] ?? null, fn ($q, $c) => $q->where('categoria', $c))
            ->when($f['veterinaria'] ?? null, fn ($q, $v) => $q->where('veterinaria_id', $v))
            ->when($f['max'] ?? null, fn ($q, $m) => $q->where('precio', '<=', (float) $m))
            ->orderByRaw('stock = 0')->orderBy(($f['orden'] ?? '') === 'precio' ? 'precio' : 'nombre')->get();
    }

    public function disponibleEnTienda(Producto $p): bool
    {
        $v = $p->veterinaria;

        return $p->activo && $p->en_tienda && $p->stock > 0 && $v && $v->activa && $v->suscripcionVigente() && in_array($p->id, $v->idsVisiblesEnTienda(), true);
    }

    // ---------------- Carrito (sesión) ----------------

    public function carrito(): array
    {
        return session(self::CLAVE, []);
    }

    public function cantidadEnCarrito(): int
    {
        return (int) array_sum($this->carrito());
    }

    public function agregar(Producto $p, int $cantidad): void
    {
        $this->exigir($this->disponibleEnTienda($p), 'Este producto ya no está disponible.', 'cantidad');
        $c = $this->carrito();
        $nueva = ($c[$p->id] ?? 0) + $cantidad;
        $this->exigir($nueva <= $p->stock, "Solo quedan {$p->stock} unidades de {$p->nombre}.", 'cantidad');
        $c[$p->id] = $nueva;
        session([self::CLAVE => $c]);
    }

    public function cambiar(Producto $p, int $cantidad): void
    {
        $c = $this->carrito();
        if ($cantidad <= 0) {
            unset($c[$p->id]);
        } else {
            $this->exigir($cantidad <= $p->stock, "Solo quedan {$p->stock} unidades de {$p->nombre}.", 'cantidad');
            $c[$p->id] = $cantidad;
        }
        session([self::CLAVE => $c]);
    }

    public function quitarDeClinica(int $vetId): void
    {
        $ids = Producto::where('veterinaria_id', $vetId)->pluck('id')->all();
        session([self::CLAVE => array_diff_key($this->carrito(), array_flip($ids))]);
    }

    /** El carrito agrupado por clínica: [vet, lineas[producto, cantidad, subtotal, disponible], subtotal] */
    public function porClinica(): Collection
    {
        $c = $this->carrito();
        $prods = Producto::with('veterinaria')->whereIn('id', array_keys($c))->get();

        return $prods->groupBy('veterinaria_id')->map(function ($ps) use ($c) {
            $lineas = $ps->map(fn ($p) => ['producto' => $p, 'cantidad' => $c[$p->id], 'subtotal' => round($p->precio * $c[$p->id], 2),
                'disponible' => $this->disponibleEnTienda($p) && $c[$p->id] <= $p->stock])->values();

            return ['vet' => $ps->first()->veterinaria, 'lineas' => $lineas, 'subtotal' => round($lineas->sum('subtotal'), 2)];
        })->values();
    }

    // ---------------- Descuento del plan ----------------

    /**
     * Descuento del plan en una compra: solo en la veterinaria del plan, fuera de la carencia, si el plan cubre medicamentos
     * y la clínica marcó el producto como cubierto. Se limita al saldo de cobertura del mes y del año.
     *
     * @return array{porcentaje: int, descuento: float, por_item: array<int, float>, nota: ?string}
     */
    public function descuento(?Mascota $m, Veterinaria $v, Collection $lineas): array
    {
        $nada = ['porcentaje' => 0, 'descuento' => 0.0, 'por_item' => [], 'nota' => null];
        $a = $m?->afiliacionActiva;
        if (! $a) {
            return $nada;
        }
        $plan = $a->plan;
        if ($a->veterinaria_id && $a->veterinaria_id !== $v->id) {
            return ['nota' => "El plan de {$m->nombre} solo descuenta en {$a->veterinaria?->nombre}."] + $nada;
        }
        if ($a->enCarencia()) {
            return ['nota' => "El plan de {$m->nombre} se puede usar desde el ".$a->carencia_hasta->format('d/m/Y').'.'] + $nada;
        }
        if ($plan->medicamentos !== 'todos') {
            return ['nota' => "El {$plan->nombre} no descuenta productos de la tienda."] + $nada;
        }
        $por = [];
        foreach ($lineas as $l) {
            $p = $l['producto'];
            if (in_array($p->categoria, Catalogo::DESCUENTO_TIENDA, true) && $p->aplicaA($plan)) {
                $por[$p->id] = round($l['subtotal'] * $plan->porcentaje / 100, 2);
            }
        }
        $total = array_sum($por);
        $saldo = max(0, $this->cobertura->disponible($m, $a, false, Carbon::today()));
        if ($total > $saldo) {
            $k = $total > 0 ? $saldo / $total : 0;
            $por = array_map(fn ($x) => round($x * $k, 2), $por);
            $total = array_sum($por);
        }

        return ['porcentaje' => $plan->porcentaje, 'descuento' => round($total, 2), 'por_item' => $por,
            'nota' => $total > 0 ? "{$plan->nombre}: {$plan->porcentaje} % en medicamentos, vacunas y antiparasitarios." : null];
    }

    // ---------------- Pedido y pago ----------------

    /** Crea el pedido de UNA clínica a partir del carrito, reserva el stock y registra el pago (lo verifica la clínica). */
    public function comprar(User $dueno, Veterinaria $v, array $datos, ?UploadedFile $comprobante): Pedido
    {
        $grupo = $this->porClinica()->firstWhere('vet.id', $v->id);
        $this->exigir((bool) $grupo, 'Tu carrito no tiene productos de esta clínica.');
        foreach ($grupo['lineas'] as $l) {
            $this->exigir($l['disponible'], "«{$l['producto']->nombre}» ya no tiene stock suficiente: ajusta la cantidad.");
        }
        $domicilio = ($datos['entrega'] ?? 'recoger') === 'domicilio';
        $this->exigir(! $domicilio || $v->envio_activo, "{$v->nombre} no hace envíos a domicilio: elige recoger en la clínica.", 'entrega');
        $m = ! empty($datos['mascota_id']) ? $dueno->mascotas()->find($datos['mascota_id']) : null;
        $desc = $this->descuento($m, $v, $grupo['lineas']);
        $envio = $domicilio ? (float) $v->envio_costo : 0.0;
        $total = round($grupo['subtotal'] - $desc['descuento'] + $envio, 2);

        $pedido = DB::transaction(function () use ($dueno, $v, $datos, $grupo, $desc, $envio, $total, $m, $domicilio) {
            $pedido = Pedido::create([
                'user_id' => $dueno->id, 'veterinaria_id' => $v->id, 'mascota_id' => $m?->id, 'entrega' => $domicilio ? 'domicilio' : 'recoger',
                'direccion' => $domicilio ? $datos['direccion'] : null, 'referencia' => $domicilio ? ($datos['referencia'] ?? null) : null,
                'subtotal' => $grupo['subtotal'], 'descuento' => $desc['descuento'], 'envio' => $envio, 'total' => $total, 'estado' => 'pendiente_pago',
                'nota' => $datos['nota'] ?? null,
            ]);
            $pedido->update(['numero' => 'PD-'.now()->format('Y').'-'.str_pad((string) $pedido->id, 6, '0', STR_PAD_LEFT)]);
            foreach ($grupo['lineas'] as $l) {
                $p = $l['producto'];
                // Reserva del stock (se devuelve si el pago se rechaza o el pedido se cancela)
                $ok = Producto::whereKey($p->id)->where('stock', '>=', $l['cantidad'])->decrement('stock', $l['cantidad']);
                $this->exigir($ok > 0, "«{$p->nombre}» se agotó mientras comprabas.");
                $pedido->items()->create(['producto_id' => $p->id, 'nombre' => $p->nombre, 'categoria' => $p->categoria, 'cantidad' => $l['cantidad'],
                    'precio' => $p->precio, 'descuento' => $desc['por_item'][$p->id] ?? 0, 'subtotal' => $l['subtotal']]);
            }

            return $pedido;
        });
        $this->quitarDeClinica($v->id);
        $this->pagos->solicitarPedido($dueno, $pedido, $datos, $comprobante);
        $this->whatsapp->enviarTexto($v->whatsappInternacional(), "PetCare 🛒 Pedido {$pedido->numero}: {$pedido->items()->count()} productos · ".Formato::bs($total)
            .' · '.($domicilio ? 'envío a domicilio' : 'recoge en la clínica')." · {$dueno->name}. Verifica el pago en PetCare.");

        return $pedido->fresh(['items', 'pago']);
    }

    /** La clínica confirma o rechaza el pago del pedido. */
    public function revisarPago(Pedido $pedido, User $vet, bool $aprobar, ?string $motivo = null): void
    {
        $this->exigir($pedido->estado === 'pendiente_pago' && $pedido->pago?->estado === 'pendiente', 'Este pago ya fue revisado.');
        DB::transaction(function () use ($pedido, $vet, $aprobar, $motivo) {
            $pedido->pago->update(['estado' => $aprobar ? 'aprobado' : 'rechazado', 'motivo_rechazo' => $aprobar ? null : $motivo,
                'revisado_por' => $vet->id, 'revisado_at' => now(), 'numero' => $aprobar ? 'TD-'.now()->format('Y').'-'.str_pad((string) $pedido->pago->id, 6, '0', STR_PAD_LEFT) : null]);
            if ($aprobar) {
                $pedido->update(['estado' => 'pagado']);
            } else {
                $this->devolverStock($pedido);
                $pedido->update(['estado' => 'cancelado']);
            }
        });
        $d = $pedido->user;
        $this->whatsapp->enviarTexto($d->telefonoInternacional(), $aprobar
            ? "PetCare ✅ Pago del pedido {$pedido->numero} confirmado. {$pedido->veterinaria->nombre} lo está preparando."
            : "PetCare ❌ Pago del pedido {$pedido->numero} no verificado. Motivo: {$motivo}. El pedido se canceló.", $d);
    }

    /** La clínica avanza el pedido: preparando → listo / en camino → entregado. */
    public function avanzar(Pedido $pedido): void
    {
        $sig = $pedido->siguiente();
        $this->exigir((bool) $sig, 'Este pedido no tiene un siguiente paso.');
        $pedido->update(['estado' => $sig]);
        $v = $pedido->veterinaria;
        $txt = match ($sig) {
            'preparando' => "PetCare 📦 Tu pedido {$pedido->numero} está en preparación.",
            'listo' => "PetCare 🛍️ Pedido {$pedido->numero} listo. Recógelo en {$v->nombre}, {$v->direccion}.",
            'en_camino' => "PetCare 🛵 Pedido {$pedido->numero} en camino a {$pedido->direccion}.",
            'entregado' => "PetCare ✅ Pedido {$pedido->numero} entregado. ¡Gracias por tu compra!",
        };
        $this->whatsapp->enviarTexto($pedido->user->telefonoInternacional(), $txt, $pedido->user);
    }

    /** Cancelar: el dueño mientras el pago no fue verificado; la clínica antes de entregar. Devuelve el stock. */
    public function cancelar(Pedido $pedido, User $quien, ?string $motivo = null): void
    {
        $esDueno = $quien->id === $pedido->user_id;
        $this->exigir($esDueno ? $pedido->estado === 'pendiente_pago' : in_array($pedido->estado, ['pendiente_pago', 'pagado', 'preparando'], true),
            $esDueno ? 'Ya no puedes cancelar: la clínica confirmó el pago. Escríbele por WhatsApp.' : 'Este pedido ya no se puede cancelar.');
        DB::transaction(function () use ($pedido, $motivo) {
            $this->devolverStock($pedido);
            if ($pedido->pago?->estado === 'pendiente') {
                $pedido->pago->update(['estado' => 'rechazado', 'motivo_rechazo' => $motivo ?: 'Pedido cancelado', 'revisado_at' => now()]);
            }
            $pedido->update(['estado' => 'cancelado']);
        });
        $destino = $esDueno ? $pedido->veterinaria->whatsappInternacional() : $pedido->user->telefonoInternacional();
        $this->whatsapp->enviarTexto($destino, "PetCare 🚫 Pedido {$pedido->numero} cancelado".($esDueno ? ' por el cliente.' : ' por la clínica.').($motivo ? " Motivo: {$motivo}." : ''),
            $esDueno ? null : $pedido->user);
    }

    private function devolverStock(Pedido $pedido): void
    {
        foreach ($pedido->items as $it) {
            if ($it->producto_id) {
                Producto::whereKey($it->producto_id)->increment('stock', $it->cantidad);
            }
        }
    }
}
