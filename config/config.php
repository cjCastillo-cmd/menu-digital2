<?php
/**
 * Configuracion del sistema.
 * En XAMPP los valores de fabrica son usuario "root" y clave vacia.
 */

// --- Base de datos -------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NOMBRE', 'menu_digital');
define('DB_USUARIO', 'root');
define('DB_CLAVE', '');

// --- Sitio ---------------------------------------------------------
// Carpeta desde la que se sirve el proyecto dentro de htdocs.
// Si lo copiaste como htdocs/menu-digital, dejalo asi.
define('BASE_URL', '/menu-digital');

// Negocio que se muestra cuando la direccion no trae ?r=
define('NEGOCIO_POR_DEFECTO', 'la-piedra');

// Poner en false cuando el sitio ya este publicado
define('MOSTRAR_ERRORES', true);

date_default_timezone_set('America/Tegucigalpa');

if (MOSTRAR_ERRORES) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
