<?php
require_once __DIR__ . '/app/repo.php';

$codigo = strtoupper(trim((string) ($_GET['c'] ?? '')));
$slug   = trim((string) ($_GET['r'] ?? ''));
$negocio = $slug !== '' ? negocio_por_slug($slug) : null;

$pedido = null;
if ($negocio && $codigo !== '') {
    $pedido = una(
        'SELECT * FROM pedidos WHERE negocio_id = ? AND codigo = ?',
        [(int) $negocio['id'], $codigo]
    );
}

$tema = $negocio ? tema_valido($negocio) : 'comanda';

// Pasos del pedido (el "anulado" se maneja aparte).
$pasos = ['recibido' => 'Recibido', 'preparando' => 'En preparación',
          'listo' => 'Listo', 'entregado' => 'Entregado'];
$estado = $pedido['estado'] ?? '';
$indiceActual = array_search($estado, array_keys($pasos), true);

$lineas = [];
if ($pedido) {
    $lineas = todas('SELECT * FROM pedido_lineas WHERE pedido_id = ? ORDER BY id', [(int) $pedido['id']]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $pedido ? 'Pedido ' . e($pedido['codigo']) : 'Seguimiento' ?></title>
<?php if ($pedido && $estado !== 'entregado' && $estado !== 'anulado'): ?>
  <meta http-equiv="refresh" content="15">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= e(url_fuentes($tema)) ?>" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/' . $tema . '.css') ?>">
<?php if ($negocio) { echo estilo_marca($negocio); } ?>
</head>
<body>
<main class="envoltura" style="max-width:480px">

<?php if (!$pedido): ?>

  <header class="cabecera">
    <h1 class="cabecera__nombre" style="font-size:26px">No encontramos ese pedido</h1>
    <p class="cabecera__tagline">Revisá el código o volvé a escanear el menú.</p>
  </header>

<?php elseif ($estado === 'anulado'): ?>

  <header class="cabecera">
    <span class="sello sello--rojo">Anulado</span>
    <h1 class="cabecera__nombre" style="font-size:26px">Pedido <?= e($pedido['codigo']) ?></h1>
    <p class="cabecera__tagline">Este pedido fue anulado. Si es un error, avisá al personal.</p>
  </header>

<?php else: ?>

  <header class="cabecera">
    <div class="cabecera__meta">
      <span class="rotulo">Pedido <?= e($pedido['codigo']) ?></span>
      <span class="sello <?= $estado === 'listo' ? 'sello--ambar' : 'sello--rojo' ?>">
        <?= e($pasos[$estado] ?? $estado) ?>
      </span>
    </div>
    <h1 class="cabecera__nombre" style="font-size:30px">
      <?= $estado === 'listo' ? '¡Tu pedido está listo!' : ($estado === 'entregado' ? '¡Buen provecho!' : 'Estamos en eso') ?>
    </h1>
    <p class="cabecera__tagline">
      <?= $pedido['mesa'] ? 'Mesa ' . e($pedido['mesa']) . ' · ' : '' ?>
      Esta página se actualiza sola.
    </p>
  </header>

  <!-- Stepper -->
  <div style="display:flex;gap:6px;margin:24px 0">
    <?php $i = 0; foreach ($pasos as $clave => $texto):
        $hecho = $indiceActual !== false && $i <= $indiceActual; ?>
      <div style="flex:1;text-align:center">
        <div style="height:6px;border-radius:99px;background:<?= $hecho ? 'var(--ambar)' : 'var(--linea)' ?>"></div>
        <div class="comanda__meta" style="margin-top:6px;color:<?= $hecho ? 'var(--tinta)' : 'var(--suave)' ?>">
          <?= e($texto) ?>
        </div>
      </div>
    <?php $i++; endforeach; ?>
  </div>

  <div class="ticket">
    <div class="ticket__encabezado"><?= e($negocio['nombre']) ?> · <?= e($pedido['codigo']) ?></div>
    <?php foreach ($lineas as $l): ?>
      <div class="ticket__linea">
        <span><?= (int) $l['cantidad'] ?>x</span>
        <div>
          <strong><?= e($l['nombre']) ?></strong>
          <?php if ($l['detalle']): ?><p class="ticket__opciones"><?= e($l['detalle']) ?></p><?php endif; ?>
        </div>
        <span><?= dinero($l['precio'] * $l['cantidad'], $negocio['moneda']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="ticket__totales">
      <div class="ticket__total ticket__total--fuerte">
        <span>Total</span><span><?= dinero($pedido['total'], $negocio['moneda']) ?></span>
      </div>
    </div>
  </div>

  <a class="accion--suave" style="text-decoration:none;display:flex"
     href="<?= e(url('index.php?r=' . urlencode($negocio['slug']) . ($pedido['mesa'] ? '&mesa=' . urlencode($pedido['mesa']) : ''))) ?>">
    Volver al menú
  </a>

  <?php if ($estado === 'listo'): ?>
  <script>
    (function () {
      var clave = 'avisado_<?= e($pedido['codigo']) ?>';
      if (localStorage.getItem(clave)) return;
      localStorage.setItem(clave, '1');
      try {
        var ac = new (window.AudioContext || window.webkitAudioContext)();
        [0, 0.25, 0.5].forEach(function (t) {
          var o = ac.createOscillator(), g = ac.createGain();
          o.frequency.value = 880; o.connect(g); g.connect(ac.destination);
          g.gain.setValueAtTime(0.001, ac.currentTime + t);
          g.gain.exponentialRampToValueAtTime(0.4, ac.currentTime + t + 0.02);
          g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + t + 0.2);
          o.start(ac.currentTime + t); o.stop(ac.currentTime + t + 0.2);
        });
      } catch (e) {}
      if (navigator.vibrate) navigator.vibrate([120, 60, 120]);
    })();
  </script>
  <?php endif; ?>

<?php endif; ?>

</main>
</body>
</html>
