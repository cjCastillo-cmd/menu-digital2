<?php
require_once __DIR__ . '/comun.php';
require_once __DIR__ . '/../app/imagen.php';

$u = requiere_dueno();
$negocio = negocio_por_id((int) $u['negocio_id']);
$negocioId = (int) $negocio['id'];

$id = entero($_GET['id'] ?? 0);
$producto = null;

if ($id > 0) {
    $producto = una('SELECT * FROM productos WHERE id = ? AND negocio_id = ?', [$id, $negocioId]);
    if (!$producto) {
        avisar('Ese platillo no existe.', 'error');
        ir('admin/index.php');
    }
}

$cats   = categorias($negocioId);
$grupos = todas('SELECT * FROM grupos WHERE negocio_id = ? ORDER BY orden, nombre', [$negocioId]);

$asignados = [];
if ($id > 0) {
    foreach (todas('SELECT grupo_id FROM producto_grupo WHERE producto_id = ?', [$id]) as $g) {
        $asignados[] = (int) $g['grupo_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_token();

    // --- Foto: subir nueva, conservar la actual o quitarla ---
    $imagenActual = $producto['imagen'] ?? null;
    $imagen = $imagenActual;
    $errorFoto = null;
    try {
        $nueva = guardar_imagen_subida($_FILES['imagen'] ?? []);
        if ($nueva !== null) {
            borrar_imagen_producto($imagenActual); // reemplaza la anterior
            $imagen = $nueva;
        } elseif (!empty($_POST['quitar_imagen'])) {
            borrar_imagen_producto($imagenActual);
            $imagen = null;
        }
    } catch (RuntimeException $e) {
        $errorFoto = $e->getMessage();
    }

    $datos = [
        'categoria_id' => entero($_POST['categoria_id'] ?? 0),
        'nombre'       => mb_substr(trim((string) ($_POST['nombre'] ?? '')), 0, 120),
        'descripcion'  => mb_substr(trim((string) ($_POST['descripcion'] ?? '')), 0, 400),
        'imagen'       => $imagen,
        'precio'       => max(0, decimal($_POST['precio'] ?? 0)),
        'disponible'   => isset($_POST['disponible']) ? 1 : 0,
        'destacado'    => isset($_POST['destacado']) ? 1 : 0,
        'mitades'      => isset($_POST['mitades']) ? 1 : 0,
        'etiquetas'    => mb_substr(trim((string) ($_POST['etiquetas'] ?? '')), 0, 120),
        'orden'        => entero($_POST['orden'] ?? 0),
    ];

    if ($errorFoto !== null) {
        avisar($errorFoto, 'error');
    } elseif ($datos['nombre'] === '' || $datos['categoria_id'] <= 0) {
        avisar('El platillo necesita nombre y categoria.', 'error');
    } else {
        if ($id > 0) {
            consulta(
                'UPDATE productos
                    SET categoria_id=?, nombre=?, descripcion=?, imagen=?, precio=?, disponible=?,
                        destacado=?, mitades=?, etiquetas=?, orden=?
                  WHERE id=? AND negocio_id=?',
                array_merge(array_values($datos), [$id, $negocioId])
            );
        } else {
            consulta(
                'INSERT INTO productos
                   (negocio_id, categoria_id, nombre, descripcion, imagen, precio, disponible,
                    destacado, mitades, etiquetas, orden)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                array_merge([$negocioId], array_values($datos))
            );
            $id = (int) db()->lastInsertId();
        }

        consulta('DELETE FROM producto_grupo WHERE producto_id = ?', [$id]);
        $orden = 1;
        foreach ((array) ($_POST['grupos'] ?? []) as $gid) {
            $gid = entero($gid);
            $existe = una('SELECT id FROM grupos WHERE id = ? AND negocio_id = ?', [$gid, $negocioId]);
            if ($existe) {
                consulta('INSERT INTO producto_grupo (producto_id, grupo_id, orden) VALUES (?,?,?)',
                         [$id, $gid, $orden++]);
            }
        }

        avisar('Platillo guardado.');
        ir('admin/index.php');
    }
}

$v = function (string $campo, $porDefecto = '') use ($producto) {
    return $producto[$campo] ?? $porDefecto;
};

cabecera_panel($id > 0 ? 'Editar platillo' : 'Nuevo platillo', 'carta', $negocio);
?>

<div class="bloque">
  <h2><?= $id > 0 ? e($producto['nombre']) : 'Nuevo platillo' ?></h2>
  <p class="ayuda">
    El precio es el del tamaño base. Los grupos de opciones le suman encima:
    un tamaño grande, una masa rellena, extras.
  </p>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="token" value="<?= e(token()) ?>">

    <div class="dos">
      <div class="campo" style="margin-top:0">
        <label class="campo__rotulo" for="nombre">Nombre</label>
        <input id="nombre" name="nombre" maxlength="120" required value="<?= e($v('nombre')) ?>">
      </div>
      <div class="campo" style="margin-top:0">
        <label class="campo__rotulo" for="categoria_id">Categoría</label>
        <select id="categoria_id" name="categoria_id">
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === (int) $v('categoria_id', 0) ? ' selected' : '' ?>>
              <?= e($c['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="campo">
      <label class="campo__rotulo" for="descripcion">Descripción</label>
      <textarea id="descripcion" name="descripcion" maxlength="400"
                placeholder="Los ingredientes, como los diría un mesero"><?= e($v('descripcion')) ?></textarea>
    </div>

    <div class="campo">
      <label class="campo__rotulo" for="imagen">Foto del platillo</label>
      <?php $fotoActual = url_imagen_producto($v('imagen') ?: null); ?>
      <?php if ($fotoActual): ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
          <img src="<?= e($fotoActual) ?>" alt="" width="80" height="80"
               style="border:2px solid var(--linea);object-fit:cover">
          <label style="margin:0">
            <input type="checkbox" name="quitar_imagen" style="width:auto"> Quitar la foto
          </label>
        </div>
      <?php endif; ?>
      <input id="imagen" name="imagen" type="file" accept="image/jpeg,image/png,image/webp">
      <p class="ayuda" style="margin-top:6px">
        Se recorta al centro y queda cuadrada. Ideal una foto de frente y bien iluminada.
      </p>
    </div>

    <div class="dos">
      <div class="campo">
        <label class="campo__rotulo" for="precio">Precio base</label>
        <input id="precio" name="precio" type="number" min="0" step="1" value="<?= (int) $v('precio', 0) ?>">
      </div>
      <div class="campo">
        <label class="campo__rotulo" for="etiquetas">Etiquetas</label>
        <input id="etiquetas" name="etiquetas" maxlength="120" value="<?= e($v('etiquetas')) ?>"
               placeholder="picante, vegetariana">
      </div>
    </div>

    <div class="campo">
      <span class="campo__rotulo">Marcas</span>
      <label style="display:block;margin-bottom:6px">
        <input type="checkbox" name="disponible" style="width:auto"
               <?= $id === 0 || (int) $v('disponible', 1) === 1 ? 'checked' : '' ?>> Disponible
      </label>
      <label style="display:block;margin-bottom:6px">
        <input type="checkbox" name="destacado" style="width:auto"
               <?= (int) $v('destacado', 0) === 1 ? 'checked' : '' ?>> Favorito de la casa
      </label>
      <label style="display:block">
        <input type="checkbox" name="mitades" style="width:auto"
               <?= (int) $v('mitades', 0) === 1 ? 'checked' : '' ?>> Se puede pedir mitad y mitad
      </label>
    </div>

    <div class="campo">
      <span class="campo__rotulo">Grupos de opciones</span>
      <?php foreach ($grupos as $g): ?>
        <label style="display:block;margin-bottom:6px">
          <input type="checkbox" name="grupos[]" value="<?= (int) $g['id'] ?>" style="width:auto"
                 <?= in_array((int) $g['id'], $asignados, true) ? 'checked' : '' ?>>
          <?= e($g['nombre']) ?>
          <span style="color:var(--suave);font-size:11px">
            (<?= $g['tipo'] === 'unico' ? 'elegir uno' : 'varios' ?><?= (int) $g['obligatorio'] === 1 ? ', obligatorio' : '' ?><?= (int) $g['escala_por_tamano'] === 1 ? ', escala por tamaño' : '' ?>)
          </span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="pie">
      <button class="accion" type="submit"><span>Guardar</span><span>&rarr;</span></button>
      <a class="mini" href="<?= url('admin/index.php') ?>" style="padding:12px 16px">Cancelar</a>
    </div>
  </form>
</div>

<?php if ($id > 0): ?>
<div class="bloque">
  <h2>Eliminar</h2>
  <p class="ayuda">Si solo se acabó por hoy, marcalo agotado en la carta en lugar de borrarlo.</p>
  <form method="post" action="<?= url('admin/index.php') ?>"
        onsubmit="return confirm('¿Eliminar este platillo de la carta?')">
    <input type="hidden" name="token" value="<?= e(token()) ?>">
    <input type="hidden" name="accion" value="borrar">
    <input type="hidden" name="id" value="<?= $id ?>">
    <button class="mini mini--peligro" type="submit" style="padding:10px 14px">Eliminar platillo</button>
  </form>
</div>
<?php endif; ?>

<?php pie_panel(); ?>
