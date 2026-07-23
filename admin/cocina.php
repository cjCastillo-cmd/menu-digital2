<?php
require_once __DIR__ . '/comun.php';

$u = requiere_sesion();
$negocio = negocio_por_id((int) $u['negocio_id']);
$negocioId = (int) $negocio['id'];

$estados = ['recibido' => 'Recibido', 'preparando' => 'Preparando',
            'listo' => 'Listo', 'entregado' => 'Entregado', 'anulado' => 'Anulado'];

$siguiente = ['recibido' => 'preparando', 'preparando' => 'listo', 'listo' => 'entregado'];

// Vista activa: comandas (cocina) o mesas (caja).
$vista = (($_GET['vista'] ?? '') === 'mesas') ? 'mesas' : 'comandas';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_token();
    $accion  = (string) ($_POST['accion'] ?? 'estado');
    $volverA = (($_POST['vista'] ?? '') === 'mesas') ? 'mesas' : 'comandas';

    if ($accion === 'atender') {
        // Marca una llamada al mesero como atendida.
        consulta('UPDATE llamadas SET atendida = 1 WHERE id = ? AND negocio_id = ?',
                 [entero($_POST['id'] ?? 0), $negocioId]);
    } elseif ($accion === 'cerrar_mesa') {
        // Caja cobra la mesa: cierra todos sus pedidos activos.
        $mesa = mb_substr(trim((string) ($_POST['mesa'] ?? '')), 0, 10);
        if ($mesa !== '') {
            consulta(
                "UPDATE pedidos SET estado = 'entregado'
                  WHERE negocio_id = ? AND mesa = ?
                    AND estado IN ('recibido','preparando','listo')",
                [$negocioId, $mesa]
            );
        }
    } else {
        // Cambio de estado de un pedido.
        $id = entero($_POST['id'] ?? 0);
        $nuevo = (string) ($_POST['estado'] ?? '');
        if (isset($estados[$nuevo]) && $id > 0) {
            consulta('UPDATE pedidos SET estado = ? WHERE id = ? AND negocio_id = ?',
                     [$nuevo, $id, $negocioId]);
        }
    }
    ir('admin/cocina.php?vista=' . $volverA);
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

$ids = array_map(static function ($p) { return (int) $p['id']; }, array_merge($abiertos, $cerrados));
$lineasPorPedido = [];
if ($ids) {
    $lista = implode(',', $ids);
    foreach (todas("SELECT * FROM pedido_lineas WHERE pedido_id IN ($lista) ORDER BY id") as $l) {
        $lineasPorPedido[(int) $l['pedido_id']][] = $l;
    }
}

// Agrupar los pedidos de mesa por numero de mesa (para la vista de caja).
$porMesa = [];
foreach ($abiertos as $p) {
    if ($p['modo'] === 'mesa' && trim((string) $p['mesa']) !== '') {
        $porMesa[(string) $p['mesa']][] = $p;
    }
}
uksort($porMesa, 'strnatcasecmp');

// Llamadas al mesero pendientes.
$llamadas = todas(
    'SELECT * FROM llamadas WHERE negocio_id = ? AND atendida = 0 ORDER BY creado ASC',
    [$negocioId]
);

// Marcadores para el aviso sonoro (cambian cuando entra algo nuevo).
$maxPedido = (int) (una('SELECT COALESCE(MAX(id),0) AS m FROM pedidos WHERE negocio_id = ?',
                        [$negocioId])['m'] ?? 0);
$numLlamadas = count($llamadas);

$modos = ['mesa' => 'En mesa', 'llevar' => 'Para llevar', 'domicilio' => 'Domicilio'];

/** Pinta las lineas de un pedido. */
function pintar_lineas(array $lineas): void
{
    foreach ($lineas as $l) { ?>
      <div style="margin-bottom:7px">
        <strong><?= (int) $l['cantidad'] ?>x <?= e($l['nombre']) ?></strong>
        <?php if ($l['detalle']): ?>
          <div style="font-size:11px;opacity:.75;white-space:pre-line"><?= e($l['detalle']) ?></div>
        <?php endif; ?>
        <?php if ($l['nota']): ?>
          <div style="font-size:11px;opacity:.75">Nota: <?= e($l['nota']) ?></div>
        <?php endif; ?>
      </div>
    <?php }
}

