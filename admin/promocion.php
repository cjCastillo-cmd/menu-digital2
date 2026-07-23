<?php
require_once __DIR__ . '/comun.php';

$u = requiere_dueno();
$negocio = negocio_por_id((int) $u['negocio_id']);
$negocioId = (int) $negocio['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_token();
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'crear') {
        $titulo = mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 80);
        $texto  = mb_substr(trim((string) ($_POST['texto'] ?? '')), 0, 200);
        if ($titulo !== '') {
            $sig = (int) (una('SELECT COALESCE(MAX(orden),0)+1 s FROM promociones WHERE negocio_id=?', [$negocioId])['s'] ?? 1);
            consulta('INSERT INTO promociones (negocio_id, titulo, texto, orden) VALUES (?,?,?,?)',
                     [$negocioId, $titulo, $texto ?: null, $sig]);
            avisar('Promoción agregada. Ya se ve en el menú.');
        } else {
            avisar('Escribí un título para la promoción.', 'error');
        }
        ir('admin/promocion.php');
    }

    if ($accion === 'activar') {
        consulta('UPDATE promociones SET activo = 1 - activo WHERE id=? AND negocio_id=?',
                 [entero($_POST['id'] ?? 0), $negocioId]);
        ir('admin/promocion.php');
    }

    if ($accion === 'borrar') {
        consulta('DELETE FROM promociones WHERE id=? AND negocio_id=?', [entero($_POST['id'] ?? 0), $negocioId]);
        avisar('Promoción eliminada.');
        ir('admin/promocion.php');
    }
}

$promos = todas('SELECT * FROM promociones WHERE negocio_id=? ORDER BY orden, id', [$negocioId]);

cabecera_panel('Promociones', 'promociones', $negocio);
?>

<div class="bloque">
  <h2>Promociones del menú</h2>
  <p class="ayuda">
    Aparecen como avisos arriba del menú para llamar la atención. Ejemplos:
    "🔥 Martes 2x1 en pizzas", "Envío gratis en pedidos de L400+", "10% con el código LORO10".
    Para dar un descuento real, creá un <a href="<?= url('admin/cupon.php') ?>">cupón</a>.
  </p>

  <?php if (!$promos): ?>
    <p class="ayuda">Aún no hay promociones.</p>
  <?php else: ?>
    <table class="tabla">
      <thead><tr><th>Promoción</th><th style="width:90px">Estado</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($promos as $p): ?>
        <tr>
          <td>
            <strong><?= e($p['titulo']) ?></strong>
            <?php if ($p['texto']): ?><div style="color:var(--suave);font-size:12px"><?= e($p['texto']) ?></div><?php endif; ?>
          </td>
          <td>
            <button class="mini <?= (int) $p['activo'] === 1 ? 'mini--activo' : '' ?>" type="submit" form="act<?= (int) $p['id'] ?>">
              <?= (int) $p['activo'] === 1 ? 'Activa' : 'Oculta' ?>
            </button>
          </td>
          <td class="ancho-acciones">
            <button class="mini mini--peligro" type="submit" form="del<?= (int) $p['id'] ?>"
                    onclick="return confirm('¿Eliminar esta promoción?')">Eliminar</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php foreach ($promos as $p): ?>
      <form id="act<?= (int) $p['id'] ?>" method="post" style="display:none">
        <input type="hidden" name="token" value="<?= e(token()) ?>">
        <input type="hidden" name="accion" value="activar">
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      </form>
      <form id="del<?= (int) $p['id'] ?>" method="post" style="display:none">
        <input type="hidden" name="token" value="<?= e(token()) ?>">
        <input type="hidden" name="accion" value="borrar">
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      </form>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="bloque">
  <h2>Nueva promoción</h2>
  <form method="post">
    <input type="hidden" name="token" value="<?= e(token()) ?>">
    <input type="hidden" name="accion" value="crear">
    <div class="campo" style="margin-top:0">
      <label class="campo__rotulo" for="titulo">Título</label>
      <input id="titulo" name="titulo" maxlength="80" placeholder="Martes 2x1 en pizzas">
    </div>
    <div class="campo">
      <label class="campo__rotulo" for="texto">Detalle (opcional)</label>
      <input id="texto" name="texto" maxlength="200" placeholder="Válido solo los martes, en pizzas medianas y grandes.">
    </div>
    <div class="pie" style="border-top:0"><button class="accion" type="submit"><span>Agregar</span><span>+</span></button></div>
  </form>
</div>

<?php pie_panel(); ?>
