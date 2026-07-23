<?php
require_once __DIR__ . '/comun.php';

$u = requiere_dueno();
$negocio = negocio_por_id((int) $u['negocio_id']);
$negocioId = (int) $negocio['id'];
$moneda = (string) $negocio['moneda'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_token();
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'crear') {
        $codigo = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['codigo'] ?? '')));
        $codigo = mb_substr($codigo, 0, 30);
        $tipo   = ($_POST['tipo'] ?? 'porcentaje') === 'monto' ? 'monto' : 'porcentaje';
        $valor  = max(0.0, (float) ($_POST['valor'] ?? 0));
        $min    = max(0.0, (float) ($_POST['min_pedido'] ?? 0));
        $vence  = trim((string) ($_POST['vence'] ?? '')) ?: null;

        if ($codigo === '') {
            avisar('Escribí un código (solo letras y números).', 'error');
        } elseif ($tipo === 'porcentaje' && $valor > 100) {
            avisar('El porcentaje no puede ser mayor a 100.', 'error');
        } elseif ($valor <= 0) {
            avisar('El valor del descuento debe ser mayor a 0.', 'error');
        } elseif (una('SELECT id FROM cupones WHERE negocio_id=? AND codigo=?', [$negocioId, $codigo])) {
            avisar('Ya existe un cupón con ese código.', 'error');
        } else {
            consulta('INSERT INTO cupones (negocio_id, codigo, tipo, valor, min_pedido, vence) VALUES (?,?,?,?,?,?)',
                     [$negocioId, $codigo, $tipo, $valor, $min, $vence]);
            avisar('Cupón ' . $codigo . ' creado.');
        }
        ir('admin/cupon.php');
    }

    if ($accion === 'activar') {
        consulta('UPDATE cupones SET activo = 1 - activo WHERE id=? AND negocio_id=?',
                 [entero($_POST['id'] ?? 0), $negocioId]);
        ir('admin/cupon.php');
    }

    if ($accion === 'borrar') {
        consulta('DELETE FROM cupones WHERE id=? AND negocio_id=?', [entero($_POST['id'] ?? 0), $negocioId]);
        avisar('Cupón eliminado.');
        ir('admin/cupon.php');
    }
}

$cupones = todas('SELECT * FROM cupones WHERE negocio_id=? ORDER BY activo DESC, id DESC', [$negocioId]);
$hoy = date('Y-m-d');

cabecera_panel('Cupones', 'cupones', $negocio);
?>

<div class="bloque">
  <h2>Cupones de descuento</h2>
  <p class="ayuda">
    El cliente escribe el código al pagar y el descuento se aplica sobre el subtotal.
    Podés dar un <strong>porcentaje</strong> (ej. 10%) o un <strong>monto fijo</strong> (ej. L50),
    exigir un <strong>mínimo de pedido</strong> y ponerle <strong>vencimiento</strong>.
    Difundilos como <a href="<?= url('admin/promocion.php') ?>">promoción</a> en el menú.
  </p>

  <?php if (!$cupones): ?>
    <p class="ayuda">Aún no hay cupones.</p>
  <?php else: ?>
    <table class="tabla">
      <thead><tr><th>Código</th><th>Descuento</th><th>Mínimo</th><th>Vence</th><th style="width:80px">Estado</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($cupones as $c):
        $vencido = $c['vence'] !== null && $c['vence'] < $hoy;
        $desc = $c['tipo'] === 'monto'
              ? dinero((float) $c['valor'], $moneda)
              : rtrim(rtrim(number_format((float) $c['valor'], 2), '0'), '.') . '%';
      ?>
        <tr<?= (int) $c['activo'] !== 1 || $vencido ? ' style="opacity:.55"' : '' ?>>
          <td><strong><?= e($c['codigo']) ?></strong></td>
          <td><?= e($desc) ?></td>
          <td><?= (float) $c['min_pedido'] > 0 ? dinero((float) $c['min_pedido'], $moneda) : '—' ?></td>
          <td>
            <?= $c['vence'] ? e($c['vence']) : 'Sin límite' ?>
            <?php if ($vencido): ?><span class="sello sello--rojo" style="margin-left:6px">Vencido</span><?php endif; ?>
          </td>
          <td>
            <button class="mini <?= (int) $c['activo'] === 1 ? 'mini--activo' : '' ?>" type="submit" form="act<?= (int) $c['id'] ?>">
              <?= (int) $c['activo'] === 1 ? 'Activo' : 'Pausado' ?>
            </button>
          </td>
          <td class="ancho-acciones">
            <button class="mini mini--peligro" type="submit" form="del<?= (int) $c['id'] ?>"
                    onclick="return confirm('¿Eliminar el cupón <?= e($c['codigo']) ?>?')">Eliminar</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php foreach ($cupones as $c): ?>
      <form id="act<?= (int) $c['id'] ?>" method="post" style="display:none">
        <input type="hidden" name="token" value="<?= e(token()) ?>">
        <input type="hidden" name="accion" value="activar">
        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
      </form>
      <form id="del<?= (int) $c['id'] ?>" method="post" style="display:none">
        <input type="hidden" name="token" value="<?= e(token()) ?>">
        <input type="hidden" name="accion" value="borrar">
        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
      </form>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="bloque">
  <h2>Nuevo cupón</h2>
  <form method="post">
    <input type="hidden" name="token" value="<?= e(token()) ?>">
    <input type="hidden" name="accion" value="crear">
    <div class="dos">
      <div class="campo" style="margin-top:0">
        <label class="campo__rotulo" for="codigo">Código</label>
        <input id="codigo" name="codigo" maxlength="30" placeholder="LORO10"
               style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')">
      </div>
      <div class="campo" style="margin-top:0">
        <label class="campo__rotulo" for="tipo">Tipo de descuento</label>
        <select id="tipo" name="tipo">
          <option value="porcentaje">Porcentaje (%)</option>
          <option value="monto">Monto fijo (<?= e($moneda) ?>)</option>
        </select>
      </div>
    </div>
    <div class="dos">
      <div class="campo">
        <label class="campo__rotulo" for="valor">Valor</label>
        <input id="valor" name="valor" type="number" min="0" step="0.01" value="10">
      </div>
      <div class="campo">
        <label class="campo__rotulo" for="min_pedido">Mínimo de pedido (opcional)</label>
        <input id="min_pedido" name="min_pedido" type="number" min="0" step="0.01" value="0">
      </div>
    </div>
    <div class="campo">
      <label class="campo__rotulo" for="vence">Vence (opcional)</label>
      <input id="vence" name="vence" type="date">
    </div>
    <div class="pie" style="border-top:0"><button class="accion" type="submit"><span>Crear cupón</span><span>+</span></button></div>
  </form>
</div>

<?php pie_panel(); ?>
