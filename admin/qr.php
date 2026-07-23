<?php
require_once __DIR__ . '/comun.php';

$u = requiere_dueno();
$negocio = negocio_por_id((int) $u['negocio_id']);

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
      . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('index.php');

cabecera_panel('Codigos QR', 'qr', $negocio);
?>

<div class="bloque">
  <h2>Un codigo por mesa</h2>
  <p class="ayuda">
    Cuando el cliente escanea, el numero de mesa ya viene cargado y no tiene que escribirlo.
    Genera la hoja, imprimila y recorta.
  </p>

  <div class="dos">
    <div class="campo" style="margin-top:0">
      <label class="campo__rotulo" for="base">Direccion del menu</label>
      <input id="base" value="<?= e($base) ?>">
    </div>
    <div class="campo" style="margin-top:0">
      <label class="campo__rotulo" for="hasta">Cuantas mesas</label>
      <input id="hasta" type="number" min="1" max="60" value="8">
    </div>
  </div>

  <div class="pie">
    <button class="accion" type="button" id="generar"><span>Generar hoja</span><span>&rarr;</span></button>
    <button class="mini" type="button" id="imprimir" style="padding:12px 16px">Imprimir</button>
  </div>
</div>

<div class="bloque" id="salida" style="display:none">
  <h2>Hoja para imprimir</h2>
  <p class="ayuda">Cada codigo lleva el nombre del local y el numero de mesa.</p>
  <div id="hoja" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px"></div>
</div>

<script src="<?= url('assets/js/qrcode.js') ?>"></script>
<script>
(function () {
  var slug = <?= json_encode($negocio['slug']) ?>;
  var local = <?= json_encode($negocio['nombre']) ?>;

  document.getElementById('generar').addEventListener('click', function () {
    var base = document.getElementById('base').value.trim().replace(/\?.*$/, '');
    var hasta = Math.max(1, Math.min(60, parseInt(document.getElementById('hasta').value, 10) || 1));
    var hoja = document.getElementById('hoja');
    hoja.innerHTML = '';

    if (typeof qrcode !== 'function') {
      hoja.textContent = 'No se pudo cargar el generador de codigos. Revisa la conexion.';
      document.getElementById('salida').style.display = '';
      return;
    }

    for (var i = 1; i <= hasta; i++) {
      var url = base + '?r=' + encodeURIComponent(slug) + '&mesa=' + i;
      var qr = qrcode(0, 'M');
      qr.addData(url);
      qr.make();

      var caja = document.createElement('div');
      caja.style.cssText = 'background:#fff;color:#111;padding:10px;border-radius:2px;text-align:center';
      caja.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 1, scalable: true }) +
        '<div style="font-size:11px;margin-top:6px;letter-spacing:.12em;text-transform:uppercase">' +
        local + '</div><div style="font-size:16px;font-weight:600">Mesa ' + i + '</div>';
      hoja.appendChild(caja);
    }

    document.getElementById('salida').style.display = '';
  });

  document.getElementById('imprimir').addEventListener('click', function () { window.print(); });
})();
</script>

<?php pie_panel(); ?>
