<?php
require_once __DIR__ . '/util.php';

/* ============================================================
   Fotos de los platillos
   Sube, valida, recorta al centro a cuadrado y guarda en disco.
   No usa librerias externas: solo la extension GD de PHP.
   ============================================================ */

const IMG_CARPETA = 'assets/img/productos'; // relativo a la raiz del proyecto
const IMG_LADO    = 600;                     // px del cuadrado final

/** Carpeta absoluta donde se guardan las fotos. */
function imagen_carpeta_abs(): string
{
    return dirname(__DIR__) . '/' . IMG_CARPETA;
}

/** URL publica de la foto de un producto, o null si no tiene. */
function url_imagen_producto(?string $archivo): ?string
{
    if (!$archivo) {
        return null;
    }
    return url(IMG_CARPETA . '/' . $archivo);
}

/**
 * Procesa una foto subida: valida, recorta al centro y la deja cuadrada.
 * Devuelve el nombre del archivo guardado, o null si no vino ninguna foto.
 * Lanza RuntimeException si el archivo no sirve.
 */
function guardar_imagen_subida(array $file): ?string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null; // el dueno no subio foto esta vez
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la foto. Intenta con otra.');
    }
    if (($file['size'] ?? 0) > 6 * 1024 * 1024) {
        throw new RuntimeException('La foto pesa mas de 6 MB. Usa una mas liviana.');
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('El archivo no llego bien. Intenta de nuevo.');
    }

    $info = @getimagesize($tmp);
    if (!$info) {
        throw new RuntimeException('Ese archivo no es una imagen.');
    }

    [$ancho, $alto] = $info;
    switch ($info['mime']) {
        case 'image/jpeg':
            $src = imagecreatefromjpeg($tmp);
            break;
        case 'image/png':
            $src = imagecreatefrompng($tmp);
            break;
        case 'image/webp':
            $src = function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmp) : false;
            break;
        default:
            throw new RuntimeException('Formato no valido. Usa JPG, PNG o WEBP.');
    }
    if (!$src) {
        throw new RuntimeException('No se pudo leer la foto.');
    }

    // Recorte cuadrado tomando el centro de la imagen.
    $lado = min($ancho, $alto);
    $x = (int) (($ancho - $lado) / 2);
    $y = (int) (($alto - $lado) / 2);

    $dst = imagecreatetruecolor(IMG_LADO, IMG_LADO);
    imagecopyresampled($dst, $src, 0, 0, $x, $y, IMG_LADO, IMG_LADO, $lado, $lado);
    imagedestroy($src);

    $carpeta = imagen_carpeta_abs();
    if (!is_dir($carpeta)) {
        @mkdir($carpeta, 0775, true);
    }

    $nombre = 'p_' . bin2hex(random_bytes(8)) . '.jpg';
    imagejpeg($dst, $carpeta . '/' . $nombre, 82);
    imagedestroy($dst);

    return $nombre;
}

/** Borra el archivo de una foto si existe. */
function borrar_imagen_producto(?string $archivo): void
{
    if (!$archivo) {
        return;
    }
    $ruta = imagen_carpeta_abs() . '/' . basename($archivo);
    if (is_file($ruta)) {
        @unlink($ruta);
    }
}
