{{-- Botones de exportar a Excel (libro completo con todas las hojas) e imprimir la lista actual --}}
<a class="btn ghost noprint" href="{{ route('admin.reportes.excel', ['periodo' => 'anio', 'fecha' => today()->year]) }}" title="Un solo Excel con todas las hojas: listas, historial clínico, contabilidad y reportes del año"><x-icon name="down"/>Excel</a>
<button class="btn ghost noprint" type="button" data-print><x-icon name="print"/>Imprimir</button>