cabecera_panel('Cocina', 'cocina', $negocio);
?>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <a class="mini <?= $vista === 'comandas' ? 'mini--activo' : '' ?>"
     href="<?= url('admin/cocina.php?vista=comandas') ?>">Comandas (<?= count($abiertos) ?>)</a>
  <a class="mini <?= $vista === 'mesas' ? 'mini--activo' : '' ?>"
     href="<?= url('admin/cocina.php?vista=mesas') ?>">Por mesa (<?= count($porMesa) ?>)</a>
</div>

<?php if ($llamadas): ?>
  <div class="aviso" style="border-color:var(--rojo);color:var(--rojo)">
    <strong>Mesas llamando al mesero:</strong>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
      <?php foreach ($llamadas as $ll): ?>
        <form method="post" style="display:inline">
          <input type="hidden" name="token" value="<?= e(token()) ?>">
          <input type="hidden" name="accion" value="atender">
          <input type="hidden" name="vista" value="<?= e($vista) ?>">
          <input type="hidden" name="id" value="<?= (int) $ll['id'] ?>">
          <button class="mini mini--peligro" type="submit">
            Mesa <?= e($ll['mesa']) ?> · atender ✓
          </button>
        </form>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($vista === 'comandas'): ?>

  <div class="bloque">
    <h2>En curso</h2>
    <p class="ayuda">
      <?= count($abiertos) ?> pedidos abiertos. La pantalla se refresca sola cada 25 segundos.
    </p>

    <?php if (!$abiertos): ?>
      <div class="vacio">Ningún pedido en curso.<br>Los nuevos aparecen aquí solos.</div>
    <?php else: ?>
      <div class="comandas">
        <?php foreach ($abiertos as $p): $sig = $siguiente[$p['estado']] ?? null; ?>
          <article class="comanda">
            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px">
              <span class="comanda__codigo"><?= e($p['codigo']) ?></span>
              <span class="comanda__meta"><?= date('H:i', strtotime($p['creado'])) ?></span>
            </div>
            <div class="comanda__meta">
              <?= e($modos[$p['modo']] ?? $p['modo']) ?>
              <?= $p['mesa'] ? ' · mesa ' . e($p['mesa']) : '' ?>
              <?= $p['zona'] ? ' · ' . e($p['zona']) : '' ?>
            </div>
            <div class="comanda__meta"><?= e($estados[$p['estado']]) ?></div>

            <div class="comanda__lineas"><?php pintar_lineas($lineasPorPedido[(int) $p['id']] ?? []); ?></div>

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
              <a class="mini" href="<?= url('admin/imprimir.php?id=' . (int) $p['id']) ?>"
                 target="_blank" rel="noopener" style="flex:1;text-align:center;padding:7px 0">Imprimir</a>
              <?php if ($sig): ?>
                <form method="post" style="flex:1">
                  <input type="hidden" name="token" value="<?= e(token()) ?>">
                  <input type="hidden" name="vista" value="comandas">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <input type="hidden" name="estado" value="<?= e($sig) ?>">
                  <button type="submit" style="width:100%"><?= e($estados[$sig]) ?></button>
                </form>
              <?php endif; ?>
              <form method="post" style="flex:1"
                    onsubmit="return confirm('¿Anular el pedido <?= e($p['codigo']) ?>?')">
                <input type="hidden" name="token" value="<?= e(token()) ?>">
                <input type="hidden" name="vista" value="comandas">
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

