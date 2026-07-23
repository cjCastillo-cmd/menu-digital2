<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/imagen.php';

/* ============================================================
   Lectura del menu
   ============================================================ */

function negocio_por_slug(string $slug): ?array
{
    return una('SELECT * FROM negocios WHERE slug = ? AND activo = 1', [$slug]);
}

function negocio_por_id(int $id): ?array
{
    return una('SELECT * FROM negocios WHERE id = ?', [$id]);
}

function categorias(int $negocioId): array
{
    return todas(
        'SELECT * FROM categorias WHERE negocio_id = ? ORDER BY orden, nombre',
        [$negocioId]
    );
}

function zonas(int $negocioId): array
{
    return todas(
        'SELECT * FROM zonas WHERE negocio_id = ? ORDER BY orden, nombre',
        [$negocioId]
    );
}

function horarios(int $negocioId): array
{
    $filas = todas('SELECT * FROM horarios WHERE negocio_id = ? ORDER BY dia', [$negocioId]);
    $porDia = [];
    foreach ($filas as $f) {
        $porDia[(int) $f['dia']] = $f;
    }
    return $porDia;
}

function esta_abierto(int $negocioId): bool
{
    $h = horarios($negocioId);
    $hoy = $h[(int) date('w')] ?? null;

    if (!$hoy || (int) $hoy['cerrado'] === 1 || !$hoy['abre'] || !$hoy['cierra']) {
        return false;
    }

    $ahora = date('H:i:s');
    return $ahora >= $hoy['abre'] && $ahora <= $hoy['cierra'];
}

/**
 * Devuelve el catalogo completo del negocio en una sola estructura.
 * Se usa tanto para pintar la pagina como para recalcular precios.
 */
function catalogo(int $negocioId): array
{
    $grupos = [];
    foreach (todas('SELECT * FROM grupos WHERE negocio_id = ? ORDER BY orden, id', [$negocioId]) as $g) {
        $g['opciones'] = [];
        $grupos[(int) $g['id']] = $g;
    }

    if ($grupos) {
        $ids = implode(',', array_map('intval', array_keys($grupos)));
        foreach (todas("SELECT * FROM opciones WHERE grupo_id IN ($ids) ORDER BY orden, id") as $o) {
            $grupos[(int) $o['grupo_id']]['opciones'][(int) $o['id']] = $o;
        }
    }

    $productos = [];
    $sql = 'SELECT p.*, c.nombre AS categoria
              FROM productos p
              JOIN categorias c ON c.id = p.categoria_id
             WHERE p.negocio_id = ?
             ORDER BY c.orden, p.orden, p.nombre';
    foreach (todas($sql, [$negocioId]) as $p) {
        $p['grupos'] = [];
        $productos[(int) $p['id']] = $p;
    }

    if ($productos) {
        $ids = implode(',', array_map('intval', array_keys($productos)));
        $sql = "SELECT producto_id, grupo_id
                  FROM producto_grupo
                 WHERE producto_id IN ($ids)
                 ORDER BY orden, grupo_id";
        foreach (todas($sql) as $pg) {
            $productos[(int) $pg['producto_id']]['grupos'][] = (int) $pg['grupo_id'];
        }
    }

    return ['productos' => $productos, 'grupos' => $grupos];
}

/**
 * Version reducida del catalogo para entregarsela al navegador.
 * No incluye nada que el cliente no necesite ver.
 */
