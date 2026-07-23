<?php
require_once __DIR__ . '/app/repo.php';

$slug    = isset($_GET['r']) ? trim((string) $_GET['r']) : NEGOCIO_POR_DEFECTO;
$negocio = negocio_por_slug($slug);

if (!$negocio) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Menu no encontrado</title>';
    echo '<p style="font-family:monospace;padding:40px;text-align:center">';
    echo 'No encontramos ese menu. Revisa el enlace o volve a escanear el codigo.</p>';
    exit;
}

$negocioId = (int) $negocio['id'];
$cat       = catalogo($negocioId);
$cats      = categorias($negocioId);
$abierto   = esta_abierto($negocioId);
$mesa      = isset($_GET['mesa']) ? substr(preg_replace('/[^0-9A-Za-z\-]/', '', $_GET['mesa']), 0, 10) : '';

$porCategoria = [];
foreach ($cat['productos'] as $id => $p) {
    $porCategoria[(int) $p['categoria_id']][] = $p;
}

$datosNavegador = [
    'negocio'  => [
        'nombre'   => $negocio['nombre'],
        'moneda'   => $negocio['moneda'],
        'impuesto' => (float) $negocio['impuesto'],
        'slug'     => $negocio['slug'],
    ],
    'catalogo' => catalogo_para_navegador($cat),
    'zonas'    => array_map(function ($z) {
        return ['nombre' => $z['nombre'], 'costo' => (float) $z['costo']];
    }, zonas($negocioId)),
    'mesa'     => $mesa,
    'abierto'  => $abierto,
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#211F1C">
<title><?= e($negocio['nombre']) ?> · Menú</title>
<meta name="description" content="Menú de <?= e($negocio['nombre']) ?>. Escaneá, elegí y enviá tu pedido.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/comanda.css') ?>">
</head>
<body>

<main class="envoltura">

  <header class="cabecera">
    <div class="cabecera__meta">
      <span class="rotulo">
        <?= $mesa !== '' ? 'Mesa ' . e($mesa) : 'Menú' ?> · <?= date('H:i') ?>
      </span>
      <span class="sello <?= $abierto ? 'sello--ambar' : 'sello--rojo' ?>">
        <?= $abierto ? 'Abierto' : 'Cerrado' ?>
      </span>
    </div>
    <h1 class="cabecera__nombre"><?= e($negocio['nombre']) ?></h1>
    <p class="cabecera__tagline"><?= e($negocio['tagline']) ?></p>

    <div class="modos" id="modos" role="group" aria-label="Tipo de pedido">
      <button class="modo" type="button" data-modo="mesa" aria-pressed="true">En mesa</button>
      <button class="modo" type="button" data-modo="llevar" aria-pressed="false">Para llevar</button>
      <button class="modo" type="button" data-modo="domicilio" aria-pressed="false">A domicilio</button>
    </div>
  </header>

  <nav class="categorias" id="categorias" aria-label="Categorías">
    <?php foreach ($cats as $i => $c): ?>
      <button class="chip" type="button" data-cat="<?= (int) $c['id'] ?>"
              aria-current="<?= $i === 0 ? 'true' : 'false' ?>"><?= e($c['nombre']) ?></button>
    <?php endforeach; ?>
  </nav>

  <?php foreach ($cats as $c):
      $items = $porCategoria[(int) $c['id']] ?? [];
      if (!$items) { continue; } ?>
    <section class="seccion" id="sec-<?= (int) $c['id'] ?>">
      <h2 class="seccion__titulo"><?= e($c['nombre']) ?></h2>
      <p class="seccion__nota"><?= count($items) ?> <?= count($items) === 1 ? 'opción' : 'opciones' ?></p>

      <?php foreach ($items as $p):
          $agotado = (int) $p['disponible'] !== 1;
          $desde   = !empty($p['grupos']) ? 'desde ' : ''; ?>
        <button class="platillo" type="button" data-prod="<?= (int) $p['id'] ?>" <?= $agotado ? 'disabled' : '' ?>>
          <div class="platillo__fila">
            <span class="platillo__nombre"><?= e($p['nombre']) ?></span>
            <span class="platillo__puntos"></span>
            <span class="platillo__precio"><?= $desde . dinero($p['precio'], $negocio['moneda']) ?></span>
          </div>
          <?php if ($p['descripcion']): ?>
            <p class="platillo__desc"><?= e($p['descripcion']) ?></p>
          <?php endif; ?>
          <?php if ($agotado || (int) $p['destacado'] === 1 || $p['etiquetas']): ?>
            <div class="platillo__marcas">
              <?php if ($agotado): ?><span class="marca" style="color:var(--rojo);border-color:var(--rojo)">Agotado</span><?php endif; ?>
              <?php if ((int) $p['destacado'] === 1): ?><span class="marca marca--favorito">Favorito</span><?php endif; ?>
              <?php foreach (array_filter(explode(',', (string) $p['etiquetas'])) as $et): ?>
                <span class="marca"><?= e(trim($et)) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </button>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>

  <p class="nota-pie">
    Precios en <?= e($negocio['moneda']) ?>. El ISV se calcula al cerrar el pedido.
    El pedido queda registrado en el sistema y se envía por WhatsApp para que la cocina lo confirme.
  </p>

</main>

<div class="barra" id="barra">
  <button class="barra__boton" id="verPedido">
    <span id="conteo">0</span>
    <span>Ver el pedido</span>
    <span id="totalBarra"><?= dinero(0, $negocio['moneda']) ?></span>
  </button>
</div>

<div class="velo" id="velo"></div>

<section class="hoja" id="hojaPlatillo" role="dialog" aria-modal="true" aria-labelledby="tituloPlatillo" hidden>
  <div class="hoja__barra">
    <h2 class="hoja__titulo" id="tituloPlatillo"></h2>
    <button class="cerrar" type="button" data-cerrar>Cerrar</button>
  </div>
  <p class="hoja__desc" id="descPlatillo"></p>
  <div id="cuerpoPlatillo"></div>
  <div class="campo">
    <label class="campo__rotulo" for="notaPlatillo">Indicación para la cocina</label>
    <textarea id="notaPlatillo" maxlength="300" placeholder="Sin cebolla, bien cocido, cortada en cuadros"></textarea>
  </div>
  <div class="pie">
    <div class="contador">
      <button type="button" id="menos" aria-label="Quitar uno">−</button>
      <span id="cantidad">1</span>
      <button type="button" id="mas" aria-label="Agregar uno">+</button>
    </div>
    <button class="accion" id="agregar" type="button">
      <span>Agregar</span><span id="precioPlatillo"></span>
    </button>
  </div>
</section>

<section class="hoja" id="hojaPedido" role="dialog" aria-modal="true" aria-labelledby="tituloPedido" hidden>
  <div class="hoja__barra">
    <h2 class="hoja__titulo" id="tituloPedido">Tu pedido</h2>
    <button class="cerrar" type="button" data-cerrar>Cerrar</button>
  </div>
  <div id="cuerpoPedido"></div>
</section>

<form id="formPedido" method="post" action="<?= url('pedido.php') ?>" style="display:none">
  <input type="hidden" name="r" value="<?= e($negocio['slug']) ?>">
  <input type="hidden" name="carga" id="cargaPedido">
</form>

<script>window.DATOS = <?= json_encode($datosNavegador, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= url('assets/js/menu.js') ?>"></script>
</body>
</html>