<?php else: /* ---------- Vista por mesa (caja) ---------- */ ?>

  <div class="bloque">
    <h2>Mesas abiertas</h2>
    <p class="ayuda">
      Cada mesa junta todos sus pedidos y su total. Cuando el cliente paga,
      tocá "Cobrar y cerrar mesa" para dejarla libre.
    </p>
  </div>

  <?php if (!$porMesa): ?>
    <div class="vacio">Ninguna mesa con pedidos.<br>Cuando alguien pida desde su mesa, aparece aquí.</div>
  <?php else: ?>
    <?php foreach ($porMesa as $mesa => $pedidos):
        $totalMesa = 0.0;
        foreach ($pedidos as $pp) { $totalMesa += (float) $pp['total']; } ?>
      <div class="bloque">
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px">
          <h2 style="margin:0">Mesa <?= e($mesa) ?></h2>
          <span class="comanda__codigo"><?= dinero($totalMesa, $negocio['moneda']) ?></span>
        </div>
        <p class="ayuda" style="margin:4px 0 14px"><?= count($pedidos) ?> pedido(s) en esta mesa.</p>

        <?php foreach ($pedidos as $p): $sig = $siguiente[$p['estado']] ?? null; ?>
          <div style="border:1px dashed var(--linea);border-radius:var(--r);padding:12px;margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px">
              <span class="comanda__codigo" style="font-size:13px"><?= e($p['codigo']) ?> · <?= e($estados[$p['estado']]) ?></span>
              <span class="comanda__meta"><?= date('H:i', strtotime($p['creado'])) ?> · <?= dinero($p['total'], $negocio['moneda']) ?></span>
            </div>
            <div style="margin-top:8px"><?php pintar_lineas($lineasPorPedido[(int) $p['id']] ?? []); ?></div>
            <div class="comanda__acciones">
              <?php if ($sig): ?>
                <form method="post" style="flex:1">
                  <input type="hidden" name="token" value="<?= e(token()) ?>">
                  <input type="hidden" name="vista" value="mesas">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <input type="hidden" name="estado" value="<?= e($sig) ?>">
                  <button type="submit" style="width:100%"><?= e($estados[$sig]) ?></button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <form method="post" style="margin-top:6px"
              onsubmit="return confirm('¿Cobrar y cerrar la mesa <?= e($mesa) ?>? Sus pedidos pasan a entregado.')">
          <input type="hidden" name="token" value="<?= e(token()) ?>">
          <input type="hidden" name="accion" value="cerrar_mesa">
          <input type="hidden" name="vista" value="mesas">
          <input type="hidden" name="mesa" value="<?= e($mesa) ?>">
          <button class="accion" type="submit" style="width:100%">
            <span>Cobrar y cerrar mesa <?= e($mesa) ?></span>
            <span><?= dinero($totalMesa, $negocio['moneda']) ?></span>
          </button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

<?php endif; ?>

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
          <td><?= e($modos[$p['modo']] ?? $p['modo']) ?><?= $p['mesa'] ? ' ' . e($p['mesa']) : '' ?></td>
          <td><?= e($estados[$p['estado']]) ?></td>
          <td><?= dinero($p['total'], $negocio['moneda']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<script>
(function () {
  var maxPedido = <?= $maxPedido ?>, llamadas = <?= $numLlamadas ?>;

  function beep(veces) {
    try {
      var ac = new (window.AudioContext || window.webkitAudioContext)();
      for (var i = 0; i < veces; i++) {
        var t = i * 0.22, o = ac.createOscillator(), g = ac.createGain();
        o.frequency.value = 760; o.connect(g); g.connect(ac.destination);
        g.gain.setValueAtTime(0.001, ac.currentTime + t);
        g.gain.exponentialRampToValueAtTime(0.5, ac.currentTime + t + 0.02);
        g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + t + 0.18);
        o.start(ac.currentTime + t); o.stop(ac.currentTime + t + 0.18);
      }
    } catch (e) {}
    if (navigator.vibrate) navigator.vibrate(200);
  }

  var prevP = parseInt(localStorage.getItem('coc_maxped') || '0', 10);
  var prevL = parseInt(localStorage.getItem('coc_llam') || '0', 10);
  if (maxPedido > prevP) { beep(2); }          // pedido nuevo
  else if (llamadas > prevL) { beep(3); }      // llamada nueva
  localStorage.setItem('coc_maxped', String(maxPedido));
  localStorage.setItem('coc_llam', String(llamadas));

  setTimeout(function () { location.reload(); }, 25000);
})();
</script>

<?php pie_panel(); ?>
