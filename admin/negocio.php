<?php
require_once __DIR__ . '/comun.php';

$u = requiere_dueno();
$negocio = negocio_por_id((int) $u['negocio_id']);
$negocioId = (int) $negocio['id'];

$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles', 4 => 'Jueves',
         5 => 'Viernes', 6 => 'Sabado', 0 => 'Domingo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_token();
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'datos') {
        $impuesto = decimal($_POST['impuesto'] ?? 0);
        $impuesto = max(0, min(1, $impuesto));
        $tema = in_array($_POST['tema'] ?? '', TEMAS, true) ? $_POST['tema'] : 'comanda';
        consulta(
            'UPDATE negocios
                SET nombre=?, tagline=?, whatsapp=?, moneda=?, impuesto=?,
                    tema=?, color_fondo=?, color_acento=?
              WHERE id=?',
            [
                mb_substr(trim((string) ($_POST['nombre'] ?? '')), 0, 120),
                mb_substr(trim((string) ($_POST['tagline'] ?? '')), 0, 180),
                preg_replace('/\D/', '', (string) ($_POST['whatsapp'] ?? '')),
                mb_substr(trim((string) ($_POST['moneda'] ?? 'L')), 0, 5),
                $impuesto,
                $tema,
                color_hex($_POST['color_fondo'] ?? null),
                color_hex($_POST['color_acento'] ?? null),
                $negocioId,
            ]
        );
        avisar('Datos del negocio guardados.');
        ir('admin/negocio.php');
    }

    if ($accion === 'domicilio') {
        $modo = in_array($_POST['envio_modo'] ?? '', ['zonas', 'fijo', 'gratis'], true)
              ? $_POST['envio_modo'] : 'zonas';
        $gratisDesde = trim((string) ($_POST['envio_gratis_desde'] ?? ''));
        $formas = trim((string) ($_POST['formas_pago'] ?? ''));
        if ($formas === '') { $formas = 'Efectivo'; }
        consulta(
            'UPDATE negocios
                SET envio_modo=?, envio_fijo=?, pedido_minimo=?, envio_gratis_desde=?,
                    tiempo_estimado=?, formas_pago=?
              WHERE id=?',
            [
                $modo,
                max(0, decimal($_POST['envio_fijo'] ?? 0)),
                max(0, decimal($_POST['pedido_minimo'] ?? 0)),
                $gratisDesde === '' ? null : max(0, decimal($gratisDesde)),
                mb_substr(trim((string) ($_POST['tiempo_estimado'] ?? '')), 0, 40) ?: null,
                mb_substr($formas, 0, 200),
                $negocioId,
            ]
        );
        avisar('Envío y formas de pago guardados.');
        ir('admin/negocio.php');
    }

    if ($accion === 'horario') {
        foreach ($dias as $dia => $nombre) {
            $cerrado = isset($_POST['cerrado'][$dia]) ? 1 : 0;
            $abre    = (string) ($_POST['abre'][$dia] ?? '');
            $cierra  = (string) ($_POST['cierra'][$dia] ?? '');
            $abre    = preg_match('/^\d{2}:\d{2}$/', $abre) ? $abre . ':00' : null;
            $cierra  = preg_match('/^\d{2}:\d{2}$/', $cierra) ? $cierra . ':00' : null;

            consulta(
                'INSERT INTO horarios (negocio_id, dia, abre, cierra, cerrado)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE abre=VALUES(abre), cierra=VALUES(cierra), cerrado=VALUES(cerrado)',
                [$negocioId, $dia, $abre, $cierra, $cerrado]
            );
        }
        avisar('Horario guardado.');
        ir('admin/negocio.php');
    }

    if ($accion === 'zona_nueva') {
        $nombre = mb_substr(trim((string) ($_POST['zona_nombre'] ?? '')), 0, 80);
        if ($nombre !== '') {
            consulta('INSERT INTO zonas (negocio_id, nombre, costo, orden) VALUES (?,?,?,?)',
                     [$negocioId, $nombre, max(0, decimal($_POST['zona_costo'] ?? 0)), 99]);
            avisar('Zona agregada.');
        }
        ir('admin/negocio.php');
    }

    if ($accion === 'zona_borrar') {
        consulta('DELETE FROM zonas WHERE id = ? AND negocio_id = ?',
                 [entero($_POST['id'] ?? 0), $negocioId]);
        avisar('Zona eliminada.');
        ir('admin/negocio.php');
    }
}

$h = horarios($negocioId);
$z = zonas($negocioId);

cabecera_panel('Negocio', 'negocio', $negocio);
?>

