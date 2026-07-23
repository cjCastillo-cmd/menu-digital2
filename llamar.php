<?php
require_once __DIR__ . '/app/repo.php';
sesion();

$slug = trim((string) ($_POST['r'] ?? ''));
$negocio = $slug !== '' ? negocio_por_slug($slug) : null;

if (!$negocio || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ir('index.php');
}
verificar_token();

$mesa = mb_substr(trim((string) ($_POST['mesa'] ?? '')), 0, 10);
$ok = false;
if ($mesa !== '') {
    // Evitamos duplicar una llamada sin atender de la misma mesa.
    $pend = una(
        "SELECT id FROM llamadas WHERE negocio_id = ? AND mesa = ? AND atendida = 0",
        [(int) $negocio['id'], $mesa]
    );
    if (!$pend) {
        consulta('INSERT INTO llamadas (negocio_id, mesa) VALUES (?,?)',
                 [(int) $negocio['id'], $mesa]);
    }
    $ok = true;
}

$tema = tema_valido($negocio);
$volver = url('index.php?r=' . urlencode($negocio['slug'])
        . ($mesa !== '' ? '&mesa=' . urlencode($mesa) : ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mesero en camino</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= e(url_fuentes($tema)) ?>" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/' . $tema . '.css') ?>">
<?= estilo_marca($negocio) ?>
</head>
<body>
<main class="envoltura" style="max-width:460px">
  <header class="cabecera">
    <span class="sello sello--ambar">Mesa <?= e($mesa) ?></span>
    <h1 class="cabecera__nombre" style="font-size:30px">
      <?= $ok ? 'Ya avisamos al mesero' : 'No pudimos avisar' ?>
    </h1>
    <p class="cabecera__tagline">
      <?= $ok ? 'En un momento pasa por tu mesa. Podés seguir viendo el menú.' : 'Volvé al menú e intentá de nuevo.' ?>
    </p>
  </header>
  <a class="accion" style="text-decoration:none;justify-content:center;margin-top:22px" href="<?= e($volver) ?>">
    Volver al menú
  </a>
</main>
</body>
</html>
