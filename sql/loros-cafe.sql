-- ============================================================
--  Alta de negocio: LORO'S CAFE
--  Comida costeña (mariscos) y parrilla (cortes de carne).
--  Colores de marca: verde selva + amarillo guacamaya.
--  Ejecutar sobre la base ya creada:
--    mysql -u root menu_digital < sql/loros-cafe.sql
-- ============================================================

USE menu_digital;

-- Si ya existe, lo borramos para poder recargarlo limpio.
DELETE FROM negocios WHERE slug = 'loros-cafe';

INSERT INTO negocios (slug, nombre, tagline, whatsapp, moneda, impuesto, tema, color_fondo, color_acento) VALUES
('loros-cafe', "Loro's Café", 'Sabor costeño y parrilla / La Ceiba',
 '50499999999', 'L', 0.150, 'elegante', '#123524', '#F4C430');

SET @n = LAST_INSERT_ID();

-- Usuario del panel (clave: piedra2026 -- cambiala en el panel)
INSERT INTO usuarios (negocio_id, nombre, correo, clave_hash, rol) VALUES
(@n, 'Administrador', 'admin@loroscafe.hn',
 '$2y$10$mMeRIcfo5ZRrjCqKBnlUOuP4b0VQjerE5wRjextEvbPfyHZyWnhaW', 'dueno');

-- Horario
INSERT INTO horarios (negocio_id, dia, abre, cierra, cerrado) VALUES
(@n, 0, '10:00:00', '21:00:00', 0),
(@n, 1, '10:00:00', '22:00:00', 0),
(@n, 2, '10:00:00', '22:00:00', 0),
(@n, 3, '10:00:00', '22:00:00', 0),
(@n, 4, '10:00:00', '22:00:00', 0),
(@n, 5, '10:00:00', '23:00:00', 0),
(@n, 6, '10:00:00', '23:00:00', 0);

-- Zonas de entrega
INSERT INTO zonas (negocio_id, nombre, costo, orden) VALUES
(@n, 'Centro', 40, 1),
(@n, 'Zona Viva', 55, 2),
(@n, 'Fuera de la ciudad', 90, 3);

-- Categorias (orden de lectura del menu)
INSERT INTO categorias (negocio_id, nombre, orden) VALUES
(@n, 'Mariscos y costeñas', 1),
(@n, 'Parrilla y cortes', 2),
(@n, 'Entradas', 3),
(@n, 'Bebidas', 4),
(@n, 'Postres', 5);

SET @c_mar = (SELECT id FROM categorias WHERE negocio_id=@n AND nombre='Mariscos y costeñas');
SET @c_par = (SELECT id FROM categorias WHERE negocio_id=@n AND nombre='Parrilla y cortes');
SET @c_ent = (SELECT id FROM categorias WHERE negocio_id=@n AND nombre='Entradas');
SET @c_beb = (SELECT id FROM categorias WHERE negocio_id=@n AND nombre='Bebidas');
SET @c_pos = (SELECT id FROM categorias WHERE negocio_id=@n AND nombre='Postres');

-- Grupos de opciones
INSERT INTO grupos (negocio_id, nombre, tipo, obligatorio, minimo, maximo, escala_por_tamano, orden) VALUES
(@n, 'Término de la carne', 'unico',    1, 0, 0, 0, 1),
(@n, 'Acompañamiento',      'unico',    1, 0, 0, 0, 2),
(@n, 'Extras',              'multiple', 0, 0, 5, 0, 3);

SET @g_term = (SELECT id FROM grupos WHERE negocio_id=@n AND nombre='Término de la carne');
SET @g_acom = (SELECT id FROM grupos WHERE negocio_id=@n AND nombre='Acompañamiento');
SET @g_ext  = (SELECT id FROM grupos WHERE negocio_id=@n AND nombre='Extras');

INSERT INTO opciones (grupo_id, nombre, precio, orden) VALUES
(@g_term, 'Rojo / inglés', 0, 1),
(@g_term, 'Tres cuartos',  0, 2),
(@g_term, 'Bien cocido',   0, 3);

INSERT INTO opciones (grupo_id, nombre, precio, orden) VALUES
(@g_acom, 'Arroz y ensalada', 0, 1),
(@g_acom, 'Tajadas de plátano', 0, 2),
(@g_acom, 'Papas fritas', 0, 3),
(@g_acom, 'Yuca frita', 0, 4),
(@g_acom, 'Ensalada de repollo', 0, 5);

INSERT INTO opciones (grupo_id, nombre, precio, orden) VALUES
(@g_ext, 'Porción de camarón', 90, 1),
(@g_ext, 'Chimol', 15, 2),
(@g_ext, 'Chismol de la casa', 15, 3),
(@g_ext, 'Mantequilla de ajo', 20, 4),
(@g_ext, 'Queso frito', 45, 5);

