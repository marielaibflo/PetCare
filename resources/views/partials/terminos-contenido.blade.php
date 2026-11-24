{{-- Términos y condiciones de PetCare. $para: propietario | veterinaria --}}
@php
  $planes = \App\Models\Plan::activos()->orderBy('precio')->get();
  $bs = fn ($n) => \App\Support\Formato::bs($n);
  $g = \App\Support\Catalogo::ADICIONAL_GRAVE;
  $sus = \App\Support\Catalogo::SUSCRIPCIONES;
@endphp
<div class="legal-doc">
  <p class="legal-ver">Versión 2.0 · vigente desde el 19/11/2026 · La Paz – Bolivia</p>
@if($para === 'propietario')
  <h3>1. Qué es PetCare</h3>
  <p>PetCare es un <b>plan de asistencia veterinaria</b>: pagas una cuota mensual o anual y, cuando tu mascota se atiende en una clínica afiliada, PetCare paga a la clínica un porcentaje del costo. Tú pagas el resto directamente en la clínica.</p>

  <h3>2. Planes, topes y límites</h3>
  <div class="tw"><table class="legal-t"><thead><tr><th>Plan</th><th>Cuota</th><th>PetCare paga</th><th>Tope mensual</th><th>Tope anual</th><th>Atenciones cubiertas</th><th>Espera cirugías</th></tr></thead><tbody>
    @foreach($planes as $p)<tr><td><b>{{ $p->nombre }}</b></td><td>{{ $bs($p->precio) }}/mes</td><td>{{ $p->porcentaje }} %</td><td>{{ $bs($p->tope_mensual) }}</td><td>{{ $bs($p->tope_anual) }}</td><td>{{ $p->limite_atenciones ? $p->limite_atenciones.' al año' : 'Sin límite' }}</td><td>{{ $p->espera_cirugia_dias ? $p->espera_cirugia_dias.' días' : '—' }}</td></tr>@endforeach
  </tbody></table></div>
  <ul>
    <li>El <b>tope mensual y anual</b> es lo máximo que PetCare paga por tu mascota en ese periodo.</li>
    <li>El <b>límite de atenciones</b> es la cantidad de atenciones con cobertura en 12 meses. <b>Al alcanzar el límite o el tope, la veterinaria cobra el precio completo</b> hasta que se renueve el periodo. El sistema te avisa antes de agendar.</li>
    <li>Adicional <b>Cobertura Grave</b> (opcional, {{ $bs($g['precio']) }}/mes): {{ $g['porcentaje'] }} % de oncología y enfermedades crónicas, hasta {{ $bs($g['tope_anual']) }} al año, después de {{ $g['espera_dias'] }} días de afiliación.</li>
  </ul>

  <h3>3. Qué cubre y qué no cubre</h3>
  <div class="legal-2">
    <div class="ok"><b>Cubre</b><ul><li>Los servicios incluidos en tu plan, <b>en la veterinaria que elegiste al contratarlo</b>.</li><li>Vacunas y antiparasitarios aplicados en la atención.</li><li>Medicamentos marcados por la clínica, según la regla de tu plan.</li></ul></div>
    <div class="no"><b>No cubre</b><ul><li>Enfermedades que la mascota tenía antes de afiliarse.</li><li>Alimentos, accesorios, higiene y juguetes.</li><li>Cirugías durante el periodo de espera.</li><li>Atenciones después de alcanzar el tope o el límite.</li><li>Atenciones en otras clínicas distintas a la de tu plan.</li><li>Ninguna atención durante la carencia (60 días), tampoco urgencias.</li></ul></div>
  </div>

  <h3>4. Contrato, permanencia y carencia</h3>
  <ul>
    <li>Cada plan se contrata para <b>una sola veterinaria</b>, que no se puede cambiar mientras dure el contrato. En tu próximo plan podrás elegir otra. Si la veterinaria deja PetCare, podrás elegir otra sin penalidad.</li>
    <li><b>Permanencia mínima de 3 meses</b>: no se puede cancelar el plan antes. En la modalidad mensual se paga mes a mes; los meses del contrato mínimo que no se paguen quedan como deuda.</li>
    <li><b>Carencia de 60 días</b>: el plan se puede usar desde el día 61 de afiliación, incluidas las urgencias.</li>
    <li>Además de estos términos, aceptas el <b>Contrato de afiliación</b>, que recibes en PDF con tu factura.</li>
  </ul>

  <h3>5. Pagos</h3>
  <ul>
    <li>El plan se activa cuando PetCare <b>verifica tu pago</b> (tarjeta, QR con comprobante o WhatsApp). Recibirás la factura en PDF por correo.</li>
    <li>Pago anual: 12 meses pagando 10. No hay devolución de cuotas ya pagadas.</li>
    <li>El pago con tarjeta es por ahora una <b>simulación</b>: PetCare confirma el cobro manualmente.</li>
    <li>La parte de cada cita que no cubre el plan la pagas a la veterinaria (tarjeta, su QR o en efectivo en la clínica).</li>
  </ul>

  <h3>6. Tus obligaciones</h3>
  <ul>
    <li>Dar datos verdaderos de tu mascota y avisar de enfermedades previas.</li>
    <li>Mantener actualizados tu correo y tu WhatsApp, donde recibirás avisos y recordatorios.</li>
    <li>Cancelar con anticipación las citas a las que no asistirás.</li>
  </ul>

  <h3>7. Qué hace PetCare</h3>
  <ul>
    <li>Paga a la clínica el porcentaje cubierto, dentro de los topes y límites.</li>
    <li>Guarda el historial clínico y la cartilla de vacunas de tu mascota.</li>
    <li>Te envía recordatorios 7 días antes de cada vacuna o desparasitación.</li>
    <li>PetCare <b>no presta servicios veterinarios</b>: la responsabilidad médica es de cada clínica.</li>
  </ul>
