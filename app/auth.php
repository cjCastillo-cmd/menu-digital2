<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';

/** Intenta iniciar sesion. Devuelve true si la clave es correcta. */
function iniciar_sesion(string $correo, string $clave): bool
{
    $u = una(
        'SELECT id, negocio_id, nombre, correo, clave_hash, rol
           FROM usuarios
          WHERE correo = ? AND activo = 1',
        [$correo]
    );

    if (!$u || !password_verify($clave, $u['clave_hash'])) {
        return false;
    }

    sesion();
    session_regenerate_id(true);

    $_SESSION['usuario'] = [
        'id'         => (int) $u['id'],
        'negocio_id' => (int) $u['negocio_id'],
        'nombre'     => $u['nombre'],
        'correo'     => $u['correo'],
        'rol'        => $u['rol'],
    ];

    return true;
}

/** Cierra la sesion. */
function cerrar_sesion(): void
{
    sesion();
    $_SESSION = [];
    session_destroy();
}

/** Usuario en sesion o null. */
function usuario(): ?array
{
    sesion();
    return $_SESSION['usuario'] ?? null;
}

/** Corta la peticion si nadie inicio sesion. */
function requiere_sesion(): array
{
    $u = usuario();
    if (!$u) {
        ir('admin/entrar.php');
    }
    return $u;
}

/** Corta la peticion si el usuario no es dueno. */
function requiere_dueno(): array
{
    $u = requiere_sesion();
    if ($u['rol'] !== 'dueno') {
        ir('admin/cocina.php');
    }
    return $u;
}
