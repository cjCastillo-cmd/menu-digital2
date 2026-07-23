<?php
require_once __DIR__ . '/comun.php';

$u = requiere_dueno();
$negocio = negocio_por_id((int) $u['negocio_id']);
$negocioId = (int) $negocio['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_token();
    $accion = (string) ($_POST['accion'] ?? '');

    // Crear una categoria nueva al final.
    if ($accion === 'crear') {
        $nombre = mb_substr(trim((string) ($_POST['nombre'] ?? '')), 0, 80);
        if ($nombre !== '') {
            $sig = (int) (una('SELECT COALESCE(MAX(orden),0)+1 AS s FROM categorias WHERE negocio_id=?',
                              [$negocioId])['s'] ?? 1);
            consulta('INSERT INTO categorias (negocio_id, nombre, orden) VALUES (?,?,?)',
                     [$negocioId, $nombre, $sig]);
            avisar('Categoria agregada. Ya podes asignarle platillos.');
        } else {
            avisar('Escribi un nombre para la categoria.', 'error');
        }
        ir('admin/categoria.php');
    }

    // Guardar nombres y orden de todas las categorias.
    if ($accion === 'guardar') {
        $ids     = (array) ($_POST['id'] ?? []);
        $nombres = (array) ($_POST['nombre'] ?? []);
        $ordenes = (array) ($_POST['orden'] ?? []);
        foreach ($ids as $i => $id) {
            $id = entero($id);
            $nombre = mb_substr(trim((string) ($nombres[$i] ?? '')), 0, 80);
            if ($id <= 0 || $nombre === '') {
                continue;
            }
            consulta('UPDATE categorias SET nombre=?, orden=? WHERE id=? AND negocio_id=?',
                     [$nombre, entero($ordenes[$i] ?? 0), $id, $negocioId]);
        }
        avisar('Categorias guardadas. El orden se refleja en el menu.');
        ir('admin/categoria.php');
    }

    // Eliminar una categoria (solo si no tiene platillos).
    if ($accion === 'borrar') {
        $id = entero($_POST['id'] ?? 0);
        $n = (int) (una('SELECT COUNT(*) AS n FROM productos WHERE categoria_id=? AND negocio_id=?',
                        [$id, $negocioId])['n'] ?? 0);
        if ($n > 0) {
            avisar('Esa categoria tiene ' . $n . ' platillos. Movelos a otra categoria antes de borrarla.', 'error');
        } else {
            consulta('DELETE FROM categorias WHERE id=? AND negocio_id=?', [$id, $negocioId]);
            avisar('Categoria eliminada.');
        }
        ir('admin/categoria.php');
    }
}

$cats = todas(
    'SELECT c.*,
            (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id) AS platillos
       FROM categorias c
      WHERE c.negocio_id = ?
      ORDER BY c.orden, c.nombre',
    [$negocioId]
);

cabecera_panel('Categorias', 'categorias', $negocio);
?>

<div class="bloque">
  <h2>Categorias del menu</h2>
  <p class="ayuda">
    Son las secciones de tu carta. Cada negocio arma las suyas: podes separar
    Bebidas en "Tragos", "Naturales" y "Gaseosas", o crear "Entradas frias" y
    "Entradas calientes". El numero de orden decide como se leen de arriba a abajo.
  </p>

  <form method="post">
    <input type="hidden" name="token" value="<?= e(token()) ?>">
    <input type="hidden" name="accion" value="guardar">
    <table class="tabla">
      <thead>
        <tr><th>Categoria</th><th style="width:80px">Orden</th><th style="width:90px">Platillos</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$cats): ?>
        <tr><td colspan="4" style="color:var(--suave)">Aun no hay categorias. Agrega la primera abajo.</td></tr>
      <?php endif; ?>
      <?php foreach ($cats as $c): ?>
        <tr>
          <td>
            <input type="hidden" name="id[]" value="<?= (int) $c['id'] ?>">
            <input name="nombre[]" value="<?= e($c['nombre']) ?>" maxlength="80">
          </td>
          <td><input name="orden[]" type="number" min="0" step="1" value="<?= (int) $c['orden'] ?>"></td>
          <td><?= (int) $c['platillos'] ?></td>
          <td class="ancho-acciones">
            <button class="mini mini--peligro" type="submit" form="del<?= (int) $c['id'] ?>"
                    onclick="return confirm('¿Eliminar la categoria <?= e($c['nombre']) ?>?')">
              Eliminar
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($cats): ?>
      <div class="pie" style="border-top:1px dashed var(--linea)">
        <button class="accion" type="submit"><span>Guardar categorias</span><span>&rarr;</span></button>
      </div>
    <?php endif; ?>
  </form>

  <?php foreach ($cats as $c): ?>
    <form id="del<?= (int) $c['id'] ?>" method="post" style="display:none">
      <input type="hidden" name="token" value="<?= e(token()) ?>">
      <input type="hidden" name="accion" value="borrar">
      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
    </form>
  <?php endforeach; ?>
</div>

<div class="bloque">
  <h2>Agregar categoria</h2>
  <form method="post">
    <input type="hidden" name="token" value="<?= e(token()) ?>">
    <input type="hidden" name="accion" value="crear">
    <div class="dos">
      <div class="campo" style="margin-top:0">
        <label class="campo__rotulo" for="nueva">Nombre de la categoria</label>
        <input id="nueva" name="nombre" maxlength="80" placeholder="Tragos, Naturales, Entradas frias...">
      </div>
      <div style="display:flex;align-items:flex-end">
        <button class="accion" type="submit" style="width:100%"><span>Agregar</span><span>+</span></button>
      </div>
    </div>
  </form>
</div>

<?php pie_panel(); ?>