<div class="bloque">
  <h2>Datos</h2>
  <p class="ayuda">
    El WhatsApp es a donde llegan los pedidos. Escribilo con codigo de pais y sin espacios:
    504 seguido del numero.
  </p>
  <form method="post">
    <input type="hidden" name="token" value="<?= e(token()) ?>">
    <input type="hidden" name="accion" value="datos">
    <div class="dos">
      <div class="campo" style="margin-top:0">
        <label class="campo__rotulo" for="nombre">Nombre</label>
        <input id="nombre" name="nombre" maxlength="120" value="<?= e($negocio['nombre']) ?>">
      </div>
      <div class="campo" style="margin-top:0">
        <label class="campo__rotulo" for="whatsapp">WhatsApp</label>
        <input id="whatsapp" name="whatsapp" maxlength="20" value="<?= e($negocio['whatsapp']) ?>">
      </div>
    </div>
    <div class="campo">
      <label class="campo__rotulo" for="tagline">Frase corta</label>
      <input id="tagline" name="tagline" maxlength="180" value="<?= e($negocio['tagline']) ?>">
    </div>
    <div class="dos">
      <div class="campo">
        <label class="campo__rotulo" for="moneda">Moneda</label>
        <input id="moneda" name="moneda" maxlength="5" value="<?= e($negocio['moneda']) ?>">
      </div>
      <div class="campo">
        <label class="campo__rotulo" for="impuesto">Impuesto (0.15 es 15%)</label>
        <input id="impuesto" name="impuesto" type="number" step="0.01" min="0" max="1"
               value="<?= e($negocio['impuesto']) ?>">
      </div>
    </div>
    <div class="campo">
      <label class="campo__rotulo" for="tema">Diseño de la carta</label>
      <select id="tema" name="tema">
        <option value="comanda"<?= ($negocio['tema'] ?? 'comanda') === 'comanda' ? ' selected' : '' ?>>
          Comanda — tipografía monoespaciada, estilo ticket (informal)
        </option>
        <option value="elegante"<?= ($negocio['tema'] ?? '') === 'elegante' ? ' selected' : '' ?>>
          Elegante — serif de autor y fotos grandes (premium)
        </option>
      </select>
    </div>
    <div class="dos">
      <div class="campo">
        <label class="campo__rotulo" for="color_fondo">Color de fondo (marca)</label>
        <input id="color_fondo" name="color_fondo" type="color"
               value="<?= e($negocio['color_fondo'] ?: '#211F1C') ?>" style="height:44px">
      </div>
      <div class="campo">
        <label class="campo__rotulo" for="color_acento">Color de acento (marca)</label>
        <input id="color_acento" name="color_acento" type="color"
               value="<?= e($negocio['color_acento'] ?: '#E5B54A') ?>" style="height:44px">
      </div>
    </div>
    <p class="ayuda">Con estos dos colores la carta se pinta con la identidad del negocio.</p>
    <div class="pie"><button class="accion" type="submit"><span>Guardar datos</span><span>&rarr;</span></button></div>
  </form>
</div>

