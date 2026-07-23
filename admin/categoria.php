<?php
require_once __DIR__ . '/comun.php';

$u = requiere_dueno();
$negocio = negocio_por_id((int) $u['negocio_id']);
$negocioId = (int) $negocio['id'];

/** Ids de categorias principales (pueden ser padre). */
function ids_principales(int $negocioId): array
{
    $out = [];
    foreach (todas('SELECT id FROM categorias WHERE negocio_id = ? AND padre_id IS NULL', [$negocioId]) as $r) {
        $out[] = (int) $r['id'];
    }
    return $out;
}

/** ¿La categoria tiene subcategorias? */
function tiene_hijos(int $id, int $negocioId): bool
{
    return una('SELECT id FROM categorias WHERE padre_id = ? AND negocio_id = ?', [$id, $negocioId]) !== null;
}

/** Valida un padre_id propuesto: 0 => principal; si no, debe ser una principal
 *  del negocio, distinta de $propia, y $propia no puede tener hijos (2 niveles). */
function padre_valido($propuesto, int $propia, int $negocioId): ?int
{
    $p = (int) $propuesto;
    if ($p <= 0) {
        return null;
    }
    if ($p === $propia || !in_array($p, ids_principales($negocioId), true)) {
        return null;
    }
    if ($propia > 0 && tiene_hijos($propia, $negocioId)) {
        return null; // una categoria con hijos no puede volverse subcategoria
    }
    return $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_token();
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'crear') {
        $nombre = mb_substr(trim((string) ($_POST['nombre'] ?? '')), 0, 80);
        if ($nombre !== '') {
            $padre = padre_valido($_POST['padre_id'] ?? 0, 0, $negocioId);
            $sig = (int) (una('SELECT COALESCE(MAX(orden),0)+1 AS s FROM categorias WHERE negocio_id=?',
                              [$negocioId])['s'] ?? 1);
            consulta('INSERT INTO categorias (negocio_id, padre_id, nombre, orden) VALUES (?,?,?,?)',
                     [$negocioId, $padre, $nombre, $sig]);
            avisar('Categoria agregada.');
        } else {
            avisar('Escribi un nombre para la categoria.', 'error');
        }
        ir('admin/categoria.php');
    }

    if ($accion === 'guardar') {
        $ids     = (array) ($_POST['id'] ?? []);
        $nombres = (array) ($_POST['nombre'] ?? []);
        $ordenes = (array) ($_POST['orden'] ?? []);
        $padres  = (array) ($_POST['padre_id'] ?? []);
        foreach ($ids as $i => $id) {
            $id = entero($id);
            $nombre = mb_substr(trim((string) ($nombres[$i] ?? '')), 0, 80);
            if ($id <= 0 || $nombre === '') {
                continue;
            }
            $padre = padre_valido($padres[$i] ?? 0, $id, $negocioId);
            consulta('UPDATE categorias SET nombre=?, orden=?, padre_id=? WHERE id=? AND negocio_id=?',
                     [$nombre, entero($ordenes[$i] ?? 0), $padre, $id, $negocioId]);
        }
        avisar('Categorias guardadas.');
        ir('admin/categoria.php');
    }

    if ($accion === 'borrar') {
        $id = entero($_POST['id'] ?? 0);
        $prod = (int) (una('SELECT COUNT(*) AS n FROM productos WHERE categoria_id=? AND negocio_id=?',
                           [$id, $negocioId])['n'] ?? 0);
        if ($prod > 0) {
            avisar('Esa categoria tiene ' . $prod . ' platillos. Movelos antes de borrarla.', 'error');
        } elseif (tiene_hijos($id, $negocioId)) {
            avisar('Esa categoria tiene subcategorias. Borra o mueve las subcategorias primero.', 'error');
        } else {
            consulta('DELETE FROM categorias WHERE id=? AND negocio_id=?', [$id, $negocioId]);
            avisar('Categoria eliminada.');
        }
        ir('admin/categoria.php');
    }
}

// Arbol para mostrar (principales con sus subcategorias) + conteo de platillos.
$arbol = categorias_arbol($negocioId);
$conteo = [];
foreach (todas('SELECT categoria_id, COUNT(*) n FROM productos WHERE negocio_id=? GROUP BY categoria_id', [$negocioId]) as $r) {
    $conteo[(int) $r['categoria_id']] = (int) $r['n'];
}
$principales = array_filter($arbol, static function ($c) { return (int) $c['nivel'] === 0; });

cabecera_panel('Categorias', 'categorias', $negocio);
?>

