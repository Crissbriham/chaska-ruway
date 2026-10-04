<?php
/* Panel de vendedor: sesión, pedidos del taller y productos. Sin HTML. */
require_once __DIR__ . '/../includes/helpers.php';

const EMPRESAS_ENVIO = ['Olva Courier' => 'OLV', 'Shalom' => 'SHA'];

/* El pedido tiene al menos un producto de este vendedor (un solo ? = id_vendedor) */
const SQL_PEDIDO_DEL_VENDEDOR = "EXISTS (SELECT 1 FROM Detalle_Pedidos dx JOIN Productos px ON px.id_producto = dx.id_producto
        WHERE dx.id_pedido = pe.id_pedido AND px.id_vendedor = ?)";

// function vendedorActual() { return $_SESSION['id_vendedor'] ?? null; }   // ahora está en includes/helpers.php

function exigirVendedor() {
    if (!vendedorActual()) {
        avisar('Inicia sesión como vendedor para continuar.', 'error');
        ir('vendedor_login.php');
    }
}

function buscarVendedor($usuario) {
    return fila("SELECT id_vendedor, nombres, nombre_taller, password_hash, estado FROM Vendedores WHERE username = ? OR email = ?", "ss", $usuario, $usuario);
}

/* ---------- Pedidos ---------- */
function obtenerPedidosVendedor($idv) {
    return filas("SELECT pe.id_pedido, pe.fecha_pedido, pe.estado_pedido, pe.total_pedido, c.nombres, c.apellidos,
        (SELECT mp.nombre FROM Pago pa JOIN Metodo_Pago mp ON mp.id_metodo = pa.id_metodo WHERE pa.id_pedido = pe.id_pedido ORDER BY pa.id_pago DESC LIMIT 1) AS metodo,
        (SELECT pa.estado FROM Pago pa WHERE pa.id_pedido = pe.id_pedido ORDER BY pa.id_pago DESC LIMIT 1) AS estado_pago,
        en.empresa_envio, en.numero_seguimiento,
        d.destinatario, d.telefono, d.departamento, d.provincia, d.distrito, d.direccion, d.referencia
        FROM Pedidos pe
        JOIN Compradores c ON c.id_comprador = pe.id_comprador
        LEFT JOIN Envio en ON en.id_pedido = pe.id_pedido
        LEFT JOIN Direccion d ON d.id_direccion = en.id_direccion
        WHERE " . SQL_PEDIDO_DEL_VENDEDOR . "
        ORDER BY pe.fecha_pedido DESC, pe.id_pedido DESC", "i", $idv);
}

/* Todos los productos de esos pedidos (también los de otros talleres), agrupados por pedido */
function obtenerItemsPedidosVendedor($idv) {
    $items = [];
    foreach (filas("SELECT dp.id_pedido, dp.cantidad, dp.precio_unitario, p.nombre, v.nombre_taller, (p.id_vendedor = ?) AS mio
        FROM Detalle_Pedidos dp
        JOIN Pedidos pe ON pe.id_pedido = dp.id_pedido
        JOIN Productos p ON p.id_producto = dp.id_producto
        JOIN Vendedores v ON v.id_vendedor = p.id_vendedor
        WHERE " . SQL_PEDIDO_DEL_VENDEDOR . "
        ORDER BY dp.id_detalle", "ii", $idv, $idv) as $it) {
        $items[$it['id_pedido']][] = $it;
    }
    return $items;
}

/* Qué puede hacer el vendedor con este pedido: 'enviar', 'entregar' o null */
function accionPedido($pe) {
    if ($pe['estado_pedido'] === 'Pagado') return 'enviar';
    if ($pe['estado_pedido'] === 'Pendiente' && ($pe['metodo'] ?? '') === 'Efectivo') return 'enviar'; // se cobra al entregar
    if ($pe['estado_pedido'] === 'Enviado') return 'entregar';
    return null;
}

function idEstado($nombre) {
    return (int)fila("SELECT id_estado FROM Estado_Pedido WHERE nombre = ?", "s", $nombre)['id_estado'];
}

function registrarHistorial($pe, $estado, $comentario) {
    ejecutar("INSERT INTO Historial_Compra (id_comprador, id_pedido, id_estado, total, comentario) VALUES (?, ?, ?, ?, ?)",
        "iiids", $pe['id_comprador'], $pe['id_pedido'], idEstado($estado), $pe['total_pedido'], $comentario);
}

/* Marca un pedido como enviado o entregado. Todo o nada: si algo falla no se guarda nada.
   Lanza RuntimeException con un mensaje para mostrar. */
function avanzarPedido($idv, $id_pedido, $accion, $empresa = '', $codigo = '') {
    global $conexion;
    $pe = fila("SELECT pe.id_pedido, pe.id_comprador, pe.estado_pedido, pe.total_pedido,
        (SELECT mp.nombre FROM Pago pa JOIN Metodo_Pago mp ON mp.id_metodo = pa.id_metodo WHERE pa.id_pedido = pe.id_pedido ORDER BY pa.id_pago DESC LIMIT 1) AS metodo,
        (SELECT pa.estado FROM Pago pa WHERE pa.id_pedido = pe.id_pedido ORDER BY pa.id_pago DESC LIMIT 1) AS estado_pago
        FROM Pedidos pe WHERE pe.id_pedido = ? AND " . SQL_PEDIDO_DEL_VENDEDOR, "ii", $id_pedido, $idv);

    if (!$pe) throw new RuntimeException('No encontramos ese pedido entre los de tu taller.');
    if ($accion === 'enviar' && accionPedido($pe) !== 'enviar')
        throw new RuntimeException('Este pedido todavía no se puede despachar: el pago aún no está confirmado.');
    if ($accion === 'entregar' && accionPedido($pe) !== 'entregar')
        throw new RuntimeException('Solo puedes marcar como entregado un pedido que ya fue enviado.');

    if ($accion === 'enviar') {
        if (!isset(EMPRESAS_ENVIO[$empresa])) throw new RuntimeException('Elige una empresa de envío.');
        $codigo = trim($codigo);
        if ($codigo === '') $codigo = EMPRESAS_ENVIO[$empresa] . '-' . str_pad((string)$id_pedido, 6, '0', STR_PAD_LEFT);
        $codigo = function_exists('mb_substr') ? mb_substr($codigo, 0, 100) : substr($codigo, 0, 100);
    }

    $conexion->begin_transaction();
    try {
        if ($accion === 'enviar') {
            ejecutar("UPDATE Pedidos SET estado_pedido = 'Enviado' WHERE id_pedido = ?", "i", $id_pedido);
            ejecutar("UPDATE Envio SET empresa_envio = ?, numero_seguimiento = ?, fecha_envio = NOW(), estado = 'En tránsito' WHERE id_pedido = ?",
                "ssi", $empresa, $codigo, $id_pedido);
            registrarHistorial($pe, 'Enviado', 'Despachado con ' . $empresa);
        } else {
            ejecutar("UPDATE Pedidos SET estado_pedido = 'Entregado' WHERE id_pedido = ?", "i", $id_pedido);
            ejecutar("UPDATE Envio SET fecha_entrega = NOW(), estado = 'Entregado' WHERE id_pedido = ?", "i", $id_pedido);
            if (($pe['estado_pago'] ?? '') === 'Pendiente') {   // efectivo: se cobra al entregar
                ejecutar("UPDATE Pago SET estado = 'Aprobado' WHERE id_pedido = ? AND estado = 'Pendiente'", "i", $id_pedido);
                registrarHistorial($pe, 'Pagado', 'Pago recibido contra entrega');
            }
            registrarHistorial($pe, 'Entregado', 'Entregado al cliente');
        }
        $conexion->commit();
    } catch (Throwable $t) {
        $conexion->rollback();
        throw new RuntimeException('No pudimos actualizar el pedido. Intenta de nuevo.');
    }
}

/* ---------- Productos del taller ---------- */
function obtenerProductosVendedor($idv) {
    return filas("SELECT p.id_producto, p.nombre, p.descripcion, p.precio, p.stock, " . SQL_IMAGEN . "
                  FROM Productos p WHERE p.id_vendedor = ? ORDER BY p.id_producto DESC", "i", $idv);
}

function validarPrecioStock($precio, $stock) {
    $precio = str_replace(',', '.', trim((string)$precio));
    if (!is_numeric($precio) || (float)$precio <= 0 || (float)$precio > 99999999)
        throw new RuntimeException('Escribe un precio mayor que cero.');
    if (!ctype_digit(trim((string)$stock)))
        throw new RuntimeException('El stock debe ser un número entero, 0 o más.');
    return [round((float)$precio, 2), (int)$stock];
}

function crearProducto($idv, $nombre, $descripcion, $precio, $stock) {
    $nombre = trim($nombre);
    if ($nombre === '') throw new RuntimeException('Escribe el nombre del producto.');
    $nombre = function_exists('mb_substr') ? mb_substr($nombre, 0, 150) : substr($nombre, 0, 150);
    [$p, $s] = validarPrecioStock($precio, $stock);
    return ejecutar("INSERT INTO Productos (id_vendedor, nombre, descripcion, precio, stock) VALUES (?, ?, ?, ?, ?)",
        "issdi", $idv, $nombre, trim($descripcion), $p, $s);
}

function actualizarProducto($idv, $id_producto, $precio, $stock) {
    [$p, $s] = validarPrecioStock($precio, $stock);
    if (!fila("SELECT id_producto FROM Productos WHERE id_producto = ? AND id_vendedor = ?", "ii", $id_producto, $idv))
        throw new RuntimeException('Ese producto no es de tu taller.');
    ejecutar("UPDATE Productos SET precio = ?, stock = ? WHERE id_producto = ?", "dii", $p, $s, $id_producto);
}

/* ---------- Agregado: edición completa de productos y fotos ---------- */
const FOTO_MAX_BYTES = 2 * 1024 * 1024;   // 2 MB (límite habitual de PHP)
const FOTO_TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const CARPETA_FOTOS = 'img/productos';

const SQL_IMAGEN = "(SELECT i.url FROM Imagen_Producto i WHERE i.id_producto = p.id_producto ORDER BY i.principal DESC, i.orden LIMIT 1) AS imagen,
    (SELECT i.alt FROM Imagen_Producto i WHERE i.id_producto = p.id_producto ORDER BY i.principal DESC, i.orden LIMIT 1) AS alt";

/* Solo devuelve el producto si es de este vendedor */
function obtenerProductoDelVendedor($idv, $id_producto) {
    return fila("SELECT p.id_producto, p.nombre, p.descripcion, p.precio, p.stock, " . SQL_IMAGEN . "
                 FROM Productos p WHERE p.id_producto = ? AND p.id_vendedor = ?", "ii", $id_producto, $idv);
}

/* Cifras para la cabecera de "Mis productos" */
function resumenProductosVendedor($idv) {
    $f = fila("SELECT COUNT(*) AS productos,
                      COALESCE(SUM(stock), 0) AS unidades,
                      COALESCE(SUM(stock > 0), 0) AS con_stock,
                      COALESCE(SUM(stock BETWEEN 1 AND 5), 0) AS pocos,
                      COALESCE(SUM(stock <= 0), 0) AS agotados
               FROM Productos WHERE id_vendedor = ?", "i", $idv);
    return array_map('intval', $f);
}

function recortar($t, $max) { return function_exists('mb_substr') ? mb_substr($t, 0, $max) : substr($t, 0, $max); }

/* Crea (si $id_producto es null) o actualiza un producto completo, con su foto.
   Devuelve ['id' => id del producto, 'foto_error' => mensaje o null].
   Si los datos están mal lanza RuntimeException y no guarda nada. */
function guardarProducto($idv, $id_producto, $nombre, $descripcion, $precio, $stock, $archivo = null, $quitar_foto = false) {
    $nombre = recortar(trim($nombre), 150);
    if ($nombre === '') throw new RuntimeException('Escribe el nombre del producto.');
    $descripcion = recortar(trim($descripcion), 2000);
    [$p, $s] = validarPrecioStock($precio, $stock);

    if ($id_producto) {
        if (!obtenerProductoDelVendedor($idv, $id_producto)) throw new RuntimeException('Ese producto no es de tu taller.');
        ejecutar("UPDATE Productos SET nombre = ?, descripcion = ?, precio = ?, stock = ? WHERE id_producto = ? AND id_vendedor = ?",
            "ssdiii", $nombre, $descripcion, $p, $s, $id_producto, $idv);
    } else {
        $id_producto = ejecutar("INSERT INTO Productos (id_vendedor, nombre, descripcion, precio, stock) VALUES (?, ?, ?, ?, ?)",
            "issdi", $idv, $nombre, $descripcion, $p, $s);
    }

    /* La foto va aparte: si falla, el producto igual queda guardado y se avisa */
    $foto_error = null;
    try {
        if ($archivo && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            guardarFoto($id_producto, $archivo, $nombre);
        } elseif ($quitar_foto) {
            quitarFoto($id_producto);
        }
    } catch (RuntimeException $ex) {
        $foto_error = $ex->getMessage();
    }
    return ['id' => (int)$id_producto, 'foto_error' => $foto_error];
}

/* ---------- Fotos ---------- */
function carpetaFotos() { return __DIR__ . '/../' . CARPETA_FOTOS; }

/* Borra el archivo solo si está dentro de img/productos (nunca fuera) */
function borrarArchivoFoto($url) {
    if (!$url || strpos($url, CARPETA_FOTOS . '/') !== 0) return;
    $ruta = carpetaFotos() . '/' . basename($url);
    if (is_file($ruta)) @unlink($ruta);
}

function guardarFoto($id_producto, $archivo, $alt) {
    $err = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || ($archivo['size'] ?? 0) > FOTO_MAX_BYTES)
        throw new RuntimeException('La foto pesa más de 2 MB. Usa una más liviana.');
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name']))
        throw new RuntimeException('No se pudo subir la foto. Intenta de nuevo.');

    /* Se revisa el contenido real del archivo, no su nombre */
    $info = @getimagesize($archivo['tmp_name']);
    $tipo = $info['mime'] ?? '';
    if (!isset(FOTO_TIPOS[$tipo])) throw new RuntimeException('La foto debe ser JPG, PNG o WEBP.');

    $dir = carpetaFotos();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true))
        throw new RuntimeException('El servidor no pudo crear la carpeta img/productos. Créala y dale permiso de escritura.');
    if (!is_writable($dir))
        throw new RuntimeException('El servidor no tiene permiso para guardar fotos en img/productos.');

    $nombre = 'p' . (int)$id_producto . '-' . bin2hex(random_bytes(4)) . '.' . FOTO_TIPOS[$tipo];
    if (!move_uploaded_file($archivo['tmp_name'], $dir . '/' . $nombre))
        throw new RuntimeException('No se pudo guardar la foto en el servidor.');
    $url = CARPETA_FOTOS . '/' . $nombre;
    $alt = recortar($alt, 150);

    $actual = fila("SELECT id_imagen, url FROM Imagen_Producto WHERE id_producto = ? ORDER BY principal DESC, orden LIMIT 1", "i", $id_producto);
    if ($actual) {
        ejecutar("UPDATE Imagen_Producto SET url = ?, alt = ?, principal = 1 WHERE id_imagen = ?", "ssi", $url, $alt, $actual['id_imagen']);
        borrarArchivoFoto($actual['url']);
    } else {
        ejecutar("INSERT INTO Imagen_Producto (id_producto, url, alt, orden, principal) VALUES (?, ?, ?, 1, 1)", "iss", $id_producto, $url, $alt);
    }
}

function quitarFoto($id_producto) {
    foreach (filas("SELECT id_imagen, url FROM Imagen_Producto WHERE id_producto = ?", "i", $id_producto) as $img) {
        borrarArchivoFoto($img['url']);
    }
    ejecutar("DELETE FROM Imagen_Producto WHERE id_producto = ?", "i", $id_producto);
}