@else
  <h3>1. Afiliación de la clínica</h3>
  <p>La clínica se afilia en línea, con su <b>N.º de registro o licencia de funcionamiento</b> y la <b>matrícula profesional</b> del veterinario responsable. PetCare revisa los documentos antes de activarla.</p>

  <h3>2. Suscripciones</h3>
  <div class="tw"><table class="legal-t"><thead><tr><th>Suscripción</th><th>Precio mensual</th><th>Atiende planes PetCare</th><th>Productos</th></tr></thead><tbody>
    @foreach($sus as $s)<tr><td><b>{{ $s['nombre'] }}</b></td><td>{{ $bs($s['precio']) }}</td><td>{{ $s['atiende_planes'] ? 'Sí' : 'No' }}</td><td>{{ $s['productos'] ? 'Hasta '.$s['productos'] : 'Ilimitados' }}</td></tr>@endforeach
  </tbody></table></div>
  <ul>
    <li>Todas las suscripciones atienden a las mascotas con plan PetCare: PetCare paga a la clínica la parte cubierta por el plan.</li>
    <li><b>El primer mes es gratis</b>, pero al afiliarse la clínica registra su forma de pago. Al terminar el mes gratis el cobro es obligatorio.</li>
    <li>Con <b>tarjeta</b>, cada mes se cobra automáticamente el precio de la suscripción (hoy en modo simulación) y llega la factura por correo. Con <b>QR o WhatsApp</b>, PetCare avisa 5 días antes de cada vencimiento para pagar desde el sistema.</li>
    <li>Si la suscripción vence sin pago, la clínica deja de aparecer en el buscador y no recibe citas nuevas hasta pagar. No hay devolución de meses pagados.</li>
    <li>La forma de cobro se puede cambiar en cualquier momento desde «Suscripción».</li>
  </ul>

  <h3>3. Atención de mascotas con plan</h3>
  <div class="tw"><table class="legal-t"><thead><tr><th>Plan</th><th>PetCare paga</th><th>Tope anual</th><th>Atenciones cubiertas</th></tr></thead><tbody>
    @foreach($planes as $p)<tr><td><b>{{ $p->nombre }}</b></td><td>{{ $p->porcentaje }} %</td><td>{{ $bs($p->tope_anual) }}</td><td>{{ $p->limite_atenciones ? $p->limite_atenciones.' al año' : 'Sin límite' }}</td></tr>@endforeach
  </tbody></table></div>
  <ul>
    <li>PetCare paga a la clínica la <b>parte cubierta</b> de cada atención registrada en el sistema; el dueño paga el resto en la clínica.</li>
    <li><b>Si la mascota alcanzó su tope o su límite de atenciones, la clínica cobra el precio completo al dueño</b>: el sistema lo calcula y lo muestra al registrar la atención.</li>
    <li>La liquidación se hace por mes, con el reporte de atenciones de la clínica.</li>
  </ul>

  <h3>4. Obligaciones de la clínica</h3>
  <ul>
    <li>Publicar precios reales en su tarifario y catálogo; el catálogo es informativo para los dueños.</li>
    <li>Registrar cada atención, vacuna y desparasitación con datos verdaderos, matrícula del veterinario y N.º de registro.</li>
    <li>Responder las solicitudes de cita y verificar los pagos de citas que reciba (tarjeta o QR con comprobante).</li>
    <li>La responsabilidad médica de cada atención es de la clínica.</li>
  </ul>

  <h3>5. Qué hace PetCare</h3>
  <ul>
    <li>Muestra la clínica en el buscador con su página, mapa, fotos, catálogo y calificaciones.</li>
    <li>Envía las citas y avisos por WhatsApp y correo.</li>
    <li>Paga puntualmente la parte cubierta de las atenciones de mascotas con plan.</li>
  </ul>
@endif
  <h3>{{ $para === 'propietario' ? '7' : '6' }}. Datos personales</h3>
  <p>PetCare usa tu correo y celular para verificar tu cuenta y enviarte avisos. Las contraseñas se guardan cifradas y los archivos (fotos, comprobantes y certificados) solo los ven las personas autorizadas. De la tarjeta solo se guardan los últimos 4 dígitos.</p>
  <p class="legal-fin">Fin del documento · Al aceptar declaras haber leído y entendido estos términos.</p>
</div>
