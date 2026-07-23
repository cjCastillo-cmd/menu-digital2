<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/repo.php';

/** Cabecera comun del panel. */
function cabecera_panel(string $titulo, string $activo, array $negocio): void
{
    $u = usuario();
    $paginas = [
        'carta'   => ['Carta', 'index.php'],
        'cocina'  => ['Cocina', 'cocina.php'],
        'negocio' => ['Negocio', 'negocio.php'],
        'qr'      => ['Codigos QR', 'qr.php'],
    ];
    $aviso = tomar_aviso();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> · Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/comanda.css') ?>">
<style>:root { --ancho: 1000px; }</style>
</head>
<body>
<main class="envoltura" style="padding-bottom:60px">

  <header class="cabecera">
    <div class="cabecera__meta">
      <span class="rotulo"><?= e($negocio['nombre']) ?> · <?= e($u['nombre']) ?></span>
      <a class="mini" href="<?= url('admin/salir.php') ?>">Salir</a>
    </div>
    <h1 class="cabecera__nombre" style="font-size:30px"><?= e($titulo) ?></h1>
  </header>

  <hr class="raya">

  <div class="panel">
    <nav class="menu-lateral">
      <?php foreach ($paginas as $clave => $p): ?>
        <a href="<?= url('admin/' . $p[1]) ?>"<?= $clave === $activo ? ' aria-current="page"' : '' ?>><?= e($p[0]) ?></a>
      <?php endforeach; ?>
      <a href="<?= url('index.php?r=' . urlencode($negocio['slug'])) ?>" target="_blank" rel="noopener">Ver el menu</a>
    </nav>
    <div>
      <?php if ($aviso): ?>
        <p class="aviso<?= $aviso['tipo'] === 'error' ? ' aviso--error' : '' ?>"><?= e($aviso['texto']) ?></p>
      <?php endif; ?>
    <?php
}

/** Pie comun del panel. */
function pie_panel(): void
{
    ?>
    </div>
  </div>
</main>
</body>
</html>
    <?php
}
