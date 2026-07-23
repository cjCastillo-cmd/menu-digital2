<?php
require_once __DIR__ . '/comun.php';

$u = requiere_dueno();
$negocio = negocio_por_id((int) $u['negocio_id']);
$negocioId = (int) $negocio['id'];
$m = (string) $negocio['moneda'];

// Rango: hoy (por defecto), 7 dias o 30 dias.
$rango = (string) ($_GET['rango'] ?? 'hoy');
$rangos = ['hoy' => 'Hoy', '7' => 'Últimos 7 días', '30' => 'Últimos 30 días'];
if (!isset($rangos[$rango])) {
    $rango = 'hoy';
}
$desde = $rango === 'hoy'
    ? 'CURDATE()'
    : 'DATE_SUB(NOW(), INTERVAL ' . (int) $rango . ' DAY)';

// Solo pedidos que no fueron anulados.
$cond = "negocio_id = ? AND estado <> 'anulado' AND creado >= $desde";

$res = una("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS ventas,
                   COALESCE(SUM(propina),0) AS propinas
              FROM pedidos WHERE $cond", [$negocioId]);
$n = (int) ($res['n'] ?? 0);
$ventas = (float) ($res['ventas'] ?? 0);
$propinas = (float) ($res['propinas'] ?? 0);
$ticket = $n > 0 ? $ventas / $n : 0;

$masVendidos = todas(
    "SELECT l.nombre, SUM(l.cantidad) AS q, SUM(l.cantidad * l.precio) AS monto
       FROM pedido_lineas l
       JOIN pedidos p ON p.id = l.pedido_id
      WHERE p.negocio_id = ? AND p.estado <> 'anulado' AND p.creado >= $desde
      GROUP BY l.nombre
      ORDER BY q DESC
      LIMIT 10",
    [$negocioId]
);

$porHora = todas(
    "SELECT HOUR(creado) AS h, COUNT(*) AS n, COALESCE(SUM(total),0) AS ventas
       FROM pedidos WHERE $cond
      GROUP BY HOUR(creado) ORDER BY h",
    [$negocioId]
);
$maxHora = 0.0;
foreach ($porHora as $ph) { $maxHora = max($maxHora, (float) $ph['ventas']); }

cabecera_panel('Reportes', 'reportes', $negocio);
?>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach ($rangos as $clave => $etiqueta): ?>
    <a class="mini <?= $rango === (string) $clave ? 'mini--activo' : '' ?>"
       href="<?= url('admin/reporte.php?rango=' . $clave) ?>"><?= e($etiqueta) ?></a>
  <?php endforeach; ?>
</div>

<div class="bloque">
  <h2>Ventas · <?= e($rangos[$rango]) ?></h2>
  <div class="dos" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px">
    <div>
      <div class="rotulo">Vendido</div>
      <div style="font-size:26px;font-weight:600"><?= dinero($ventas, $m) ?></div>
    </div>
    <div>
      <div class="rotulo">Pedidos</div>
      <div style="font-size:26px;font-weight:600"><?= $n ?></div>
    </div>
    <div>
      <div class="rotulo">Ticket promedio</div>
      <div style="font-size:26px;font-weight:600"><?= dinero($ticket, $m) ?></div>
    </div>
    <div>
      <div class="rotulo">Propinas</div>
      <div style="font-size:26px;font-weight:600"><?= dinero($propinas, $m) ?></div>
    </div>
  </div>
</div>

<div class="bloque">
  <h2>Platillos más vendidos</h2>
  <?php if (!$masVendidos): ?>
    <p class="ayuda">Aún no hay ventas en este rango.</p>
  <?php else: ?>
    <table class="tabla">
      <thead><tr><th>Platillo</th><th style="width:90px">Cantidad</th><th style="width:120px">Monto</th></tr></thead>
      <tbody>
        <?php foreach ($masVendidos as $i => $p): ?>
          <tr>
            <td><?= ($i + 1) ?>. <?= e($p['nombre']) ?></td>
            <td><?= (int) $p['q'] ?></td>
            <td><?= dinero($p['monto'], $m) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="bloque">
  <h2>Ventas por hora</h2>
  <?php if (!$porHora): ?>
    <p class="ayuda">Sin datos todavía.</p>
  <?php else: ?>
    <?php foreach ($porHora as $ph): $pct = $maxHora > 0 ? round((float) $ph['ventas'] / $maxHora * 100) : 0; ?>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:7px">
        <span class="comanda__meta" style="width:52px"><?= sprintf('%02d:00', (int) $ph['h']) ?></span>
        <div style="flex:1;background:var(--fondo3);border-radius:var(--r);overflow:hidden">
          <div style="width:<?= $pct ?>%;background:var(--ambar);height:16px"></div>
        </div>
        <span class="comanda__meta" style="width:90px;text-align:right"><?= dinero($ph['ventas'], $m) ?></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php pie_panel(); ?>
