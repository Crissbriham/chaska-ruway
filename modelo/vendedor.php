<?php
/* Panel de vendedor: sesión, pedidos del taller y productos. Sin HTML. */
require_once __DIR__ . '/../includes/helpers.php';

const EMPRESAS_ENVIO = ['Olva Courier' => 'OLV', 'Shalom' => 'SHA'];

/* El pedido tiene al menos un producto de este vendedor (un solo ? = id_vendedor) */
const SQL_PEDIDO_DEL_VENDEDOR = "EXISTS (SELECT 1 FROM Detalle_Pedidos dx JOIN Productos px ON px.id_producto = dx.id_producto
        WHERE dx.id_pedido = pe.id_pedido AND px.id_vendedor = ?)";

// function vendedorActual() { return $_SESSION['id_vendedor'] ?? null; }   // ahora está en includes/helpers.php

function exigirVendedor() {
    if (vendedorActual()) return;
    if (compradorActual()) {          // un comprador no entra al panel
        avisar('Esa sección es solo para cuentas de vendedor.', 'error');
        ir('index.php');
    }
    avisar('Inicia sesión con tu cuenta de vendedor para continuar.', 'error');
    ir('login.php');
}

function buscarVendedor($usuario) {
    return fila("SELECT id_vendedor, nombres, nombre_taller, password_hash, estado FROM Vendedores WHERE username = ? OR email = ?", "ss", $usuario, $usuario);
}

/* ---------- Pedidos ---------- */
/* Marca que identifica en Historial_Compra lo que hizo cada taller (no cambia la base) */
function marcaTaller($idv) { return 'taller:' . (int)$idv . ' | '; }

const SQL_YA_HIZO = "EXISTS (SELECT 1 FROM Historial_Compra h JOIN Estado_Pedido es ON es.id_estado = h.id_estado
        WHERE h.id_pedido = pe.id_pedido AND es.nombre = '%s' AND h.comentario LIKE CONCAT('taller:', ?, ' | %%'))";

