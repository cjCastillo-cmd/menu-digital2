<?php
require_once __DIR__ . '/../app/auth.php';

$destino = function (): string {
    $u = usuario();
    return ($u && $u['rol'] === 'cocina') ? 'admin/cocina.php' : 'admin/index.php';
};

if (usuario()) {
    ir($destino());
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_token();
    $correo = trim((string) ($_POST['correo'] ?? ''));
    $clave  = (string) ($_POST['clave'] ?? '');

    if ($correo === '' || $clave === '') {
        $error = 'Escribi tu correo y tu clave.';
    } elseif (iniciar_sesion($correo, $clave)) {
        ir($destino());
    } else {
        $error = 'Ese correo y esa clave no coinciden.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar al panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/comanda.css') ?>">
</head>
<body>
<div class="entrar">
  <span class="rotulo">Panel del restaurante</span>
  <h1 class="cabecera__nombre" style="font-size:28px;margin-top:10px">Entrar</h1>

  <?php if ($error): ?>
    <p class="aviso aviso--error" style="margin-top:18px"><?= e($error) ?></p>
  <?php endif; ?>

  <form method="post" style="margin-top:18px">
    <input type="hidden" name="token" value="<?= e(token()) ?>">
    <div class="campo">
      <label class="campo__rotulo" for="correo">Correo</label>
      <input id="correo" name="correo" type="email" autocomplete="username" required autofocus>
    </div>
    <div class="campo">
      <label class="campo__rotulo" for="clave">Clave</label>
      <input id="clave" name="clave" type="password" autocomplete="current-password" required>
    </div>
    <div class="pie" style="border-top:0">
      <button class="accion" type="submit"><span>Entrar</span><span>&rarr;</span></button>
    </div>
  </form>
</div>
</body>
</html>