function catalogo_para_navegador(array $cat): array
{
    $productos = [];
    foreach ($cat['productos'] as $id => $p) {
        $productos[] = [
            'id'         => (int) $id,
            'nombre'     => $p['nombre'],
            'desc'       => $p['descripcion'],
            'img'        => url_imagen_producto($p['imagen'] ?? null),
            'precio'     => (float) $p['precio'],
            'disponible' => (int) $p['disponible'] === 1,
            'mitades'    => (int) $p['mitades'] === 1,
            'grupos'     => $p['grupos'],
        ];
    }

    $grupos = [];
    foreach ($cat['grupos'] as $id => $g) {
        $opciones = [];
        foreach ($g['opciones'] as $oid => $o) {
            $opciones[] = [
                'id'     => (int) $oid,
                'nombre' => $o['nombre'],
                'precio' => (float) $o['precio'],
                'factor' => (float) $o['factor'],
                'color'  => $o['color'],
            ];
        }
        $grupos[] = [
            'id'          => (int) $id,
            'nombre'      => $g['nombre'],
            'tipo'        => $g['tipo'],
            'obligatorio' => (int) $g['obligatorio'] === 1,
            'minimo'      => (int) $g['minimo'],
            'maximo'      => (int) $g['maximo'],
            'escala'      => (int) $g['escala_por_tamano'] === 1,
            'opciones'    => $opciones,
        ];
    }

    return ['productos' => $productos, 'grupos' => $grupos];
}

/* ============================================================
   Calculo de precios
   El navegador muestra un precio, pero el que vale es este.
   Nunca se guarda un monto que venga del formulario.
   ============================================================ */

/**
 * Calcula una linea del pedido.
 * Devuelve ['nombre', 'detalle', 'precio'] o lanza una excepcion.
 */
function calcular_linea(array $cat, array $linea): array
{
    $pid = entero($linea['producto_id'] ?? 0);
    if (!isset($cat['productos'][$pid])) {
        throw new RuntimeException('Ese platillo ya no esta en el menu.');
    }

    $p = $cat['productos'][$pid];
    if ((int) $p['disponible'] !== 1) {
        throw new RuntimeException('Se acabo ' . $p['nombre'] . '. Quitalo del pedido.');
    }

    $sel      = is_array($linea['opciones'] ?? null) ? $linea['opciones'] : [];
    $mitades  = !empty($linea['mitades']['activo']) && (int) $p['mitades'] === 1;
    $detalle  = [];

    // El factor lo aporta la opcion de tamano: es la unica con factor distinto de 1.
    $factor = 1.0;
    foreach ($sel as $gid => $opcionIds) {
        $gid = (int) $gid;
        if (!isset($cat['grupos'][$gid])) {
            continue;
        }
        foreach ((array) $opcionIds as $oid) {
            $oid = (int) $oid;
            if (isset($cat['grupos'][$gid]['opciones'][$oid])) {
                $f = (float) $cat['grupos'][$gid]['opciones'][$oid]['factor'];
                if (abs($f - 1.0) > 0.001) {
                    $factor = $f;
                }
            }
        }
    }

    // Precio base
    if ($mitades) {
        $izq = entero($linea['mitades']['izq']['producto_id'] ?? 0);
        $der = entero($linea['mitades']['der']['producto_id'] ?? 0);
        if (!isset($cat['productos'][$izq]) || !isset($cat['productos'][$der])) {
            throw new RuntimeException('Una de las mitades ya no esta disponible.');
        }
        $total = max((float) $cat['productos'][$izq]['precio'], (float) $cat['productos'][$der]['precio']);
    } else {
        $total = (float) $p['precio'];
    }

    $grupoExtras = null;
    foreach ($p['grupos'] as $gid) {
        if (isset($cat['grupos'][$gid]) && (int) $cat['grupos'][$gid]['escala_por_tamano'] === 1) {
            $grupoExtras = (int) $gid;
            break;
        }
    }

    // Grupos normales
    foreach ($p['grupos'] as $gid) {
        $gid = (int) $gid;
        $g = $cat['grupos'][$gid] ?? null;
        if (!$g) {
            continue;
        }

        $elegidas = array_map('intval', (array) ($sel[$gid] ?? []));
        $elegidas = array_values(array_filter($elegidas, function ($oid) use ($g) {
            return isset($g['opciones'][$oid]);
        }));

        if ($g['tipo'] === 'unico' && count($elegidas) > 1) {
            $elegidas = [$elegidas[0]];
        }
        if ((int) $g['obligatorio'] === 1 && !$elegidas) {
            throw new RuntimeException('Falta elegir ' . $g['nombre'] . ' en ' . $p['nombre'] . '.');
        }
        if ((int) $g['minimo'] > 0 && count($elegidas) < (int) $g['minimo']) {
            throw new RuntimeException('En ' . $g['nombre'] . ' hay que elegir al menos ' . $g['minimo'] . '.');
        }
        if ((int) $g['maximo'] > 0 && count($elegidas) > (int) $g['maximo']) {
            throw new RuntimeException('En ' . $g['nombre'] . ' el maximo es ' . $g['maximo'] . '.');
        }

        // Los extras de una pizza dividida se cobran por mitad, mas abajo.
        if ($mitades && $gid === $grupoExtras) {
            continue;
        }

        $nombres = [];
        foreach ($elegidas as $oid) {
            $o = $g['opciones'][$oid];
            $escala = (int) $g['escala_por_tamano'] === 1 ? $factor : 1.0;
            $total += (float) $o['precio'] * $escala;
            $nombres[] = $o['nombre'];
        }
        if ($nombres) {
            $detalle[] = $g['nombre'] . ': ' . implode(', ', $nombres);
        }
    }

    // Extras por mitad
    if ($mitades && $grupoExtras !== null) {
        foreach (['izq' => 'Mitad izquierda', 'der' => 'Mitad derecha'] as $lado => $rotulo) {
            $sabor  = entero($linea['mitades'][$lado]['producto_id'] ?? 0);
            $extras = array_map('intval', (array) ($linea['mitades'][$lado]['extras'] ?? []));

            $nombres = [];
            foreach ($extras as $oid) {
                if (!isset($cat['grupos'][$grupoExtras]['opciones'][$oid])) {
                    continue;
                }
                $o = $cat['grupos'][$grupoExtras]['opciones'][$oid];
                $total += (float) $o['precio'] * 0.5 * $factor;
                $nombres[] = $o['nombre'];
            }

            $texto = $cat['productos'][$sabor]['nombre'] ?? '';
            if ($nombres) {
                $texto .= ' + ' . implode(', ', $nombres);
            }
            $detalle[] = $rotulo . ': ' . $texto;
        }
    }

    $cantidad = max(1, min(50, entero($linea['cantidad'] ?? 1, 1)));

    return [
        'nombre'   => $mitades ? 'Pizza mitad y mitad' : $p['nombre'],
        'detalle'  => implode("\n", $detalle),
        'nota'     => mb_substr(trim((string) ($linea['nota'] ?? '')), 0, 300),
        'cantidad' => $cantidad,
        'precio'   => round($total),
    ];
}

