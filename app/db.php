<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Devuelve la conexion PDO. Se abre una sola vez por peticion.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NOMBRE . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USUARIO, DB_CLAVE, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            if (MOSTRAR_ERRORES) {
                exit('No se pudo conectar a la base de datos: ' . $e->getMessage());
            }
            exit('No se pudo conectar a la base de datos.');
        }
    }

    return $pdo;
}

/** Ejecuta una consulta preparada y devuelve el statement. */
function consulta(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** Primera fila o null. */
function una(string $sql, array $params = []): ?array
{
    $fila = consulta($sql, $params)->fetch();
    return $fila === false ? null : $fila;
}

/** Todas las filas. */
function todas(string $sql, array $params = []): array
{
    return consulta($sql, $params)->fetchAll();
}
