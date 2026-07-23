# Menú digital · PHP y MySQL

Menú con código QR y pedidos por WhatsApp para restaurantes. El cliente escanea
el código de su mesa, arma el pedido y este queda guardado en la base de datos
antes de salir por WhatsApp a la cocina.

Requiere PHP 8.0 o más nuevo y MySQL o MariaDB. Sin Composer, sin frameworks,
sin compilación: se copia a `htdocs` y funciona.

La instalación paso a paso está en `docs/INSTALACION-XAMPP.md`.

## Cómo está organizado

```
config/config.php    Datos de conexión y dirección base
app/db.php           Conexión PDO y ayudas de consulta
app/util.php         Escapado, formato de moneda, sesión, token
app/auth.php         Entrar, salir, control de acceso
app/repo.php         Lectura del menú y cálculo de pedidos
index.php            El menú que ve el cliente
pedido.php           Recibe, recalcula, guarda y manda a WhatsApp
admin/               Panel: carta, cocina, negocio, códigos QR
assets/              Estilos y JavaScript
sql/esquema.sql      Tablas y datos de ejemplo
```

Las carpetas `app`, `config` y `sql` tienen un `.htaccess` que bloquea el
acceso directo desde el navegador.

## Decisiones que vale la pena conocer

**El precio se calcula dos veces.** El navegador muestra un precio mientras
el cliente arma el pedido, pero `app/repo.php` lo vuelve a calcular desde la
base de datos antes de guardar. Nunca se guarda un monto que venga del
formulario. Si alguien edita el HTML, el total no cambia.

**El pedido se guarda antes de WhatsApp.** Así existe aunque el cliente nunca
llegue a mandar el mensaje, y por eso la pantalla de cocina puede funcionar.

**Mitad y mitad.** Se cobra el precio de la mitad más cara y los extras de
cada mitad valen la mitad, que es la convención del rubro. La vista previa
dibuja los ingredientes con el color que tiene cada uno en la base.

**El factor de tamaño.** Un extra en pizza grande cuesta más que en personal.
El multiplicador vive en la columna `factor` de la tabla `opciones`; la única
opción con factor distinto de 1 debe ser la de tamaño.

**Roles.** El dueño ve todo el panel. Un usuario con rol `cocina` solo entra
a la pantalla de pedidos, que es lo que se le deja abierto a la tablet de la
cocina.

## Acceso de ejemplo

- Correo: `admin@lapiedra.hn`
- Clave: `piedra2026`

Cambiala antes de enseñarle esto a alguien. Está explicado en la guía de
instalación.

## Agregar un restaurante nuevo

El sistema ya es multi-negocio: todas las tablas cuelgan de `negocio_id`.

1. Insertá una fila en `negocios` con su `slug`, nombre y WhatsApp.
2. Insertá su usuario en `usuarios` apuntando a ese `negocio_id`.
3. Cargá sus categorías, grupos, opciones y productos.
4. Su menú queda en `?r=el-slug-que-le-pusiste`.

## Lo que sigue

- Fotos de los platillos con carga desde el panel. Es lo que más sube la
  conversión y hoy no está.
- Combos y cupones.
- Reportes de ventas por platillo y por hora.
- Sonido y notificación al entrar un pedido en la pantalla de cocina.
- Pago en línea, al final de todo.

## Publicar

Esto ya no corre en GitHub Pages: PHP necesita un servidor. Lo más barato que
funciona bien es un hosting compartido con cPanel, PHP 8 y MySQL. El paso desde
XAMPP es copiar los archivos por FTP, importar el `.sql` desde el phpMyAdmin del
hosting y ajustar `config/config.php` con los datos que te dé el proveedor.
