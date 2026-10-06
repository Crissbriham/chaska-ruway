<?php
/* Consultas SQL de productos, reseñas y favoritos. Sin HTML. */
require_once __DIR__ . '/../includes/helpers.php';

/* Consulta base: taller, imagen principal y promedio de reseñas */
const SQL_PRODUCTO = "SELECT p.id_producto, p.id_vendedor, p.visible, p.nombre, p.descripcion, p.precio, p.stock,
    v.nombre_taller, v.departamento AS region, v.descripcion AS taller_descripcion,
    (SELECT i.url FROM Imagen_Producto i WHERE i.id_producto = p.id_producto ORDER BY i.principal DESC, i.orden LIMIT 1) AS imagen,
    (SELECT i.alt FROM Imagen_Producto i WHERE i.id_producto = p.id_producto ORDER BY i.principal DESC, i.orden LIMIT 1) AS alt,
    (SELECT ROUND(AVG(r.puntuacion), 1) FROM Resena r WHERE r.id_producto = p.id_producto) AS promedio,
    (SELECT COUNT(*) FROM Resena r WHERE r.id_producto = p.id_producto) AS num_resenas
    FROM Productos p
    JOIN Vendedores v ON v.id_vendedor = p.id_vendedor";

const ORDENES_CATALOGO = [
    'recientes'   => 'p.id_producto DESC',
    'valorados'   => 'promedio IS NULL, promedio DESC, num_resenas DESC',
    'precio_asc'  => 'p.precio ASC',
    'precio_desc' => 'p.precio DESC',
    'nombre'      => 'p.nombre ASC',
];

function obtenerCatalogo($q = '', $orden = 'valorados', $soloDisponibles = false, $region = '') {
    $orden = ORDENES_CATALOGO[$orden] ?? ORDENES_CATALOGO['valorados'];
    $like = '%' . $q . '%';
    $donde = ["p.visible = 1", "(p.nombre LIKE ? OR p.descripcion LIKE ? OR v.nombre_taller LIKE ? OR v.departamento LIKE ?)"];
    $tipos = 'ssss';
    $params = [$like, $like, $like, $like];
    if ($soloDisponibles) $donde[] = 'p.stock > 0';
    if ($region !== '') { $donde[] = 'v.departamento = ?'; $tipos .= 's'; $params[] = $region; }
    return filas(SQL_PRODUCTO . " WHERE " . implode(' AND ', $donde) . " ORDER BY " . $orden, $tipos, ...$params);
}

/* Regiones que tienen productos, para los filtros del catálogo */
function obtenerRegiones() {
    return array_column(filas("SELECT DISTINCT v.departamento FROM Vendedores v
                               JOIN Productos p ON p.id_vendedor = v.id_vendedor WHERE p.visible = 1 ORDER BY v.departamento"), 'departamento');
}

function obtenerProductoPorId($id) {
    return fila(SQL_PRODUCTO . " WHERE p.id_producto = ?", "i", $id);
}

function obtenerRelacionados($id, $limite = 4) {
    return filas(SQL_PRODUCTO . " WHERE p.id_producto <> ? AND p.stock > 0 AND p.visible = 1 ORDER BY RAND() LIMIT " . (int)$limite, "i", $id);
}

function obtenerResenas($id_producto) {
    return filas("SELECT r.puntuacion, r.titulo, r.comentario, r.fecha, c.nombres, c.apellidos
                  FROM Resena r JOIN Compradores c ON c.id_comprador = r.id_comprador
                  WHERE r.id_producto = ? ORDER BY r.fecha DESC", "i", $id_producto);
}

/* Devuelve el id del pedido más reciente (no cancelado) donde compró el producto, o null */
function pedidoDeCompra($id_comprador, $id_producto) {
    $f = fila("SELECT pe.id_pedido FROM Pedidos pe JOIN Detalle_Pedidos d ON d.id_pedido = pe.id_pedido
               WHERE pe.id_comprador = ? AND d.id_producto = ? AND pe.estado_pedido <> 'Cancelado'
               ORDER BY pe.fecha_pedido DESC LIMIT 1", "ii", $id_comprador, $id_producto);
    return $f ? (int)$f['id_pedido'] : null;
}

function yaReseno($id_comprador, $id_producto) {
    return (bool)fila("SELECT 1 AS si FROM Resena WHERE id_comprador = ? AND id_producto = ?", "ii", $id_comprador, $id_producto);
}

function publicarResena($id_producto, $id_comprador, $id_pedido, $puntuacion, $titulo, $comentario) {
    return ejecutar("INSERT INTO Resena (id_producto, id_comprador, id_pedido, puntuacion, titulo, comentario) VALUES (?, ?, ?, ?, ?, ?)",
                    "iiiiss", $id_producto, $id_comprador, $id_pedido, $puntuacion, $titulo, $comentario);
}

/* ---------- Favoritos ---------- */
function obtenerIdsFavoritos($id_comprador) {
    if (!$id_comprador) return [];
    return array_map('intval', array_column(filas("SELECT id_producto FROM Favorito WHERE id_comprador = ?", "i", $id_comprador), 'id_producto'));
}

function obtenerFavoritos($id_comprador) {
    return filas(SQL_PRODUCTO . " JOIN Favorito f ON f.id_producto = p.id_producto WHERE f.id_comprador = ? AND p.visible = 1 ORDER BY f.fecha DESC", "i", $id_comprador);
}

function alternarFavorito($id_comprador, $id_producto) {
    $existe = fila("SELECT id_favorito FROM Favorito WHERE id_comprador = ? AND id_producto = ?", "ii", $id_comprador, $id_producto);
    if ($existe) {
        ejecutar("DELETE FROM Favorito WHERE id_favorito = ?", "i", $existe['id_favorito']);
    } elseif (fila("SELECT id_producto FROM Productos WHERE id_producto = ?", "i", $id_producto)) {
        ejecutar("INSERT INTO Favorito (id_comprador, id_producto) VALUES (?, ?)", "ii", $id_comprador, $id_producto);
    }
}