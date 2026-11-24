<div class="tw"><table><thead><tr>@foreach($cab as $h)<th>{{ $h }}</th>@endforeach</tr></thead><tbody>
@foreach($filas as $f)<tr>@foreach($f as $i => $c)<td @if($i) data-l="{{ $cab[$i] }}" @endif>{{ $c }}</td>@endforeach</tr>@endforeach
</tbody></table></div>