<div class="bloque">
  <h2>Categorias del menu</h2>
  <p class="ayuda">
    Son las secciones de tu carta y cada una es su propio panel. El orden recomendado sigue
    cómo se come: <strong>Entradas → Sopas → Ensaladas → Platos fuertes → Guarniciones →
    Postres → Bebidas</strong>. Podés crear <strong>subcategorías</strong> (ej: Bebidas →
    Naturales / Cervezas y tragos) eligiendo una categoría padre.
  </p>

  <form method="post">
    <input type="hidden" name="token" value="<?= e(token()) ?>">
    <input type="hidden" name="accion" value="guardar">
    <table class="tabla">
      <thead>
        <tr><th>Categoria</th><th style="width:180px">Es subcategoría de</th><th style="width:70px">Orden</th><th style="width:70px">Platillos</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$arbol): ?>
        <tr><td colspan="5" style="color:var(--suave)">Aun no hay categorias. Agrega la primera abajo.</td></tr>
      <?php endif; ?>
      <?php foreach ($arbol as $c): $es = (int) $c['nivel'] === 1; ?>
        <tr>
          <td>
            <input type="hidden" name="id[]" value="<?= (int) $c['id'] ?>">
            <input name="nombre[]" value="<?= e($c['nombre']) ?>" maxlength="80"
                   style="<?= $es ? 'margin-left:18px' : 'font-weight:600' ?>">
          </td>
          <td>
            <select name="padre_id[]">
              <option value="0"<?= $c['padre_id'] === null ? ' selected' : '' ?>>— Principal —</option>
              <?php foreach ($principales as $pp): if ((int) $pp['id'] === (int) $c['id']) { continue; } ?>
                <option value="<?= (int) $pp['id'] ?>"<?= (int) $c['padre_id'] === (int) $pp['id'] ? ' selected' : '' ?>>
                  <?= e($pp['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input name="orden[]" type="number" min="0" step="1" value="<?= (int) $c['orden'] ?>"></td>
          <td><?= $conteo[(int) $c['id']] ?? 0 ?></td>
          <td class="ancho-acciones">
            <button class="mini mini--peligro" type="submit" form="del<?= (int) $c['id'] ?>"
                    onclick="return confirm('¿Eliminar <?= e($c['nombre']) ?>?')">Eliminar</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($arbol): ?>
      <div class="pie" style="border-top:1px dashed var(--linea)">
        <button class="accion" type="submit"><span>Guardar categorias</span><span>&rarr;</span></button>
      </div>
    <?php endif; ?>
  </form>

  <?php foreach ($arbol as $c): ?>
    <form id="del<?= (int) $c['id'] ?>" method="post" style="display:none">
      <input type="hidden" name="token" value="<?= e(token()) ?>">
      <input type="hidden" name="accion" value="borrar">
      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
    </form>
  <?php endforeach; ?>
</div>

<?php
$sugeridas = ['Entradas', 'Sopas', 'Ensaladas', 'Platos fuertes', 'Guarniciones', 'Postres', 'Bebidas'];
$existentes = array_map(static function ($c) { return mb_strtolower($c['nombre']); }, $arbol);
$faltan = array_filter($sugeridas, static function ($s) use ($existentes) {
    return !in_array(mb_strtolower($s), $existentes, true);
});
?>
<?php if ($faltan): ?>
<div class="bloque">
  <h2>Categorías sugeridas</h2>
  <p class="ayuda">Agregá las secciones estándar de un menú con un toque.</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach ($faltan as $s): ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="token" value="<?= e(token()) ?>">
        <input type="hidden" name="accion" value="crear">
        <input type="hidden" name="nombre" value="<?= e($s) ?>">
        <button class="mini mini--activo" type="submit">+ <?= e($s) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="bloque">
  <h2>Agregar categoria</h2>
  <form method="post">
    <input type="hidden" name="token" value="<?= e(token()) ?>">
    <input type="hidden" name="accion" value="crear">
    <div class="dos">
      <div class="campo" style="margin-top:0">
        <label class="campo__rotulo" for="nueva">Nombre</label>
        <input id="nueva" name="nombre" maxlength="80" placeholder="Tragos, Naturales, Del mar...">
      </div>
      <div class="campo" style="margin-top:0">
        <label class="campo__rotulo" for="padre">¿Subcategoría de? (opcional)</label>
        <select id="padre" name="padre_id">
          <option value="0">— Es principal —</option>
          <?php foreach ($principales as $pp): ?>
            <option value="<?= (int) $pp['id'] ?>"><?= e($pp['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="pie" style="border-top:0"><button class="accion" type="submit"><span>Agregar</span><span>+</span></button></div>
  </form>
</div>

<?php pie_panel(); ?>
