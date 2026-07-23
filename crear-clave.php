<?php
/**
 * Genera el hash de una clave para pegarlo en la tabla usuarios.
 * Abrir en el navegador:  http://localhost/menu-digital/crear-clave.php?clave=loquesea
 * BORRAR ESTE ARCHIVO antes de publicar el sitio.
 */
$clave = isset($_GET['clave']) ? (string) $_GET['clave'] : '';

header('Content-Type: text/plain; charset=utf-8');

if ($clave === '') {
    echo "Agrega ?clave=tuclave a la direccion.\n";
    exit;
}

echo "Clave: " . $clave . "\n";
echo "Hash:  " . password_hash($clave, PASSWORD_DEFAULT) . "\n\n";
echo "Copia el hash y pegalo en la columna clave_hash de la tabla usuarios.\n";
echo "Cuando termines, borra este archivo.\n";
