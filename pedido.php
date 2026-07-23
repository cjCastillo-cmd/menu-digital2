<?php
require_once __DIR__ . '/app/repo.php';

$slug    = isset($_POST['r']) ? trim((string) $_POST['r']) : NEGOCIO_POR_DEFECTO;
$negocio = negocio_por_slug($slug);

if (!$negocio || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ir('index.php');
}

// Token contra falsificacion de formularios: todos los POST lo verifican.
verificar_token();

$carga = json_decode((string) ($_POST['carga'] ?? ''), true);
$error = null;
$pedido = null;

if (!is_array($carga)) {
    $error = 'No pudimos leer el pedido. Volvé al menú e intentá otra vez.';
} else {
    try {
        $pedido = guardar_pedido($negocio, $carga, $carga['lineas'] ?? []);
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        $error = MOSTRAR_ERRORES ? $e->getMessage() : 'No pudimos guardar el pedido. Intentá de nuevo.';
    }
}

$urlWhatsApp = '';
if ($pedido) {
    $urlWhatsApp = 'https://wa.me/' . preg_replace('/\D/', '', $negocio['whatsapp'])
                 . '?text=' . rawurlencode(texto_whatsapp($negocio, $pedido, $carga));
}

$volver = url('index.php?r=' . urlencode($negocio['slug'])
        . (!empty($carga['mesa']) ? '&mesa=' . urlencode($carga['mesa']) : ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#211F1C">
<title><?= $error ? 'No se pudo enviar' : 'Pedido ' . e($pedido['codigo']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/comanda.css') ?>">
<?= estilo_marca($negocio) ?>
</head>
<body>
<main class="envoltura" style="max-width:460px">

<?php if ($error): ?>

  <header class="cabecera">
    <span class="sello sello--rojo">No se envió</span>
    <h1 class="cabecera__nombre" style="font-size:26px">Algo faltó</h1>
  </header>
  <p class="nota-pie nota-pie--alerta" style="font-size:14px"><?= e($error) ?></p>
  <a class="accion" style="text-decoration:none;justify-content:center;margin-top:22px" href="<?= e($volver) ?>">
    Volver al menú
  </a>

<?php else: ?>

  <header class="cabecera">
    <div class="cabecera__meta">
      <span class="rotulo">Pedido <?= e($pedido['codigo']) ?></span>
      <span class="sello sello--ambar">Registrado</span>
    </div>
    <h1 class="cabecera__nombre" style="font-size:26px">Ya quedó anotado</h1>
    <p class="cabecera__tagline">
      Falta un paso: mandalo por WhatsApp para que la cocina lo confirme.
    </p>
  </header>

  <div class="ticket" style="margin-top:22px">
    <div class="ticket__encabezado"><?= e($negocio['nombre']) ?> · <?= e($pedido['codigo']) ?></div>
    <?php foreach ($pedido['lineas'] as $l): ?>
      <div class="ticket__linea">
        <span><?= (int) $l['cantidad'] ?>x</span>
        <div>
          <strong><?= e($l['nombre']) ?></strong>
          <?php if ($l['detalle']): ?>
            <p class="ticket__opciones"><?= e($l['detalle']) ?></p>
          <?php endif; ?>
          <?php if ($l['nota']): ?>
            <p class="ticket__opciones">Nota: <?= e($l['nota']) ?></p>
          <?php endif; ?>
        </div>
        <span><?= dinero($l['precio'] * $l['cantidad'], $negocio['moneda']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="ticket__totales">
      <div class="ticket__total"><span>Subtotal</span><span><?= dinero($pedido['subtotal'], $negocio['moneda']) ?></span></div>
      <?php if ($pedido['impuesto'] > 0): ?>
        <div class="ticket__total"><span>ISV</span><span><?= dinero($pedido['impuesto'], $negocio['moneda']) ?></span></div>
      <?php endif; ?>
      <?php if ($pedido['envio'] > 0): ?>
        <div class="ticket__total"><span>Envío</span><span><?= dinero($pedido['envio'], $negocio['moneda']) ?></span></div>
      <?php endif; ?>
      <div class="ticket__total ticket__total--fuerte">
        <span>Total</span><span><?= dinero($pedido['total'], $negocio['moneda']) ?></span>
      </div>
    </div>
  </div>

  <a class="accion" id="irWhatsApp" style="text-decoration:none;justify-content:center;margin-top:20px"
     href="<?= e($urlWhatsApp) ?>" target="_blank" rel="noopener">
    Abrir WhatsApp y enviar
  </a>
  <a class="accion accion--suave" style="text-decoration:none" href="<?= e($volver) ?>">Volver al menú</a>

  <p class="nota-pie">
    Guardá el código <?= e($pedido['codigo']) ?>. Si WhatsApp no abre solo, tocá el botón de arriba.
  </p>

  <script>
    setTimeout(function () {
      window.location.href = document.getElementById('irWhatsApp').href;
    }, 1200);
  </script>

<?php endif; ?>

</main>
</body>
</html>