/* ============================================================
   Guardado del pedido
   ============================================================ */

function guardar_pedido(array $negocio, array $datos, array $lineasCrudas): array
{
    $cat = catalogo((int) $negocio['id']);

    if (!$lineasCrudas) {
        throw new RuntimeException('El pedido esta vacio.');
    }

    $lineas   = [];
    $subtotal = 0.0;
    foreach ($lineasCrudas as $cruda) {
        $l = calcular_linea($cat, $cruda);
        $subtotal += $l['precio'] * $l['cantidad'];
        $lineas[] = $l;
    }

    $modo  = in_array($datos['modo'] ?? '', ['mesa', 'llevar', 'domicilio'], true)
           ? $datos['modo'] : 'mesa';

    $envio = 0.0;
    $zona  = null;
    if ($modo === 'domicilio') {
        $zonaNombre = trim((string) ($datos['zona'] ?? ''));
        foreach (zonas((int) $negocio['id']) as $z) {
            if ($z['nombre'] === $zonaNombre) {
                $envio = (float) $z['costo'];
                $zona  = $z['nombre'];
                break;
            }
        }
        if ($zona === null) {
            throw new RuntimeException('Elegi una zona de entrega.');
        }
    }

    $impuesto = round($subtotal * (float) $negocio['impuesto']);
    $total    = $subtotal + $impuesto + $envio;
    $codigo   = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

    $pdo = db();
    $pdo->beginTransaction();

    try {
        consulta(
            'INSERT INTO pedidos
               (negocio_id, codigo, modo, mesa, cliente, telefono, zona, direccion,
                pago, nota, subtotal, impuesto, envio, total)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                (int) $negocio['id'],
                $codigo,
                $modo,
                mb_substr(trim((string) ($datos['mesa'] ?? '')), 0, 10) ?: null,
                mb_substr(trim((string) ($datos['cliente'] ?? '')), 0, 120) ?: null,
                mb_substr(trim((string) ($datos['telefono'] ?? '')), 0, 30) ?: null,
                $zona,
                mb_substr(trim((string) ($datos['direccion'] ?? '')), 0, 300) ?: null,
                mb_substr(trim((string) ($datos['pago'] ?? '')), 0, 30) ?: null,
                mb_substr(trim((string) ($datos['nota'] ?? '')), 0, 300) ?: null,
                $subtotal, $impuesto, $envio, $total,
            ]
        );

        $pedidoId = (int) $pdo->lastInsertId();

        foreach ($lineas as $l) {
            consulta(
                'INSERT INTO pedido_lineas (pedido_id, nombre, detalle, nota, cantidad, precio)
                 VALUES (?,?,?,?,?,?)',
                [$pedidoId, $l['nombre'], $l['detalle'], $l['nota'] ?: null, $l['cantidad'], $l['precio']]
            );
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [
        'id'       => $pedidoId,
        'codigo'   => $codigo,
        'modo'     => $modo,
        'zona'     => $zona,
        'lineas'   => $lineas,
        'subtotal' => $subtotal,
        'impuesto' => $impuesto,
        'envio'    => $envio,
        'total'    => $total,
    ];
}

/** Arma el mensaje que se le manda al WhatsApp del local. */
function texto_whatsapp(array $negocio, array $pedido, array $datos): string
{
    $m = (string) $negocio['moneda'];
    $rotulos = ['mesa' => 'En mesa', 'llevar' => 'Para llevar', 'domicilio' => 'A domicilio'];

    $l = [];
    $l[] = '*' . mb_strtoupper($negocio['nombre']) . '* - pedido ' . $pedido['codigo'];
    $l[] = 'Tipo: ' . ($rotulos[$pedido['modo']] ?? $pedido['modo']);

    if ($pedido['modo'] === 'mesa' && !empty($datos['mesa'])) {
        $l[] = 'Mesa: ' . $datos['mesa'];
    }
    if ($pedido['modo'] === 'domicilio') {
        $l[] = 'Zona: ' . $pedido['zona'];
        if (!empty($datos['direccion'])) {
            $l[] = 'Direccion: ' . $datos['direccion'];
        }
    }

    $l[] = 'Cliente: ' . (($datos['cliente'] ?? '') ?: '-')
         . (!empty($datos['telefono']) ? ' / ' . $datos['telefono'] : '');
    $l[] = '';

    foreach ($pedido['lineas'] as $ln) {
        $l[] = $ln['cantidad'] . 'x ' . $ln['nombre'] . '  -  ' . dinero($ln['precio'] * $ln['cantidad'], $m);
        foreach (array_filter(explode("\n", $ln['detalle'])) as $d) {
            $l[] = '   . ' . $d;
        }
        if ($ln['nota']) {
            $l[] = '   . Nota: ' . $ln['nota'];
        }
    }

    $l[] = '';
    $l[] = 'Subtotal: ' . dinero($pedido['subtotal'], $m);
    if ($pedido['impuesto'] > 0) {
        $l[] = 'ISV: ' . dinero($pedido['impuesto'], $m);
    }
    if ($pedido['envio'] > 0) {
        $l[] = 'Envio: ' . dinero($pedido['envio'], $m);
    }
    $l[] = '*TOTAL: ' . dinero($pedido['total'], $m) . '*';

    if (!empty($datos['pago'])) {
        $l[] = 'Pago: ' . $datos['pago'];
    }

    return implode("\n", $l);
}