<div class="bloque">
  <h2>Envío a domicilio y pago</h2>
  <p class="ayuda">Vos decidís cómo cobrar el envío: por zona, un precio fijo o gratis. El pedido
    mínimo y el envío gratis por monto son opcionales.</p>
  <form method="post">
    <input type="hidden" name="token" value="<?= e(token()) ?>">
    <input type="hidden" name="accion" value="domicilio">
    <div class="dos">
      <div class="campo" style="margin-top:0">
        <label class="campo__rotulo" for="envio_modo">Costo del envío</label>
        <select id="envio_modo" name="envio_modo">
          <option value="zonas"<?= ($negocio['envio_modo'] ?? 'zonas') === 'zonas' ? ' selected' : '' ?>>Por zona (abajo)</option>
          <option value="fijo"<?= ($negocio['envio_modo'] ?? '') === 'fijo' ? ' selected' : '' ?>>Un precio fijo</option>
          <option value="gratis"<?= ($negocio['envio_modo'] ?? '') === 'gratis' ? ' selected' : '' ?>>Siempre gratis</option>
        </select>
      </div>
      <div class="campo" style="margin-top:0">
        <label class="campo__rotulo" for="envio_fijo">Precio fijo de envío</label>
        <input id="envio_fijo" name="envio_fijo" type="number" min="0" step="1" value="<?= (int) ($negocio['envio_fijo'] ?? 0) ?>">
      </div>
    </div>
    <div class="dos">
      <div class="campo">
        <label class="campo__rotulo" for="pedido_minimo">Pedido mínimo a domicilio (0 = sin mínimo)</label>
        <input id="pedido_minimo" name="pedido_minimo" type="number" min="0" step="1" value="<?= (int) ($negocio['pedido_minimo'] ?? 0) ?>">
      </div>
      <div class="campo">
        <label class="campo__rotulo" for="envio_gratis_desde">Envío gratis desde (vacío = nunca)</label>
        <input id="envio_gratis_desde" name="envio_gratis_desde" type="number" min="0" step="1" value="<?= $negocio['envio_gratis_desde'] !== null ? (int) $negocio['envio_gratis_desde'] : '' ?>">
      </div>
    </div>
    <div class="campo">
      <label class="campo__rotulo" for="tiempo_estimado">Tiempo estimado (ej: 30-45 min)</label>
      <input id="tiempo_estimado" name="tiempo_estimado" maxlength="40" value="<?= e($negocio['tiempo_estimado'] ?? '') ?>">
    </div>
    <div class="campo">
      <label class="campo__rotulo" for="formas_pago">Formas de pago (separadas por coma)</label>
      <input id="formas_pago" name="formas_pago" maxlength="200" value="<?= e($negocio['formas_pago'] ?? 'Efectivo,Tarjeta,Transferencia') ?>"
             placeholder="Efectivo, Tarjeta, Transferencia, Pago móvil">
    </div>
    <div class="pie" style="border-top:0"><button class="accion" type="submit"><span>Guardar envío y pago</span><span>&rarr;</span></button></div>
  </form>
</div>

<div class="bloque">
  <h2>Horario</h2>
  <p class="ayuda">Fuera de estas horas el menu se muestra cerrado, pero el cliente igual puede enviar el pedido.</p>
  <form method="post">
    <input type="hidden" name="token" value="<?= e(token()) ?>">
    <input type="hidden" name="accion" value="horario">
    <table class="tabla">
      <thead><tr><th>Dia</th><th>Abre</th><th>Cierra</th><th>Cerrado</th></tr></thead>
      <tbody>
      <?php foreach ($dias as $dia => $nombre):
          $f = $h[$dia] ?? null; ?>
        <tr>
          <td><?= e($nombre) ?></td>
          <td><input type="time" name="abre[<?= $dia ?>]" value="<?= $f && $f['abre'] ? e(substr($f['abre'], 0, 5)) : '' ?>"></td>
          <td><input type="time" name="cierra[<?= $dia ?>]" value="<?= $f && $f['cierra'] ? e(substr($f['cierra'], 0, 5)) : '' ?>"></td>
          <td style="width:70px">
            <input type="checkbox" name="cerrado[<?= $dia ?>]" style="width:auto"
                   <?= $f && (int) $f['cerrado'] === 1 ? 'checked' : '' ?>>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="pie"><button class="accion" type="submit"><span>Guardar horario</span><span>&rarr;</span></button></div>
  </form>
</div>

<div class="bloque">
  <h2>Zonas de entrega</h2>
  <p class="ayuda">El costo se suma al total cuando el cliente elige entrega a domicilio.</p>
  <table class="tabla">
    <thead><tr><th>Zona</th><th>Costo</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($z as $zona): ?>
      <tr>
        <td><?= e($zona['nombre']) ?></td>
        <td><?= dinero($zona['costo'], $negocio['moneda']) ?></td>
        <td class="ancho-acciones">
          <form method="post" style="display:inline">
            <input type="hidden" name="token" value="<?= e(token()) ?>">
            <input type="hidden" name="accion" value="zona_borrar">
            <input type="hidden" name="id" value="<?= (int) $zona['id'] ?>">
            <button class="mini mini--peligro" type="submit">Quitar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <form method="post" style="margin-top:14px">
    <input type="hidden" name="token" value="<?= e(token()) ?>">
    <input type="hidden" name="accion" value="zona_nueva">
    <div class="dos">
      <div class="campo" style="margin-top:0">
        <label class="campo__rotulo" for="zona_nombre">Zona nueva</label>
        <input id="zona_nombre" name="zona_nombre" maxlength="80" placeholder="Col. Miraflores">
      </div>
      <div class="campo" style="margin-top:0">
        <label class="campo__rotulo" for="zona_costo">Costo</label>
        <input id="zona_costo" name="zona_costo" type="number" min="0" step="1" value="0">
      </div>
    </div>
    <div class="pie"><button class="accion" type="submit"><span>Agregar zona</span><span>+</span></button></div>
  </form>
</div>

<?php pie_panel(); ?>