-- ------------------------------------------------------------
--  Productos
-- ------------------------------------------------------------
-- Mariscos y costeñas
INSERT INTO productos (negocio_id, categoria_id, nombre, descripcion, precio, destacado, etiquetas, orden) VALUES
(@n, @c_mar, 'Sopa de caracol', 'Caracol, leche de coco, yuca y plátano. La reina de la costa.', 260, 1, NULL, 1),
(@n, @c_mar, 'Sopa marinera', 'Camarón, pescado, caracol y jaiba en caldo de coco.', 290, 1, NULL, 2),
(@n, @c_mar, 'Tapado costeño', 'Guiso de mariscos y carne salada en leche de coco.', 320, 0, NULL, 3),
(@n, @c_mar, 'Pescado frito entero', 'Servido con tajadas, ensalada y chismol.', 240, 1, NULL, 4),
(@n, @c_mar, 'Filete de pescado a la plancha', 'Corvina a la plancha con mantequilla de ajo.', 230, 0, NULL, 5),
(@n, @c_mar, 'Camarones al ajillo', 'Camarón salteado en ajo y mantequilla.', 275, 1, NULL, 6),
(@n, @c_mar, 'Camarones empanizados', 'Crocantes, con salsa tártara de la casa.', 265, 0, NULL, 7),
(@n, @c_mar, 'Ceviche de camarón', 'Curtido en limón, cebolla morada y cilantro.', 180, 0, 'picante', 8),
(@n, @c_mar, 'Arroz con mariscos', 'Camarón, calamar y pescado sobre arroz costeño.', 250, 0, NULL, 9);

-- Parrilla y cortes
INSERT INTO productos (negocio_id, categoria_id, nombre, descripcion, precio, destacado, etiquetas, orden) VALUES
(@n, @c_par, 'Churrasco', 'Corte de res a la parrilla, jugoso y ahumado.', 290, 1, NULL, 1),
(@n, @c_par, 'Rib eye', 'Corte marmoleado de 12 oz al carbón.', 360, 1, NULL, 2),
(@n, @c_par, 'Lomito de res', 'El corte más suave, término a tu gusto.', 340, 0, NULL, 3),
(@n, @c_par, 'Costilla BBQ', 'Costilla de cerdo glaseada en salsa de la casa.', 280, 1, NULL, 4),
(@n, @c_par, 'Pollo a la parrilla', 'Pechuga marinada con hierbas.', 210, 0, NULL, 5),
(@n, @c_par, 'Chorizo parrillero', 'Dos chorizos con chimol y tortilla.', 150, 0, NULL, 6),
(@n, @c_par, 'Parrillada Loro´s para 2', 'Res, cerdo, pollo, chorizo y chuleta con acompañamientos.', 620, 1, NULL, 7);

-- Entradas
INSERT INTO productos (negocio_id, categoria_id, nombre, descripcion, precio, destacado, etiquetas, orden) VALUES
(@n, @c_ent, 'Tajadas con carne', 'Plátano verde frito, carne desmenuzada y repollo.', 130, 0, NULL, 1),
(@n, @c_ent, 'Yuca con chicharrón', 'Yuca frita, chicharrón y curtido.', 140, 0, NULL, 2),
(@n, @c_ent, 'Anafre', 'Frijoles fritos con queso, para mojar con totopos.', 120, 0, 'vegetariana', 3),
(@n, @c_ent, 'Alitas a la parrilla', 'Ocho piezas. BBQ o picante.', 175, 0, NULL, 4);

-- Bebidas
INSERT INTO productos (negocio_id, categoria_id, nombre, descripcion, precio, orden) VALUES
(@n, @c_beb, 'Agua de coco', 'Fría, servida en el coco.', 55, 1),
(@n, @c_beb, 'Limonada con hierbabuena', 'Hecha al momento.', 50, 2),
(@n, @c_beb, 'Horchata', 'Receta de la casa.', 45, 3),
(@n, @c_beb, 'Michelada', 'Cerveza preparada con chile y limón.', 85, 4),
(@n, @c_beb, 'Cerveza nacional', 'Salva Vida, Port Royal o Barena.', 55, 5),
(@n, @c_beb, 'Gaseosa', 'Coca-Cola, Fanta o Sprite.', 35, 6);

-- Postres
INSERT INTO productos (negocio_id, categoria_id, nombre, descripcion, precio, orden) VALUES
(@n, @c_pos, 'Tres leches', 'Esponjoso y bien remojado.', 95, 1),
(@n, @c_pos, 'Flan de coco', 'Con caramelo y coco rallado.', 90, 2),
(@n, @c_pos, 'Plátano en gloria', 'Plátano maduro en dulce con crema.', 80, 3);

-- ------------------------------------------------------------
--  Enlazar grupos a productos
-- ------------------------------------------------------------
-- Término: solo a los cortes de res/cerdo
INSERT INTO producto_grupo (producto_id, grupo_id, orden)
SELECT p.id, @g_term, 1 FROM productos p
WHERE p.negocio_id=@n AND p.categoria_id=@c_par
  AND p.nombre IN ('Churrasco','Rib eye','Lomito de res');

-- Acompañamiento: a todos los platos fuertes (parrilla y mariscos con plato)
INSERT INTO producto_grupo (producto_id, grupo_id, orden)
SELECT p.id, @g_acom, 2 FROM productos p
WHERE p.negocio_id=@n AND p.categoria_id IN (@c_par, @c_mar)
  AND p.nombre NOT IN ('Sopa de caracol','Sopa marinera','Ceviche de camarón','Tapado costeño');

-- Extras: a mariscos y parrilla
INSERT INTO producto_grupo (producto_id, grupo_id, orden)
SELECT p.id, @g_ext, 3 FROM productos p
WHERE p.negocio_id=@n AND p.categoria_id IN (@c_par, @c_mar);
