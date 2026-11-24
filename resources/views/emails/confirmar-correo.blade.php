<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirma tu correo</title></head>
<body style="margin:0;background:#F4F2FB;font-family:Arial,Helvetica,sans-serif;color:#1D1A3B">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F2FB;padding:28px 12px">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #E7E4F3">
        <tr><td style="background:#5B3FD6;padding:22px 26px;color:#fff;font-size:22px;font-weight:bold">🐾 PetCare</td></tr>
        <tr><td style="padding:28px 26px 8px">
          <p style="margin:0 0 12px;font-size:17px"><b>Hola {{ $nombre }},</b></p>
          <p style="margin:0 0 18px;font-size:15px;line-height:1.55">{{ $intro }} Para terminar, confirma que este correo es tuyo:</p>
          <p style="margin:0 0 22px;text-align:center">
            <a href="{{ $enlace }}" style="display:inline-block;background:#5B3FD6;color:#ffffff;text-decoration:none;font-weight:bold;font-size:16px;padding:14px 30px;border-radius:12px">Confirmar mi correo</a>
          </p>
          <p style="margin:0 0 6px;font-size:13.5px;color:#6B6890;text-align:center">O escribe este código en PetCare:</p>
          <p style="margin:0 0 22px;text-align:center;font-size:30px;letter-spacing:8px;font-weight:bold;color:#1D1A3B">{{ $codigo }}</p>
          <p style="margin:0 0 6px;font-size:12.5px;color:#6B6890;line-height:1.5">El botón funciona durante 24 horas y el código durante {{ $minutos }} minutos. Si no creaste una cuenta en PetCare, ignora este mensaje: no se activará nada.</p>
        </td></tr>
        <tr><td style="padding:16px 26px 22px;font-size:12px;color:#9A97B5;border-top:1px solid #EEEBF7">Si el botón no funciona, copia este enlace en tu navegador:<br><span style="word-break:break-all;color:#5B3FD6">{{ $enlace }}</span></td></tr>
      </table>
      <p style="font-size:11.5px;color:#9A97B5;margin:14px 0 0">PetCare · Asistencia veterinaria · La Paz – Bolivia</p>
    </td></tr>
  </table>
</body></html>
