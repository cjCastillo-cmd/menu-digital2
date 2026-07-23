-- ============================================================
--  Menu digital - esquema de base de datos
--  MySQL / MariaDB (XAMPP)
--  Importar desde phpMyAdmin o con:
--    mysql -u root < sql/esquema.sql
-- ============================================================

DROP DATABASE IF EXISTS menu_digital;
CREATE DATABASE menu_digital DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE menu_digital;

-- ------------------------------------------------------------
--  Negocios
-- ------------------------------------------------------------
CREATE TABLE negocios (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(60)  NOT NULL UNIQUE,
  nombre        VARCHAR(120) NOT NULL,
  tagline       VARCHAR(180) DEFAULT NULL,
  whatsapp      VARCHAR(20)  NOT NULL,
  moneda        VARCHAR(5)   NOT NULL DEFAULT 'L',
  impuesto      DECIMAL(4,3) NOT NULL DEFAULT 0.150,
  -- Envio a domicilio (a criterio del dueño)
  envio_modo         ENUM('zonas','fijo','gratis') NOT NULL DEFAULT 'zonas',
  envio_fijo         DECIMAL(10,2) NOT NULL DEFAULT 0,
  pedido_minimo      DECIMAL(10,2) NOT NULL DEFAULT 0,
  envio_gratis_desde DECIMAL(10,2) NULL,
  tiempo_estimado    VARCHAR(40) NULL,
  formas_pago   VARCHAR(200) NOT NULL DEFAULT 'Efectivo,Tarjeta,Transferencia',
  tema          VARCHAR(30)  NOT NULL DEFAULT 'comanda',
  color_fondo   VARCHAR(9)   DEFAULT NULL,   -- color de marca (fondo)
  color_acento  VARCHAR(9)   DEFAULT NULL,   -- color de marca (acento)
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  creado        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Usuarios del panel
-- ------------------------------------------------------------
CREATE TABLE usuarios (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id  INT NOT NULL,
  nombre      VARCHAR(80)  NOT NULL,
  correo      VARCHAR(120) NOT NULL UNIQUE,
  clave_hash  VARCHAR(255) NOT NULL,
  rol         ENUM('dueno','cocina') NOT NULL DEFAULT 'dueno',
  activo      TINYINT(1)   NOT NULL DEFAULT 1,
  creado      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Horario y zonas de entrega
-- ------------------------------------------------------------
CREATE TABLE horarios (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  dia        TINYINT NOT NULL,          -- 0 domingo .. 6 sabado
  abre       TIME DEFAULT NULL,
  cierra     TIME DEFAULT NULL,
  cerrado    TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_horario (negocio_id, dia),
  CONSTRAINT fk_horarios_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE zonas (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  nombre     VARCHAR(80) NOT NULL,
  costo      DECIMAL(10,2) NOT NULL DEFAULT 0,
  orden      INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_zonas_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Carta
-- ------------------------------------------------------------
CREATE TABLE categorias (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  padre_id   INT NULL,                 -- NULL = categoria principal; si no, subcategoria
  nombre     VARCHAR(80) NOT NULL,
  orden      INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_categorias_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios(id) ON DELETE CASCADE,
  CONSTRAINT fk_categorias_padre FOREIGN KEY (padre_id)
    REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE grupos (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id        INT NOT NULL,
  nombre            VARCHAR(80) NOT NULL,
  tipo              ENUM('unico','multiple') NOT NULL DEFAULT 'unico',
  obligatorio       TINYINT(1) NOT NULL DEFAULT 0,
  minimo            INT NOT NULL DEFAULT 0,
  maximo            INT NOT NULL DEFAULT 0,     -- 0 = sin limite
  escala_por_tamano TINYINT(1) NOT NULL DEFAULT 0,
  orden             INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_grupos_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE opciones (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  grupo_id INT NOT NULL,
  nombre   VARCHAR(80) NOT NULL,
  precio   DECIMAL(10,2) NOT NULL DEFAULT 0,
  factor   DECIMAL(4,2)  NOT NULL DEFAULT 1.00,
  color    VARCHAR(9) DEFAULT NULL,
  orden    INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_opciones_grupo FOREIGN KEY (grupo_id)
    REFERENCES grupos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE productos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id   INT NOT NULL,
  categoria_id INT NOT NULL,
  nombre       VARCHAR(120) NOT NULL,
  descripcion  VARCHAR(400) DEFAULT NULL,
  imagen       VARCHAR(255) DEFAULT NULL,
  precio       DECIMAL(10,2) NOT NULL DEFAULT 0,
  disponible   TINYINT(1) NOT NULL DEFAULT 1,
  destacado    TINYINT(1) NOT NULL DEFAULT 0,
  mitades      TINYINT(1) NOT NULL DEFAULT 0,
  etiquetas    VARCHAR(120) DEFAULT NULL,
  orden        INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_productos_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios(id) ON DELETE CASCADE,
  CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id)
    REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE producto_grupo (
  producto_id INT NOT NULL,
  grupo_id    INT NOT NULL,
  orden       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (producto_id, grupo_id),
  CONSTRAINT fk_pg_producto FOREIGN KEY (producto_id)
    REFERENCES productos(id) ON DELETE CASCADE,
  CONSTRAINT fk_pg_grupo FOREIGN KEY (grupo_id)
    REFERENCES grupos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Pedidos
-- ------------------------------------------------------------
CREATE TABLE pedidos (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  codigo     VARCHAR(12) NOT NULL,
  modo       ENUM('mesa','llevar','domicilio') NOT NULL DEFAULT 'mesa',
  mesa       VARCHAR(10)  DEFAULT NULL,
  cliente    VARCHAR(120) DEFAULT NULL,
  telefono   VARCHAR(30)  DEFAULT NULL,
  zona       VARCHAR(80)  DEFAULT NULL,
  direccion  VARCHAR(300) DEFAULT NULL,
  pago       VARCHAR(30)  DEFAULT NULL,
  nota       VARCHAR(300) DEFAULT NULL,
  subtotal   DECIMAL(10,2) NOT NULL DEFAULT 0,
  impuesto   DECIMAL(10,2) NOT NULL DEFAULT 0,
  envio      DECIMAL(10,2) NOT NULL DEFAULT 0,
  propina    DECIMAL(10,2) NOT NULL DEFAULT 0,
  descuento  DECIMAL(10,2) NOT NULL DEFAULT 0,
  cupon      VARCHAR(30) DEFAULT NULL,
  total      DECIMAL(10,2) NOT NULL DEFAULT 0,
  estado     ENUM('recibido','preparando','listo','entregado','anulado')
             NOT NULL DEFAULT 'recibido',
  creado     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pedidos_negocio_estado (negocio_id, estado),
  CONSTRAINT fk_pedidos_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cupones de descuento (marketing)
CREATE TABLE cupones (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  codigo     VARCHAR(30) NOT NULL,
  tipo       ENUM('porcentaje','monto') NOT NULL DEFAULT 'porcentaje',
  valor      DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_pedido DECIMAL(10,2) NOT NULL DEFAULT 0,
  activo     TINYINT(1) NOT NULL DEFAULT 1,
  vence      DATE NULL,
  UNIQUE KEY uq_cupon (negocio_id, codigo),
  CONSTRAINT fk_cupones_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Promociones que se muestran en el menu (marketing)
CREATE TABLE promociones (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  titulo     VARCHAR(80) NOT NULL,
  texto      VARCHAR(200) DEFAULT NULL,
  activo     TINYINT(1) NOT NULL DEFAULT 1,
  orden      INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_promos_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Llamadas al mesero desde la mesa
CREATE TABLE llamadas (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  mesa       VARCHAR(10) NOT NULL,
  atendida   TINYINT(1) NOT NULL DEFAULT 0,
  creado     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_llamadas (negocio_id, atendida),
  CONSTRAINT fk_llamadas_negocio FOREIGN KEY (negocio_id)
    REFERENCES negocios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pedido_lineas (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  nombre    VARCHAR(160) NOT NULL,
  detalle   TEXT DEFAULT NULL,
  nota      VARCHAR(300) DEFAULT NULL,
  cantidad  INT NOT NULL DEFAULT 1,
  precio    DECIMAL(10,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_lineas_pedido FOREIGN KEY (pedido_id)
    REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  Datos de ejemplo
-- ============================================================

INSERT INTO negocios (slug, nombre, tagline, whatsapp, moneda, impuesto) VALUES
('la-piedra', 'La Piedra', 'Horno de lena / Santa Rosa de Copan', '50499999999', 'L', 0.150);

SET @n = LAST_INSERT_ID();

-- Clave de acceso: piedra2026
INSERT INTO usuarios (negocio_id, nombre, correo, clave_hash, rol) VALUES
(@n, 'Administrador', 'admin@lapiedra.hn',
 '$2y$10$mMeRIcfo5ZRrjCqKBnlUOuP4b0VQjerE5wRjextEvbPfyHZyWnhaW', 'dueno');

INSERT INTO horarios (negocio_id, dia, abre, cierra, cerrado) VALUES
(@n, 0, '12:00:00', '21:00:00', 0),
(@n, 1, '11:00:00', '22:00:00', 0),
(@n, 2, '11:00:00', '22:00:00', 0),
(@n, 3, '11:00:00', '22:00:00', 0),
(@n, 4, '11:00:00', '22:00:00', 0),
(@n, 5, '11:00:00', '23:30:00', 0),
(@n, 6, '11:00:00', '23:30:00', 0);

INSERT INTO zonas (negocio_id, nombre, costo, orden) VALUES
(@n, 'Centro', 30, 1),
(@n, 'Col. Santa Teresa', 45, 2),
(@n, 'Fuera de la ciudad', 70, 3);

INSERT INTO categorias (negocio_id, nombre, orden) VALUES
(@n, 'Pizzas', 1), (@n, 'Arma la tuya', 2), (@n, 'Entradas', 3),
(@n, 'Pastas', 4), (@n, 'Bebidas', 5), (@n, 'Postres', 6);

SET @c_pizzas   = (SELECT id FROM categorias WHERE negocio_id=@n AND nombre='Pizzas');
SET @c_arma     = (SELECT id FROM categorias WHERE negocio_id=@n AND nombre='Arma la tuya');
SET @c_entradas = (SELECT id FROM categorias WHERE negocio_id=@n AND nombre='Entradas');
SET @c_pastas   = (SELECT id FROM categorias WHERE negocio_id=@n AND nombre='Pastas');
SET @c_bebidas  = (SELECT id FROM categorias WHERE negocio_id=@n AND nombre='Bebidas');
SET @c_postres  = (SELECT id FROM categorias WHERE negocio_id=@n AND nombre='Postres');

-- Grupos de opciones
INSERT INTO grupos (negocio_id, nombre, tipo, obligatorio, minimo, maximo, escala_por_tamano, orden) VALUES
(@n, 'Tamano',       'unico',    1, 0, 0, 0, 1),
(@n, 'Masa',         'unico',    1, 0, 0, 0, 2),
(@n, 'Extras',       'multiple', 0, 0, 6, 1, 3),
(@n, 'Presentacion', 'unico',    1, 0, 0, 0, 4),
(@n, 'Salsas extra', 'multiple', 0, 0, 4, 0, 5);

SET @g_tam   = (SELECT id FROM grupos WHERE negocio_id=@n AND nombre='Tamano');
SET @g_masa  = (SELECT id FROM grupos WHERE negocio_id=@n AND nombre='Masa');
SET @g_ext   = (SELECT id FROM grupos WHERE negocio_id=@n AND nombre='Extras');
SET @g_pres  = (SELECT id FROM grupos WHERE negocio_id=@n AND nombre='Presentacion');
SET @g_sals  = (SELECT id FROM grupos WHERE negocio_id=@n AND nombre='Salsas extra');

INSERT INTO opciones (grupo_id, nombre, precio, factor, orden) VALUES
(@g_tam, 'Personal 8 pulgadas', 0,   0.60, 1),
(@g_tam, 'Mediana 12 pulgadas', 90,  1.00, 2),
(@g_tam, 'Grande 16 pulgadas',  170, 1.50, 3);

INSERT INTO opciones (grupo_id, nombre, precio, orden) VALUES
(@g_masa, 'Tradicional', 0, 1),
(@g_masa, 'Delgada crujiente', 0, 2),
(@g_masa, 'Orilla rellena de queso', 45, 3);

INSERT INTO opciones (grupo_id, nombre, precio, color, orden) VALUES
(@g_ext, 'Pepperoni',       30, '#C0392B', 1),
(@g_ext, 'Jamon',           28, '#E8918A', 2),
(@g_ext, 'Chorizo',         32, '#8E3B26', 3),
(@g_ext, 'Pollo BBQ',       35, '#D9A25F', 4),
(@g_ext, 'Champinon',       25, '#B9A084', 5),
(@g_ext, 'Pimiento',        20, '#5E9C4E', 6),
(@g_ext, 'Cebolla morada',  18, '#8E6FA8', 7),
(@g_ext, 'Jalapeno',        20, '#3F7A3A', 8),
(@g_ext, 'Pina',            22, '#EFC94C', 9),
(@g_ext, 'Doble queso',     40, '#F0DFA8', 10);

INSERT INTO opciones (grupo_id, nombre, precio, orden) VALUES
(@g_pres, 'Vaso 12 oz', 0, 1),
(@g_pres, '1 litro', 25, 2);

INSERT INTO opciones (grupo_id, nombre, precio, orden) VALUES
(@g_sals, 'Ranch', 15, 1),
(@g_sals, 'Mantequilla de ajo', 12, 2),
(@g_sals, 'Salsa picante de la casa', 10, 3);

-- Productos
INSERT INTO productos (negocio_id, categoria_id, nombre, descripcion, precio, destacado, mitades, etiquetas, orden) VALUES
(@n, @c_pizzas, 'Margarita', 'Salsa de tomate San Marzano, mozzarella fresca, albahaca del huerto.', 120, 1, 1, 'vegetariana', 1),
(@n, @c_pizzas, 'Pepperoni clasica', 'Doble pepperoni curado, mozzarella, oregano tostado.', 140, 1, 1, NULL, 2),
(@n, @c_pizzas, 'Cuatro quesos', 'Mozzarella, parmesano, cheddar y queso de Copan.', 155, 0, 1, 'vegetariana', 3),
(@n, @c_pizzas, 'Catracha', 'Chorizo, frijol molido, cuajada seca y chile verde. Nuestra favorita.', 165, 1, 1, 'picante', 4),
(@n, @c_pizzas, 'Hawaiana', 'Jamon de pierna, pina caramelizada, mozzarella.', 145, 0, 1, NULL, 5),
(@n, @c_pizzas, 'Del huerto', 'Pimiento, champinon, cebolla morada, aceituna y rucula fresca.', 150, 0, 1, 'vegetariana', 6),
(@n, @c_pizzas, 'Pollo BBQ', 'Pollo ahumado, salsa BBQ, cebolla morada, cilantro.', 160, 0, 1, NULL, 7),
(@n, @c_arma,   'Arma tu pizza', 'Empieza con salsa y mozzarella. Agrega lo que quieras, mitad y mitad incluido.', 105, 1, 1, NULL, 1),
(@n, @c_entradas, 'Pan de ajo del horno', 'Seis piezas con mantequilla de ajo y parmesano.', 85, 0, 0, NULL, 1),
(@n, @c_entradas, 'Alitas al horno', 'Ocho piezas. Elegi BBQ, bufalo o tamarindo picante.', 165, 0, 0, NULL, 2),
(@n, @c_entradas, 'Ensalada caprese', 'Tomate, mozzarella fresca, albahaca, reduccion de balsamico.', 120, 0, 0, 'vegetariana', 3),
(@n, @c_pastas, 'Carbonara', 'Fettuccine, tocino, yema, parmesano. Sin crema, como debe ser.', 210, 0, 0, NULL, 1),
(@n, @c_pastas, 'Bolonesa lenta', 'Ragu de res cocinado cuatro horas sobre pasta fresca.', 225, 0, 0, NULL, 2),
(@n, @c_pastas, 'Pesto de albahaca', 'Albahaca, pinon, parmesano y aceite de oliva.', 195, 0, 0, 'vegetariana', 3),
(@n, @c_bebidas, 'Limonada con hierbabuena', 'Hecha al momento.', 45, 0, 0, NULL, 1),
(@n, @c_bebidas, 'Horchata de la casa', 'Receta de la abuela, bien fria.', 40, 0, 0, NULL, 2),
(@n, @c_bebidas, 'Gaseosa', 'Coca-Cola, Fanta o Sprite.', 35, 0, 0, NULL, 3),
(@n, @c_bebidas, 'Cerveza nacional', 'Salva Vida, Port Royal o Barena.', 55, 0, 0, NULL, 4),
(@n, @c_bebidas, 'Cafe de Copan', 'Grano de altura tostado en la ciudad.', 38, 0, 0, NULL, 5),
(@n, @c_postres, 'Tiramisu', 'Con cafe de Copan y mascarpone.', 110, 0, 0, NULL, 1),
(@n, @c_postres, 'Brownie con helado', 'Tibio, con helado de vainilla.', 95, 0, 0, NULL, 2),
(@n, @c_postres, 'Cheesecake de maracuya', 'Base de galleta, coulis fresco.', 105, 0, 0, NULL, 3);

-- Enlazar grupos a productos
INSERT INTO producto_grupo (producto_id, grupo_id, orden)
SELECT p.id, @g_tam, 1 FROM productos p
WHERE p.negocio_id=@n AND p.categoria_id IN (@c_pizzas, @c_arma);

INSERT INTO producto_grupo (producto_id, grupo_id, orden)
SELECT p.id, @g_masa, 2 FROM productos p
WHERE p.negocio_id=@n AND p.categoria_id IN (@c_pizzas, @c_arma);

INSERT INTO producto_grupo (producto_id, grupo_id, orden)
SELECT p.id, @g_ext, 3 FROM productos p
WHERE p.negocio_id=@n AND p.categoria_id IN (@c_pizzas, @c_arma);

INSERT INTO producto_grupo (producto_id, grupo_id, orden)
SELECT p.id, @g_pres, 1 FROM productos p
WHERE p.negocio_id=@n AND p.categoria_id=@c_bebidas
  AND p.nombre IN ('Limonada con hierbabuena','Horchata de la casa','Gaseosa');

INSERT INTO producto_grupo (producto_id, grupo_id, orden)
SELECT p.id, @g_sals, 1 FROM productos p
WHERE p.negocio_id=@n AND p.categoria_id=@c_entradas
  AND p.nombre IN ('Pan de ajo del horno','Alitas al horno');