function obtenerPedidosVendedor($idv) {
    return filas("SELECT pe.id_pedido, pe.fecha_pedido, pe.estado_pedido, pe.total_pedido, c.nombres, c.apellidos,
        (SELECT mp.nombre FROM Pago pa JOIN Metodo_Pago mp ON mp.id_metodo = pa.id_metodo WHERE pa.id_pedido = pe.id_pedido ORDER BY pa.id_pago DESC LIMIT 1) AS metodo,
        (SELECT pa.estado FROM Pago pa WHERE pa.id_pedido = pe.id_pedido ORDER BY pa.id_pago DESC LIMIT 1) AS estado_pago,
        (SELECT COUNT(DISTINCT px.id_vendedor) FROM Detalle_Pedidos dx JOIN Productos px ON px.id_producto = dx.id_producto WHERE dx.id_pedido = pe.id_pedido) AS num_talleres,
        (pe.estado_pedido IN ('Enviado', 'Entregado') OR " . sprintf(SQL_YA_HIZO, 'Enviado') . ") AS yo_envie,
        (pe.estado_pedido = 'Entregado' OR " . sprintf(SQL_YA_HIZO, 'Entregado') . ") AS yo_entregue,
        en.empresa_envio, en.numero_seguimiento,
        d.destinatario, d.telefono, d.departamento, d.provincia, d.distrito, d.direccion, d.referencia
        FROM Pedidos pe
        JOIN Compradores c ON c.id_comprador = pe.id_comprador
        LEFT JOIN Envio en ON en.id_pedido = pe.id_pedido
        LEFT JOIN Direccion d ON d.id_direccion = en.id_direccion
        WHERE " . SQL_PEDIDO_DEL_VENDEDOR . "
        ORDER BY pe.fecha_pedido DESC, pe.id_pedido DESC", "iii", $idv, $idv, $idv);
}

/* Solo los productos de ESTE taller en sus pedidos (los de otros talleres no se muestran) */
function obtenerItemsPedidosVendedor($idv) {
    $items = [];
    foreach (filas("SELECT dp.id_pedido, dp.cantidad, dp.precio_unitario, p.nombre
        FROM Detalle_Pedidos dp
        JOIN Productos p ON p.id_producto = dp.id_producto
        WHERE p.id_vendedor = ?
        ORDER BY dp.id_detalle", "i", $idv) as $it) {
        $items[$it['id_pedido']][] = $it;
    }
    return $items;
}

/* Qué puede hacer el taller con SU parte del pedido: 'enviar', 'entregar' o null */
function accionPedido($pe) {
    $pagado = $pe['estado_pedido'] === 'Pagado'
           || ($pe['estado_pedido'] === 'Pendiente' && ($pe['metodo'] ?? '') === 'Efectivo');   // se cobra al entregar
    if ($pagado && empty($pe['yo_envie'])) return 'enviar';
    if ($pe['estado_pedido'] === 'Enviado' && empty($pe['yo_entregue'])) return 'entregar';
    return null;
}

function idEstado($nombre) {
    return (int)fila("SELECT id_estado FROM Estado_Pedido WHERE nombre = ?", "s", $nombre)['id_estado'];
}

function registrarHistorial($pe, $estado, $comentario) {
    ejecutar("INSERT INTO Historial_Compra (id_comprador, id_pedido, id_estado, total, comentario) VALUES (?, ?, ?, ?, ?)",
             "iiids", $pe['id_comprador'], $pe['id_pedido'], idEstado($estado), $pe['total_pedido'], $comentario);
}

/* Cuántos talleres del pedido ya registraron este paso ('Enviado' o 'Entregado') */
function talleresQueHicieron($id_pedido, $estado) {
    return (int)fila("SELECT COUNT(DISTINCT SUBSTRING_INDEX(h.comentario, ' | ', 1)) AS n
        FROM Historial_Compra h JOIN Estado_Pedido es ON es.id_estado = h.id_estado
        WHERE h.id_pedido = ? AND es.nombre = ? AND h.comentario LIKE 'taller:% | %'", "is", $id_pedido, $estado)['n'];
}

/* Marca como enviada o entregada la parte de ESTE taller. Cuando todos los talleres del pedido
   hicieron lo suyo, el pedido completo pasa a Enviado o Entregado. Todo o nada. */
function avanzarPedido($idv, $id_pedido, $accion, $empresa = '', $codigo = '') {
    global $conexion;
    $pe = null;
    foreach (obtenerPedidosVendedor($idv) as $x) if ((int)$x['id_pedido'] === (int)$id_pedido) { $pe = $x; break; }
    if (!$pe) throw new RuntimeException('No encontramos ese pedido entre los de tu taller.');
    $pe['id_comprador'] = (int)fila("SELECT id_comprador FROM Pedidos WHERE id_pedido = ?", "i", $id_pedido)['id_comprador'];

    if ($accion === 'enviar' && accionPedido($pe) !== 'enviar')
        throw new RuntimeException(!empty($pe['yo_envie']) ? 'Ya despachaste tus productos de este pedido.' : 'Este pedido todavía no se puede despachar: el pago aún no está confirmado.');
    if ($accion === 'entregar' && accionPedido($pe) !== 'entregar')
        throw new RuntimeException(!empty($pe['yo_entregue']) ? 'Ya marcaste tus productos como entregados.' : 'Podrás marcarlo como entregado cuando todos los productos del pedido hayan sido enviados.');

    if ($accion === 'enviar') {
        if (!isset(EMPRESAS_ENVIO[$empresa])) throw new RuntimeException('Elige una empresa de envío.');
        $codigo = trim($codigo);
        if ($codigo === '') $codigo = EMPRESAS_ENVIO[$empresa] . '-' . str_pad((string)$id_pedido, 6, '0', STR_PAD_LEFT) . ($pe['num_talleres'] > 1 ? '-' . (int)$idv : '');
        $codigo = recortar($codigo, 40);
    }
    $marca = marcaTaller($idv);
    $total_talleres = (int)$pe['num_talleres'];

    $conexion->begin_transaction();
    try {
        if ($accion === 'enviar') {
            registrarHistorial($pe, 'Enviado', $marca . 'Despachado con ' . $empresa . ', código ' . $codigo);
            /* Se juntan las empresas y códigos de cada taller en el único envío del pedido */
            $en = fila("SELECT empresa_envio, numero_seguimiento FROM Envio WHERE id_pedido = ?", "i", $id_pedido);
            $empresas = array_filter(array_map('trim', explode(' / ', $en['empresa_envio'] ?? '')));
            if (!in_array($empresa, $empresas, true)) $empresas[] = $empresa;
            $codigos = array_filter(array_map('trim', explode(' / ', $en['numero_seguimiento'] ?? '')));
            $codigos[] = $codigo;
            ejecutar("UPDATE Envio SET empresa_envio = ?, numero_seguimiento = ?, fecha_envio = COALESCE(fecha_envio, NOW()), estado = 'En tránsito' WHERE id_pedido = ?",
                     "ssi", recortar(implode(' / ', $empresas), 100), recortar(implode(' / ', $codigos), 100), $id_pedido);
            if (talleresQueHicieron($id_pedido, 'Enviado') >= $total_talleres) {
                ejecutar("UPDATE Pedidos SET estado_pedido = 'Enviado' WHERE id_pedido = ?", "i", $id_pedido);
            }
        } else {
            registrarHistorial($pe, 'Entregado', $marca . 'Productos entregados al cliente');
            if (talleresQueHicieron($id_pedido, 'Entregado') >= $total_talleres) {
                ejecutar("UPDATE Pedidos SET estado_pedido = 'Entregado' WHERE id_pedido = ?", "i", $id_pedido);
                ejecutar("UPDATE Envio SET fecha_entrega = NOW(), estado = 'Entregado' WHERE id_pedido = ?", "i", $id_pedido);
                if (($pe['estado_pago'] ?? '') === 'Pendiente') {   // efectivo: se cobra al entregar
                    ejecutar("UPDATE Pago SET estado = 'Aprobado' WHERE id_pedido = ? AND estado = 'Pendiente'", "i", $id_pedido);
                    registrarHistorial($pe, 'Pagado', 'Pago recibido contra entrega');
                }
            }
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
const CARPETA_FOTOS = 'imagenes/productos';

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

/* Borra el archivo solo si está dentro de imagenes/productos (nunca fuera) */
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
        throw new RuntimeException('El servidor no pudo crear la carpeta imagenes/productos. Créala y dale permiso de escritura.');
    if (!is_writable($dir))
        throw new RuntimeException('El servidor no tiene permiso para guardar fotos en imagenes/productos.');

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

/* ---------- Eliminar productos ---------- */
/* Un producto que ya se vendió está en el historial de pedidos (Detalle_Pedidos)
   y la base no deja borrarlo. Solo se eliminan los que nunca se vendieron. */
function productoTieneVentas($id_producto) {
    return (bool)fila("SELECT 1 AS si FROM Detalle_Pedidos WHERE id_producto = ? LIMIT 1", "i", $id_producto);
}

/* Ids de los productos del taller que ya tienen ventas (para la tabla de Mis productos) */
function idsProductosConVentas($idv) {
    return array_map('intval', array_column(filas("SELECT DISTINCT d.id_producto FROM Detalle_Pedidos d
        JOIN Productos p ON p.id_producto = d.id_producto WHERE p.id_vendedor = ?", "i", $idv), 'id_producto'));
}

function eliminarProducto($idv, $id_producto) {
    global $conexion;
    $p = obtenerProductoDelVendedor($idv, $id_producto);
    if (!$p) throw new RuntimeException('Ese producto no es de tu taller.');
    if (productoTieneVentas($id_producto))
        throw new RuntimeException('"' . $p['nombre'] . '" ya tiene ventas, así que no se puede borrar sin perder el historial de pedidos. Puedes dejarlo agotado para que nadie más lo compre.');

    $fotos = filas("SELECT url FROM Imagen_Producto WHERE id_producto = ?", "i", $id_producto);
    $conexion->begin_transaction();
    try {
        ejecutar("DELETE FROM Carrito_Detalle WHERE id_producto = ?", "i", $id_producto);   // lo saca de los carritos
        ejecutar("DELETE FROM Productos WHERE id_producto = ? AND id_vendedor = ?", "ii", $id_producto, $idv);
        $conexion->commit();   // favoritos, fotos y reseñas se borran solos (ON DELETE CASCADE)
    } catch (Throwable $ex) {
        $conexion->rollback();
        throw $ex;
    }
    foreach ($fotos as $f) borrarArchivoFoto($f['url']);
    return $p['nombre'];
}

/* Para productos con ventas: stock en 0, se muestra como agotado */
function dejarAgotado($idv, $id_producto) {
    $p = obtenerProductoDelVendedor($idv, $id_producto);
    if (!$p) throw new RuntimeException('Ese producto no es de tu taller.');
    ejecutar("UPDATE Productos SET stock = 0 WHERE id_producto = ? AND id_vendedor = ?", "ii", $id_producto, $idv);
    return $p['nombre'];
}