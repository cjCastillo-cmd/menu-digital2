<?php
require_once __DIR__ . '/comun.php';

$u = requiere_dueno();
$negocio = negocio_por_id((int) $u['negocio_id']);
$negocioId = (int) $negocio['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_token();
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'guardar') {
        $ids     = (array) ($_POST['id'] ?? []);
        $nombres = (array) ($_POST['nombre'] ?? []);
        $precios = (array) ($_POST['precio'] ?? []);
        $cats    = (array) ($_POST['categoria'] ?? []);
        $cambios = 0;

        foreach ($ids as $i => $id) {
            $id = entero($id);
            $nombre = trim((string) ($nombres[$i] ?? ''));
            if ($id <= 0 || $nombre === '') {
                continue;
            }
            consulta(
                'UPDATE productos
                    SET nombre = ?, precio = ?, categoria_id = ?
                  WHERE id = ? AND negocio_id = ?',
                [
                    mb_substr($nombre, 0, 120),
                    max(0, decimal($precios[$i] ?? 0)),
                    entero($cats[$i] ?? 0),
                    $id,
                    $negocioId,
                ]
            );
            $cambios++;
        }
        avisar($cambios . ' platillos guardados. Ya se ven en el menu.');
        ir('admin/index.php');
    }

    if ($accion === 'disponible') {
        consulta(
            'UPDATE productos SET disponible = 1 - disponible WHERE id = ? AND negocio_id = ?',
            [entero($_POST['id'] ?? 0), $negocioId]
        );
        avisar('Disponibilidad cambiada.');
        ir('admin/index.php');
    }

    if ($accion === 'borrar') {
        consulta('DELETE FROM productos WHERE id = ? AND negocio_id = ?',
                 [entero($_POST['id'] ?? 0), $negocioId]);
        avisar('Platillo eliminado.');
        ir('admin/index.php');
    }
}

$cats = categorias_arbol($negocioId);
$productos = todas(
    'SELECT p.*, c.nombre AS categoria
       FROM productos p
       JOIN categorias c ON c.id = p.categoria_id
      WHERE p.negocio_id = ?
      ORDER BY c.orden, p.orden, p.nombre',
    [$negocioId]
);

$agotados = 0;
foreach ($productos as $p) {
    if ((int) $p['disponible'] !== 1) { $agotados++; }
}

cabecera_panel('Carta', 'carta', $negocio);
?>

<div class="bloque">
  <h2>Platillos</h2>
  <p class="ayuda">
    <?= count($productos) ?> en la carta<?= $agotados ? ', ' . $agotados . ' agotados' : '' ?>.
    Cambiá nombre, categoría o precio y guardá al final. Marcar agotado quita el platillo
    del menú sin borrarlo.
  </p>

  <form method="post">
    <input type="hidden" name="token" value="<?= e(token()) ?>">
    <input type="hidden" name="accion" value="guardar">

    <table class="tabla">
      <thead>
        <tr><th>Platillo</th><th>Categoría</th><th>Precio</th><th></th></tr>
      </thead>
      <tbody>
      <?php $catActual = null; foreach ($productos as $p): ?>
        <?php if ($p['categoria'] !== $catActual): $catActual = $p['categoria']; ?>
          <tr>
            <td colspan="4" style="padding-top:16px">
              <span class="rotulo"><?= e($catActual) ?></span>
            </td>
          </tr>
        <?php endif; ?>
        <tr>
          <td>
            <input type="hidden" name="id[]" value="<?= (int) $p['id'] ?>">
            <input name="nombre[]" value="<?= e($p['nombre']) ?>" maxlength="120">
          </td>
          <td style="width:160px">
            <select name="categoria[]">
              <?php foreach ($cats as $c): ?>
                <option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === (int) $p['categoria_id'] ? ' selected' : '' ?>>
                  <?= ((int) $c['nivel'] === 1 ? '— ' : '') . e($c['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td class="ancho-precio">
            <input name="precio[]" type="number" min="0" step="1" value="<?= (int) $p['precio'] ?>">
          </td>
          <td class="ancho-acciones">
            <a class="mini" href="<?= url('admin/producto.php?id=' . (int) $p['id']) ?>">Opciones</a>
            <button class="mini<?= (int) $p['disponible'] === 1 ? ' mini--activo' : ' mini--peligro' ?>"
                    type="submit" form="f<?= (int) $p['id'] ?>">
              <?= (int) $p['disponible'] === 1 ? 'Disponible' : 'Agotado' ?>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div class="pie" style="border-top:1px dashed var(--linea)">
      <button class="accion" type="submit"><span>Guardar cambios</span><span>&rarr;</span></button>
      <a class="mini" href="<?= url('admin/producto.php') ?>" style="padding:12px 16px">Nuevo platillo</a>
    </div>
  </form>

  <?php foreach ($productos as $p): ?>
    <form id="f<?= (int) $p['id'] ?>" method="post" style="display:none">
      <input type="hidden" name="token" value="<?= e(token()) ?>">
      <input type="hidden" name="accion" value="disponible">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    </form>
  <?php endforeach; ?>
</div>

<?php pie_panel(); ?>
