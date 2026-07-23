<?php
require_once __DIR__ . '/comun.php';

$u = requiere_sesion();
$negocio = negocio_por_id((int) $u['negocio_id']);
$negocioId = (int) $negocio['id'];

$pedido = una('SELECT * FROM pedidos WHERE id = ? AND negocio_id = ?',
              [entero($_GET['id'] ?? 0), $negocioId]);
if (!$pedido) {
    ir('admin/cocina.php');
}
$lineas = todas('SELECT * FROM pedido_lineas WHERE pedido_id = ? ORDER BY id', [(int) $pedido['id']]);
$m = (string) $negocio['moneda'];
$modos = ['mesa' => 'En mesa', 'llevar' => 'Para llevar', 'domicilio' => 'A domicilio'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Comanda <?= e($pedido['codigo']) ?></title>
<style>
  /* Ticket para impresora termica de 80mm (o papel normal). */
  * { box-sizing: border-box; }
  body { font-family: "Courier New", ui-monospace, monospace; color: #000; background: #fff;
         margin: 0; padding: 10px; font-size: 13px; line-height: 1.45; }
  .t { width: 280px; margin: 0 auto; }
  .c { text-align: center; }
  .g { font-size: 18px; font-weight: bold; }
  .r { border-top: 1px dashed #000; margin: 8px 0; }
  .fila { display: flex; justify-content: space-between; gap: 8px; }
  .op { font-size: 12px; padding-left: 14px; white-space: pre-line; }
  .tot { font-size: 16px; font-weight: bold; }
  .imp { margin: 14px auto 0; display: block; padding: 8px 14px; font-size: 13px; }
  @media print { .imp { display: none; } body { padding: 0; } }
</style>
</head>
<body>
<div class="t">
  <div class="c g"><?= e($negocio['nombre']) ?></div>
  <div class="c">Comanda <?= e($pedido['codigo']) ?></div>
  <div class="c"><?= date('d/m/Y H:i', strtotime($pedido['creado'])) ?></div>
  <div class="c">
    <?= e($modos[$pedido['modo']] ?? $pedido['modo']) ?>
    <?= $pedido['mesa'] ? ' · Mesa ' . e($pedido['mesa']) : '' ?>
  </div>
  <?php if ($pedido['cliente']): ?><div class="c"><?= e($pedido['cliente']) ?><?= $pedido['telefono'] ? ' · ' . e($pedido['telefono']) : '' ?></div><?php endif; ?>
  <?php if ($pedido['direccion']): ?><div><?= e($pedido['direccion']) ?></div><?php endif; ?>
  <div class="r"></div>

  <?php foreach ($lineas as $l): ?>
    <div class="fila">
      <span><?= (int) $l['cantidad'] ?>x <?= e($l['nombre']) ?></span>
      <span><?= dinero($l['precio'] * $l['cantidad'], $m) ?></span>
    </div>
    <?php if ($l['detalle']): ?><div class="op"><?= e($l['detalle']) ?></div><?php endif; ?>
    <?php if ($l['nota']): ?><div class="op">Nota: <?= e($l['nota']) ?></div><?php endif; ?>
  <?php endforeach; ?>

  <div class="r"></div>
  <div class="fila"><span>Subtotal</span><span><?= dinero($pedido['subtotal'], $m) ?></span></div>
  <?php if ($pedido['impuesto'] > 0): ?><div class="fila"><span>ISV</span><span><?= dinero($pedido['impuesto'], $m) ?></span></div><?php endif; ?>
  <?php if ($pedido['envio'] > 0): ?><div class="fila"><span>Envío</span><span><?= dinero($pedido['envio'], $m) ?></span></div><?php endif; ?>
  <?php if ($pedido['propina'] > 0): ?><div class="fila"><span>Propina</span><span><?= dinero($pedido['propina'], $m) ?></span></div><?php endif; ?>
  <div class="r"></div>
  <div class="fila tot"><span>TOTAL</span><span><?= dinero($pedido['total'], $m) ?></span></div>
  <div class="c" style="margin-top:12px">¡Gracias!</div>
</div>

<button class="imp" onclick="window.print()">Imprimir</button>
<script>window.print();</script>
</body>
</html>
