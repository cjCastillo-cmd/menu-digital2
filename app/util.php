<?php
require_once __DIR__ . '/../config/config.php';

/** Escapa texto para imprimirlo en HTML. */
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formatea un monto con el simbolo del negocio. */
function dinero($monto, string $moneda = 'L'): string
{
    return $moneda . ' ' . number_format((float) round($monto), 0, '.', ',');
}

/** Construye una direccion dentro del sitio. */
function url(string $ruta = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($ruta, '/');
}

/** Redirige y corta la ejecucion. */
function ir(string $ruta): void
{
    header('Location: ' . (str_starts_with($ruta, 'http') ? $ruta : url($ruta)));
    exit;
}

/** Arranca la sesion una sola vez. */
function sesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/** Token contra falsificacion de formularios. */
function token(): string
{
    sesion();
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token'];
}

/** Corta la peticion si el token no coincide. */
function verificar_token(): void
{
    sesion();
    $enviado = $_POST['token'] ?? '';
    if (!is_string($enviado) || !hash_equals($_SESSION['token'] ?? '', $enviado)) {
        http_response_code(400);
        exit('La sesion expiro. Volve a cargar la pagina e intenta de nuevo.');
    }
}

/** Guarda un aviso para mostrarlo despues de una redireccion. */
function avisar(string $texto, string $tipo = 'ok'): void
{
    sesion();
    $_SESSION['aviso'] = ['texto' => $texto, 'tipo' => $tipo];
}

/** Saca el aviso pendiente, si lo hay. */
function tomar_aviso(): ?array
{
    sesion();
    $a = $_SESSION['aviso'] ?? null;
    unset($_SESSION['aviso']);
    return $a;
}

/** Entero limpio a partir de una entrada. */
function entero($valor, int $porDefecto = 0): int
{
    return is_numeric($valor) ? (int) $valor : $porDefecto;
}

/** Decimal limpio a partir de una entrada. */
function decimal($valor, float $porDefecto = 0.0): float
{
    return is_numeric($valor) ? (float) $valor : $porDefecto;
}
