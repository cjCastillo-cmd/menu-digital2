<?php
require_once __DIR__ . '/comun.php';

$u = requiere_sesion();
$negocio = negocio_por_id((int) $u['negocio_id']);
$negocioId = (int) $negocio['id'];

$estados = ['recibido' => 'Recibido', 'preparando' => 'Preparando',
            'listo' => 'Listo', 'entregado' => 'Entregado', 'anulado' => 'Anulado'];

$siguiente = ['recibido' => 'preparando', 'preparando' => 'listo', 'listo' => 'entregado'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_token();
    $id = entero($_POST['id'] ?? 0);
    $nuevo = (string) ($_POST['estado'] ?? '');

    if (isset($estados[$nuevo]) && $id > 0) {
        consulta('UPDATE pedidos SET estado = ? WHERE id = ? AND negocio_id = ?',
                 [$nuevo, $id, $negocioId]);
    }
    ir('admin/cocina.php');
}

$abiertos = todas(
    "SELECT * FROM pedidos
      WHERE negocio_id = ? AND estado IN ('recibido','preparando','listo')
      ORDER BY creado ASC",
    [$negocioId]
);

$cerrados = todas(
    "SELECT * FROM pedidos
      WHERE negocio_id = ? AND estado IN ('entregado','anulado')
        AND creado >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
      ORDER BY creado DESC
      LIMIT 20",
    [$negocioId]
);

$ids = array_map(function ($p) { return (int) $p['id']; }, array_merge($abiertos, $cerrados));
$lineasPorPedido = [];
if ($ids) {
    $lista = implode(',', $ids);
    foreach (todas("SELECT * FROM pedido_lineas WHERE pedido_id IN ($lista) ORDER BY id") as $l) {
        $lineasPorPedido[(int) $l['pedido_id']][] = $l;
    }
}

cabecera_panel('Cocina', 'cocina', $negocio);
?>

<div class="bloque">
  <h2>En curso</h2>
  <p class="ayuda">
    <?= count($abiertos) ?> pedidos abiertos. La pantalla se refresca sola cada 25 segundos.
  </p>

  <?php if (!$abiertos): ?>
    <div class="vacio">Ningún pedido en curso.<br>Los nuevos aparecen aquí solos.</div>
  <?php else: ?>
    <div class="comandas">
      <?php foreach ($abiertos as $p):
          $modo = ['mesa' => 'En mesa', 'llevar' => 'Para llevar', 'domicilio' => 'Domicilio'];
          $sig  = $siguiente[$p['estado']] ?? null; ?>
        <article class="comanda">
          <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px">
            <span class="comanda__codigo"><?= e($p['codigo']) ?></span>
            <span class="comanda__meta"><?= date('H:i', strtotime($p['creado'])) ?></span>
          </div>
          <div class="comanda__meta">
            <?= e($modo[$p['modo']] ?? $p['modo']) ?>
            <?= $p['mesa'] ? ' · mesa ' . e($p['mesa']) : '' ?>
            <?= $p['zona'] ? ' · ' . e($p['zona']) : '' ?>
          </div>
          <div class="comanda__meta"><?= e($estados[$p['estado']]) ?></div>

          <div class="comanda__lineas">
            <?php foreach ($lineasPorPedido[(int) $p['id']] ?? [] as $l): ?>
              <div style="margin-bottom:7px">
                <strong><?= (int) $l['cantidad'] ?>x <?= e($l['nombre']) ?></strong>
                <?php if ($l['detalle']): ?>
                  <div style="font-size:11px;opacity:.75;white-space:pre-line"><?= e($l['detalle']) ?></div>
                <?php endif; ?>
                <?php if ($l['nota']): ?>
                  <div style="font-size:11px;opacity:.75">Nota: <?= e($l['nota']) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <div style="display:flex;justify-content:space-between;font-weight:600">
            <span>Total</span><span><?= dinero($p['total'], $negocio['moneda']) ?></span>
          </div>

          <?php if ($p['cliente'] || $p['telefono']): ?>
            <div class="comanda__meta" style="margin-top:6px">
              <?= e($p['cliente']) ?><?= $p['telefono'] ? ' · ' . e($p['telefono']) : '' ?>
            </div>
          <?php endif; ?>
          <?php if ($p['direccion']): ?>
            <div class="comanda__meta" style="margin-top:4px"><?= e($p['direccion']) ?></div>
          <?php endif; ?>

          <div class="comanda__acciones">
            <?php if ($sig): ?>
              <form method="post" style="flex:1">
                <input type="hidden" name="token" value="<?= e(token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="estado" value="<?= e($sig) ?>">
                <button type="submit" style="width:100%"><?= e($estados[$sig]) ?></button>
              </form>
            <?php endif; ?>
            <form method="post" style="flex:1"
                  onsubmit="return confirm('¿Anular el pedido <?= e($p['codigo']) ?>?')">
              <input type="hidden" name="token" value="<?= e(token()) ?>">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="estado" value="anulado">
              <button type="submit" style="width:100%">Anular</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($cerrados): ?>
<div class="bloque">
  <h2>Cerrados hoy</h2>
  <p class="ayuda">Últimos <?= count($cerrados) ?> pedidos terminados o anulados en las últimas 12 horas.</p>
  <table class="tabla">
    <thead><tr><th>Código</th><th>Hora</th><th>Tipo</th><th>Estado</th><th>Total</th></tr></thead>
    <tbody>
      <?php foreach ($cerrados as $p): ?>
        <tr>
          <td><?= e($p['codigo']) ?></td>
          <td><?= date('H:i', strtotime($p['creado'])) ?></td>
          <td><?= e($p['modo']) ?><?= $p['mesa'] ? ' ' . e($p['mesa']) : '' ?></td>
          <td><?= e($estados[$p['estado']]) ?></td>
          <td><?= dinero($p['total'], $negocio['moneda']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<script>setTimeout(function () { location.reload(); }, 25000);</script>

<?php pie_panel(); ?>
