# Instalación en XAMPP

Diez minutos si XAMPP ya está instalado. Necesitás PHP 8.0 o más nuevo,
que es lo que traen todas las versiones de XAMPP desde 2021.

## 1. Copiar la carpeta

Poné la carpeta `menu-digital` dentro de `htdocs`:

- Windows: `C:\xampp\htdocs\menu-digital`
- Mac: `/Applications/XAMPP/htdocs/menu-digital`

El nombre de la carpeta importa. Si le ponés otro, cambiá `BASE_URL`
en `config/config.php` para que coincida.

## 2. Encender Apache y MySQL

Abrí el panel de XAMPP y dale **Start** a Apache y a MySQL. Los dos tienen
que quedar en verde.

Si Apache no arranca, casi siempre es que otro programa ocupa el puerto 80
(Skype o IIS en Windows). Cambiá el puerto de Apache a 8080 desde
Config → httpd.conf, y usá `http://localhost:8080/...` en todas las direcciones.

## 3. Crear la base de datos

Entrá a `http://localhost/phpmyadmin`, pestaña **Importar**, elegí el archivo
`sql/esquema.sql` y dale **Continuar**.

El archivo crea la base `menu_digital`, todas las tablas y carga la pizzería
de ejemplo con veintidós platillos.

Si preferís la línea de comandos:

```
cd C:\xampp\mysql\bin
mysql -u root < C:\xampp\htdocs\menu-digital\sql\esquema.sql
```

## 4. Revisar la configuración

Abrí `config/config.php`. Con XAMPP de fábrica no hay que tocar nada:
usuario `root` y clave vacía. Si le pusiste clave a MySQL, ponela en `DB_CLAVE`.

## 5. Probar

| Dirección | Qué es |
|---|---|
| `http://localhost/menu-digital/` | El menú del cliente |
| `http://localhost/menu-digital/?mesa=7` | El menú como si escaneara la mesa 7 |
| `http://localhost/menu-digital/admin/entrar.php` | El panel |

Para entrar al panel:

- Correo: `admin@lapiedra.hn`
- Clave: `piedra2026`

Cambiá esa clave antes de mostrarle esto a un cliente. Abrí
`http://localhost/menu-digital/crear-clave.php?clave=laquequieras`,
copiá el hash y pegalo en la columna `clave_hash` de la tabla `usuarios`
desde phpMyAdmin. Después borrá `crear-clave.php`.

## 6. Probarlo desde el celular

Mientras el celular esté en el mismo wifi, averiguá la IP de tu computadora
(`ipconfig` en Windows, `ifconfig` en Mac) y entrá desde el teléfono a
`http://192.168.x.x/menu-digital/`. Así se ve como lo va a ver el cliente,
que es la única prueba que cuenta.

Si no carga, es el firewall de Windows bloqueando Apache: permitilo en
redes privadas.

## Problemas comunes

**"No se pudo conectar a la base de datos"**
MySQL está apagado en el panel de XAMPP, o la clave en `config/config.php`
no coincide con la de tu MySQL.

**Los estilos no cargan y todo se ve en blanco y negro**
`BASE_URL` no coincide con el nombre de la carpeta dentro de `htdocs`.

**Página en blanco sin ningún mensaje**
Poné `MOSTRAR_ERRORES` en `true` en `config/config.php` y recargá:
va a aparecer el error exacto.

**El QR no se genera**
El generador se descarga de internet. Sin conexión no aparece.
El menú funciona igual, solo esa pantalla necesita red.
