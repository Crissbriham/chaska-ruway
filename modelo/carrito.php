<?php
/* Consultas SQL del carrito. Sin HTML. */
require_once __DIR__ . '/../includes/helpers.php';

/* Cada comprador tiene un solo carrito (clave única en la BD) */
function obtenerOCrearCarrito($id_comprador, $crear = true) {
    $f = fila("SELECT id_carrito FROM Carrito WHERE id_comprador = ?", "i", $id_comprador);
    if ($f) return (int)$f['id_carrito'];
    return $crear ? ejecutar("INSERT INTO Carrito (id_comprador, estado) VALUES (?, 'Activo')", "i", $id_comprador) : null;
}

function obtenerItemsCarrito($id_comprador) {
    $idc = obtenerOCrearCarrito($id_comprador, false);
    if (!$idc) return [];
    return filas("SELECT cd.id_detalle, cd.cantidad, p.id_producto, p.nombre, p.precio, p.stock, v.nombre_taller,
        (SELECT i.url FROM Imagen_Producto i WHERE i.id_producto = p.id_producto ORDER BY i.principal DESC, i.orden LIMIT 1) AS imagen,
        (SELECT i.alt FROM Imagen_Producto i WHERE i.id_producto = p.id_producto ORDER BY i.principal DESC, i.orden LIMIT 1) AS alt
        FROM Carrito_Detalle cd
        JOIN Productos p ON p.id_producto = cd.id_producto
        JOIN Vendedores v ON v.id_vendedor = p.id_vendedor
        WHERE cd.id_carrito = ? ORDER BY cd.id_detalle", "i", $idc);
}

function contarItemsCarrito($id_comprador) {
    $f = fila("SELECT COALESCE(SUM(cd.cantidad), 0) AS n FROM Carrito_Detalle cd
               JOIN Carrito c ON c.id_carrito = cd.id_carrito WHERE c.id_comprador = ?", "i", $id_comprador);
    return (int)$f['n'];
}

/* Agrega al carrito sin pasar del stock. Devuelve ['ok'=>bool, 'mensaje'=>texto] */
function agregarAlCarrito($id_comprador, $id_producto, $cantidad) {
    $prod = fila("SELECT nombre, precio, stock FROM Productos WHERE id_producto = ?", "i", $id_producto);
    if (!$prod) return ['ok' => false, 'mensaje' => 'Ese producto ya no existe.'];

    $idc = obtenerOCrearCarrito($id_comprador);
    $actual = fila("SELECT id_detalle, cantidad FROM Carrito_Detalle WHERE id_carrito = ? AND id_producto = ?", "ii", $idc, $id_producto);
    $ya = $actual ? (int)$actual['cantidad'] : 0;
    $nueva = min($ya + $cantidad, (int)$prod['stock']);

    if ($nueva <= $ya) {
        return ['ok' => false, 'ya' => $ya, 'mensaje' => $ya > 0
            ? 'Ya tienes en tu carrito todas las unidades disponibles de ' . $prod['nombre'] . '.'
            : $prod['nombre'] . ' está agotado.'];
    }

    if ($actual) {
        ejecutar("UPDATE Carrito_Detalle SET cantidad = ?, precio_unitario = ? WHERE id_detalle = ?", "idi", $nueva, $prod['precio'], $actual['id_detalle']);
    } else {
        ejecutar("INSERT INTO Carrito_Detalle (id_carrito, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)", "iiid", $idc, $id_producto, $nueva, $prod['precio']);
    }
    $msg = $prod['nombre'] . ' está en tu carrito.';
    if ($nueva < $ya + $cantidad) $msg .= ' Solo había ' . $prod['stock'] . ' unidades disponibles.';
    return ['ok' => true, 'mensaje' => $msg];
}

/* Cambia la cantidad (o quita si es 0). Solo toca líneas del propio comprador. */
function cambiarCantidad($id_comprador, $id_detalle, $cantidad) {
    $item = fila("SELECT cd.id_detalle, p.stock, p.nombre FROM Carrito_Detalle cd
                  JOIN Carrito c ON c.id_carrito = cd.id_carrito
                  JOIN Productos p ON p.id_producto = cd.id_producto
                  WHERE cd.id_detalle = ? AND c.id_comprador = ?", "ii", $id_detalle, $id_comprador);
    if (!$item) return 'No encontramos ese producto en tu carrito.';
    if ($cantidad <= 0) {
        ejecutar("DELETE FROM Carrito_Detalle WHERE id_detalle = ?", "i", $id_detalle);
        return 'Quitaste ' . $item['nombre'] . ' de tu carrito.';
    }
    $cantidad = min($cantidad, max(1, (int)$item['stock']));
    ejecutar("UPDATE Carrito_Detalle SET cantidad = ? WHERE id_detalle = ?", "ii", $cantidad, $id_detalle);
    return 'Cantidad actualizada.';
}
