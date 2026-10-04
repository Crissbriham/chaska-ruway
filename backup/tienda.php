<?php
/* =====================================================================
   Chaska Ruway - tienda.php
   Catálogo, búsqueda, carrito, pago, pedidos, favoritos y reseñas.
   Necesita: conexion.php (crea $conexion con mysqli) y datos_tienda.sql
   ===================================================================== */
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once 'conexion.php';
$conexion->set_charset('utf8mb4');

/* ---------------------------------------------------------------------
   AYUDAS
   --------------------------------------------------------------------- */
function e($t) { return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'); }
function soles($n) { return 'S/ ' . number_format((float)$n, 2, '.', ','); }
function ir($url) { header('Location: ' . $url); exit; }
function avisar($texto, $tipo = 'ok') { $_SESSION['aviso'] = ['texto' => $texto, 'tipo' => $tipo]; }
function inicial($t) { return function_exists('mb_substr') ? mb_substr($t, 0, 1) : substr($t, 0, 1); }
function fecha($f) {
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','set','oct','nov','dic'];
    $t = strtotime($f);
    return date('j', $t) . ' ' . $meses[date('n', $t) - 1] . ' ' . date('Y', $t);
}

function filas($sql, $tipos = '', ...$params) {
    global $conexion;
    $stmt = $conexion->prepare($sql);
    if ($tipos !== '') $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $datos = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $datos;
}
function fila($sql, $tipos = '', ...$params) {
    $f = filas($sql, $tipos, ...$params);
    return $f[0] ?? null;
}
function ejecutar($sql, $tipos = '', ...$params) {
    global $conexion;
    $stmt = $conexion->prepare($sql);
    if ($tipos !== '') $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $id = $conexion->insert_id;
    $stmt->close();
    return $id;
}
function volverSeguro($url) {
    return preg_match('#^tienda\.php(\?[^\r\n]*)?$#', (string)$url) ? $url : 'tienda.php';
}
function exigirSesion($volver, $mensaje = 'Inicia sesión para continuar.') {
    if (!isset($_SESSION['id_usuario'])) {
        $_SESSION['volver'] = volverSeguro($volver);
        avisar($mensaje, 'error');
        ir('tienda.php?ver=login');
    }
}

/* Consulta base de productos: imagen principal y promedio de reseñas */
const SQL_PRODUCTO = "SELECT p.id_producto, p.nombre, p.descripcion, p.precio, p.stock,
    (SELECT i.url FROM Imagen_Producto i WHERE i.id_producto = p.id_producto ORDER BY i.principal DESC, i.orden LIMIT 1) AS imagen,
    (SELECT i.alt FROM Imagen_Producto i WHERE i.id_producto = p.id_producto ORDER BY i.principal DESC, i.orden LIMIT 1) AS alt,
    (SELECT ROUND(AVG(r.puntuacion), 1) FROM Resena r WHERE r.id_producto = p.id_producto) AS promedio,
    (SELECT COUNT(*) FROM Resena r WHERE r.id_producto = p.id_producto) AS num_resenas
    FROM Productos p";

/* Portada del producto: foto si existe; si no, un tejido de color */
function portada($p, $clase = '') {
    $tonos = ['anil', 'cochinilla', 'molle', 'tierra', 'maiz', 'qolle'];
    $tono = $tonos[(int)$p['id_producto'] % count($tonos)];
    $img = '';
    $url = $p['imagen'] ?? '';
    if ($url !== '' && $url !== null) {
        $remota = preg_match('#^https?://#i', $url);
        if ($remota || is_file(__DIR__ . '/' . ltrim($url, '/'))) {
            $alt = ($p['alt'] ?? '') !== '' ? $p['alt'] : $p['nombre'];
            $img = '<img src="' . e($url) . '" alt="' . e($alt) . '" loading="lazy" onerror="this.remove()">';
        }
    }
    return '<div class="portada portada--' . $tono . ' ' . $clase . '">'
         . '<span class="portada__letra" aria-hidden="true">' . e(inicial($p['nombre'])) . '</span>' . $img . '</div>';
}

function estrellas($promedio, $cuantas = null) {
    $p = (float)$promedio;
    $llenas = (int)round($p);
    $etiqueta = $p > 0 ? number_format($p, 1) . ' de 5 estrellas' : 'Sin reseñas';
    $html = '<span class="estrellas" role="img" aria-label="' . $etiqueta . '">';
    for ($i = 1; $i <= 5; $i++) $html .= '<span' . ($i <= $llenas ? ' class="on"' : '') . '>★</span>';
    $html .= '</span>';
    if ($cuantas !== null) {
        $html .= $cuantas > 0
            ? '<span class="nota">' . number_format($p, 1) . ' (' . $cuantas . ($cuantas == 1 ? ' reseña' : ' reseñas') . ')</span>'
            : '<span class="nota">Sin reseñas aún</span>';
    }
    return $html;
}

function textoStock($stock) {
    $stock = (int)$stock;
    if ($stock <= 0) return '<span class="stock stock--agotado">Agotado</span>';
    if ($stock <= 5) return '<span class="stock stock--poco">Quedan ' . $stock . '</span>';
    return '<span class="stock">Disponible</span>';
}

function tarjeta($p, $favs, $volver) {
    $id = (int)$p['id_producto'];
    $esFav = in_array($id, $favs, true);
    ob_start(); ?>
    <article class="tarjeta">
      <a class="tarjeta__enlace" href="tienda.php?ver=producto&amp;id=<?= $id ?>">
        <?= portada($p) ?>
        <h3><?= e($p['nombre']) ?></h3>
      </a>
      <div class="tarjeta__valoracion"><?= estrellas($p['promedio'], (int)$p['num_resenas']) ?></div>
      <div class="tarjeta__pie">
        <span class="precio"><?= soles($p['precio']) ?></span>
        <?= textoStock($p['stock']) ?>
      </div>
      <form method="post" class="tarjeta__fav">
        <input type="hidden" name="accion" value="favorito">
        <input type="hidden" name="id_producto" value="<?= $id ?>">
        <input type="hidden" name="volver" value="<?= e($volver) ?>">
        <button type="submit" class="corazon<?= $esFav ? ' corazon--on' : '' ?>"
                aria-label="<?= $esFav ? 'Quitar de favoritos' : 'Guardar en favoritos' ?>"
                aria-pressed="<?= $esFav ? 'true' : 'false' ?>">♥</button>
      </form>
    </article>
    <?php return ob_get_clean();
}

function idCarrito($id_usuario, $crear = false) {
    $f = fila("SELECT id_carrito FROM Carrito WHERE id_usuario = ?", "i", $id_usuario);
    if ($f) return (int)$f['id_carrito'];
    return $crear ? ejecutar("INSERT INTO Carrito (id_usuario) VALUES (?)", "i", $id_usuario) : null;
}

function itemsCarrito($id_usuario) {
    $idc = idCarrito($id_usuario);
    if (!$idc) return [];
    return filas("SELECT cd.id_detalle, cd.cantidad, p.id_producto, p.nombre, p.precio, p.stock,
        (SELECT i.url FROM Imagen_Producto i WHERE i.id_producto = p.id_producto ORDER BY i.principal DESC, i.orden LIMIT 1) AS imagen,
        (SELECT i.alt FROM Imagen_Producto i WHERE i.id_producto = p.id_producto ORDER BY i.principal DESC, i.orden LIMIT 1) AS alt
        FROM Carrito_Detalle cd JOIN Productos p ON p.id_producto = cd.id_producto
        WHERE cd.id_carrito = ? ORDER BY cd.id_detalle", "i", $idc);
}

$idu = $_SESSION['id_usuario'] ?? null;

/* ---------------------------------------------------------------------
   ACCIONES (formularios)
   --------------------------------------------------------------------- */
if (isset($_GET['salir']) || isset($_GET['logout'])) {
    session_destroy();
    session_start();
    avisar('Cerraste sesión.');
    ir('tienda.php');
}

$accion = $_POST['accion'] ?? '';

/* Iniciar sesión (con usuario o correo) */
if ($accion === 'login') {
    $u = trim($_POST['usuario'] ?? '');
    $clave = $_POST['clave'] ?? '';
    $f = fila("SELECT id_usuario, nombres, password_hash, estado FROM Usuarios WHERE username = ? OR email = ?", "ss", $u, $u);
    // Acepta contraseñas encriptadas (cuentas nuevas) y las de texto plano que ya existían
    $ok = $f && $f['estado'] && (password_verify($clave, $f['password_hash']) || hash_equals($f['password_hash'], $clave));
    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['id_usuario'] = (int)$f['id_usuario'];
        $_SESSION['nombre'] = $f['nombres'];
        $volver = volverSeguro($_SESSION['volver'] ?? 'tienda.php');
        unset($_SESSION['volver']);
        avisar('Hola, ' . $f['nombres'] . '.');
        ir($volver);
    }
    $_SESSION['form'] = ['usuario' => $u];
    avisar('El usuario o la contraseña no coinciden. Revísalos e intenta de nuevo.', 'error');
    ir('tienda.php?ver=login');
}

/* Crear cuenta */
if ($accion === 'registro') {
    $nombres   = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $clave     = $_POST['clave'] ?? '';
    $telefono  = $telefono === '' ? null : $telefono;

    $errores = [];
    if ($nombres === '' || $apellidos === '') $errores[] = 'Escribe tus nombres y apellidos.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Escribe un correo válido.';
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) $errores[] = 'El usuario debe tener de 3 a 50 caracteres: letras, números, punto o guion bajo.';
    if (strlen($clave) < 6) $errores[] = 'La contraseña debe tener al menos 6 caracteres.';

    if (!$errores) {
        try {
            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $nuevo = ejecutar("INSERT INTO Usuarios (username, email, password_hash, rol, nombres, apellidos, telefono)
                               VALUES (?, ?, ?, 'comprador', ?, ?, ?)", "ssssss",
                               $username, $email, $hash, $nombres, $apellidos, $telefono);
            session_regenerate_id(true);
            $_SESSION['id_usuario'] = (int)$nuevo;
            $_SESSION['nombre'] = $nombres;
            $volver = volverSeguro($_SESSION['volver'] ?? 'tienda.php');
            unset($_SESSION['volver']);
            avisar('Tu cuenta está lista, ' . $nombres . '.');
            ir($volver);
        } catch (mysqli_sql_exception $ex) {
            $errores[] = $ex->getCode() == 1062
                ? 'Ese usuario o correo ya tiene una cuenta. Inicia sesión o usa otros datos.'
                : 'No se pudo crear la cuenta: ' . $ex->getMessage();
        }
    }
    $_SESSION['form'] = compact('nombres', 'apellidos', 'email', 'telefono', 'username');
    avisar(implode(' ', $errores), 'error');
    ir('tienda.php?ver=registro');
}

/* Agregar al carrito (o comprar ahora) */
if ($accion === 'agregar') {
    $idp = (int)($_POST['id_producto'] ?? 0);
    $cant = max(1, (int)($_POST['cantidad'] ?? 1));
    $destino = 'tienda.php?ver=producto&id=' . $idp;
    exigirSesion($destino, 'Inicia sesión para agregar productos a tu carrito.');

    $prod = fila("SELECT nombre, precio, stock FROM Productos WHERE id_producto = ?", "i", $idp);
    if (!$prod) { avisar('Ese producto ya no existe.', 'error'); ir('tienda.php'); }

    $idc = idCarrito($idu, true);
    $actual = fila("SELECT id_detalle, cantidad FROM Carrito_Detalle WHERE id_carrito = ? AND id_producto = ?", "ii", $idc, $idp);
    $ya = $actual ? (int)$actual['cantidad'] : 0;
    $nueva = min($ya + $cant, (int)$prod['stock']);

    if ($nueva <= $ya) {
        avisar($ya > 0
            ? 'Ya tienes en tu carrito todas las unidades disponibles de ' . $prod['nombre'] . '.'
            : $prod['nombre'] . ' está agotado.', 'error');
        ir(isset($_POST['comprar_ya']) && $ya > 0 ? 'tienda.php?ver=pagar' : $destino);
    }

    if ($actual) {
        ejecutar("UPDATE Carrito_Detalle SET cantidad = ?, precio_unitario = ? WHERE id_detalle = ?", "idi", $nueva, $prod['precio'], $actual['id_detalle']);
    } else {
        ejecutar("INSERT INTO Carrito_Detalle (id_carrito, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)", "iiid", $idc, $idp, $nueva, $prod['precio']);
    }

    if (isset($_POST['comprar_ya'])) ir('tienda.php?ver=pagar');
    $msg = $prod['nombre'] . ' está en tu carrito.';
    if ($nueva < $ya + $cant) $msg .= ' Solo había ' . $prod['stock'] . ' unidades disponibles.';
    avisar($msg);
    ir($destino);
}

/* Cambiar cantidad o quitar del carrito */
if ($accion === 'cantidad' || $accion === 'quitar') {
    exigirSesion('tienda.php?ver=carrito');
    $idd = (int)($_POST['id_detalle'] ?? 0);
    $item = fila("SELECT cd.id_detalle, p.stock, p.nombre FROM Carrito_Detalle cd
                  JOIN Carrito c ON c.id_carrito = cd.id_carrito
                  JOIN Productos p ON p.id_producto = cd.id_producto
                  WHERE cd.id_detalle = ? AND c.id_usuario = ?", "ii", $idd, $idu);
    if ($item) {
        $cant = $accion === 'quitar' ? 0 : (int)($_POST['cantidad'] ?? 0);
        if ($cant <= 0) {
            ejecutar("DELETE FROM Carrito_Detalle WHERE id_detalle = ?", "i", $idd);
            avisar('Quitaste ' . $item['nombre'] . ' de tu carrito.');
        } else {
            $cant = min($cant, max(1, (int)$item['stock']));
            ejecutar("UPDATE Carrito_Detalle SET cantidad = ? WHERE id_detalle = ?", "ii", $cant, $idd);
            avisar('Cantidad actualizada.');
        }
    }
    ir('tienda.php?ver=carrito');
}

/* Guardar o quitar de favoritos */
if ($accion === 'favorito') {
    $idp = (int)($_POST['id_producto'] ?? 0);
    $volver = volverSeguro($_POST['volver'] ?? 'tienda.php');
    exigirSesion($volver, 'Inicia sesión para guardar favoritos.');
    $existe = fila("SELECT id_favorito FROM Favorito WHERE id_usuario = ? AND id_producto = ?", "ii", $idu, $idp);
    if ($existe) {
        ejecutar("DELETE FROM Favorito WHERE id_favorito = ?", "i", $existe['id_favorito']);
    } elseif (fila("SELECT id_producto FROM Productos WHERE id_producto = ?", "i", $idp)) {
        ejecutar("INSERT INTO Favorito (id_usuario, id_producto) VALUES (?, ?)", "ii", $idu, $idp);
    }
    ir($volver);
}

/* Publicar reseña (solo si compró el producto) */
if ($accion === 'resena') {
    $idp = (int)($_POST['id_producto'] ?? 0);
    $destino = 'tienda.php?ver=producto&id=' . $idp;
    exigirSesion($destino);
    $punt = (int)($_POST['puntuacion'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $comentario = trim($_POST['comentario'] ?? '');
    $titulo = function_exists('mb_substr') ? mb_substr($titulo, 0, 150) : substr($titulo, 0, 150);

    $compra = fila("SELECT pe.id_pedido FROM Pedidos pe JOIN Detalle_Pedidos d ON d.id_pedido = pe.id_pedido
                    WHERE pe.id_usuario = ? AND d.id_producto = ? AND pe.estado_pedido <> 'Cancelado'
                    ORDER BY pe.fecha_pedido DESC LIMIT 1", "ii", $idu, $idp);
    $ya = fila("SELECT id_resena FROM Resena WHERE id_usuario = ? AND id_producto = ?", "ii", $idu, $idp);

    if ($punt < 1 || $punt > 5) avisar('Elige de 1 a 5 estrellas.', 'error');
    elseif (!$compra) avisar('Solo puedes reseñar productos que hayas comprado.', 'error');
    elseif ($ya) avisar('Ya publicaste una reseña de este producto.', 'error');
    else {
        ejecutar("INSERT INTO Resena (id_producto, id_usuario, id_pedido, puntuacion, titulo, comentario) VALUES (?, ?, ?, ?, ?, ?)",
                 "iiiiss", $idp, $idu, $compra['id_pedido'], $punt, $titulo, $comentario);
        avisar('Tu reseña está publicada.');
    }
    ir($destino . '#resenas');
}

/* Confirmar compra: llama al procedimiento ProcesarCompraCarrito */
if ($accion === 'comprar') {
    exigirSesion('tienda.php?ver=pagar');
    $id_metodo = (int)($_POST['id_metodo'] ?? 0);
    $dir = $_POST['id_direccion'] ?? '';

    try {
        if ($dir === 'nueva') {
            $d = [];
            foreach (['destinatario', 'telefono', 'departamento', 'provincia', 'distrito', 'direccion', 'referencia', 'alias'] as $c) {
                $d[$c] = trim($_POST[$c] ?? '');
            }
            foreach (['destinatario', 'departamento', 'provincia', 'distrito', 'direccion'] as $c) {
                if ($d[$c] === '') throw new RuntimeException('Completa la dirección nueva: quién recibe, departamento, provincia, distrito y dirección.');
            }
            $tel = $d['telefono'] === '' ? null : $d['telefono'];
            $ref = $d['referencia'] === '' ? null : $d['referencia'];
            $id_direccion = ejecutar("INSERT INTO Direccion (destinatario, telefono, departamento, provincia, distrito, direccion, referencia)
                                      VALUES (?, ?, ?, ?, ?, ?, ?)", "sssssss",
                                      $d['destinatario'], $tel, $d['departamento'], $d['provincia'], $d['distrito'], $d['direccion'], $ref);
            $tiene = (int)fila("SELECT COUNT(*) AS n FROM Usuario_Direccion WHERE id_usuario = ?", "i", $idu)['n'];
            $alias = $d['alias'] !== '' ? $d['alias'] : 'Dirección ' . ($tiene + 1);
            $pred = $tiene ? 0 : 1;
            ejecutar("INSERT INTO Usuario_Direccion (id_usuario, id_direccion, alias, predeterminada) VALUES (?, ?, ?, ?)",
                     "iisi", $idu, $id_direccion, $alias, $pred);
        } else {
            $id_direccion = (int)$dir;
        }

        $stmt = $conexion->prepare("CALL ProcesarCompraCarrito(?, ?, ?)");
        $stmt->bind_param("iii", $idu, $id_metodo, $id_direccion);
        $stmt->execute();
        $res = $stmt->get_result();
        $id_pedido = (int)$res->fetch_assoc()['id_pedido'];
        $res->free();
        while ($stmt->more_results()) { $stmt->next_result(); }
        $stmt->close();

        avisar('Listo. Registramos tu pedido #' . $id_pedido . '.');
        ir('tienda.php?ver=pedidos&nuevo=' . $id_pedido);
    } catch (RuntimeException $ex) {
        avisar($ex->getMessage(), 'error');
        ir('tienda.php?ver=pagar');
    }
}

/* ---------------------------------------------------------------------
   DATOS PARA LA VISTA
   --------------------------------------------------------------------- */
$ver = $_GET['ver'] ?? 'catalogo';
if (isset($_GET['producto'])) { $ver = 'producto'; $_GET['id'] = $_GET['producto']; }
if (isset($_GET['login'])) $ver = 'login';
if (isset($_GET['registro'])) $ver = 'registro';
$vistas = ['catalogo', 'producto', 'carrito', 'pagar', 'pedidos', 'favoritos', 'login', 'registro'];
if (!in_array($ver, $vistas, true)) $ver = 'catalogo';

$privadas = ['carrito', 'pagar', 'pedidos', 'favoritos'];
if (in_array($ver, $privadas, true)) exigirSesion('tienda.php?ver=' . $ver, 'Inicia sesión para ver esta sección.');
if (in_array($ver, ['login', 'registro'], true) && $idu) ir('tienda.php');

$aviso = $_SESSION['aviso'] ?? null;
unset($_SESSION['aviso']);
$viejo = $_SESSION['form'] ?? [];
unset($_SESSION['form']);

$aqui = 'tienda.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
$favs = $idu ? array_map('intval', array_column(filas("SELECT id_producto FROM Favorito WHERE id_usuario = ?", "i", $idu), 'id_producto')) : [];
$num_carrito = $idu ? (int)fila("SELECT COALESCE(SUM(cd.cantidad), 0) AS n FROM Carrito_Detalle cd
                                 JOIN Carrito c ON c.id_carrito = cd.id_carrito WHERE c.id_usuario = ?", "i", $idu)['n'] : 0;

$titulo_pagina = 'Chaska Ruway | Artesanía peruana';
$q = '';

if ($ver === 'producto') {
    $id = (int)($_GET['id'] ?? 0);
    $p = fila(SQL_PRODUCTO . " WHERE p.id_producto = ?", "i", $id);
    if ($p) {
        $titulo_pagina = $p['nombre'] . ' | Chaska Ruway';
        $resenas = filas("SELECT r.puntuacion, r.titulo, r.comentario, r.fecha, u.nombres, u.apellidos
                          FROM Resena r JOIN Usuarios u ON u.id_usuario = r.id_usuario
                          WHERE r.id_producto = ? ORDER BY r.fecha DESC", "i", $id);
        $compro = $idu ? fila("SELECT 1 AS si FROM Pedidos pe JOIN Detalle_Pedidos d ON d.id_pedido = pe.id_pedido
                               WHERE pe.id_usuario = ? AND d.id_producto = ? AND pe.estado_pedido <> 'Cancelado' LIMIT 1", "ii", $idu, $id) : null;
        $ya_reseno = $idu ? fila("SELECT 1 AS si FROM Resena WHERE id_usuario = ? AND id_producto = ?", "ii", $idu, $id) : null;
        $en_carrito = 0;
        if ($idu) {
            $c = fila("SELECT cd.cantidad FROM Carrito_Detalle cd JOIN Carrito c ON c.id_carrito = cd.id_carrito
                       WHERE c.id_usuario = ? AND cd.id_producto = ?", "ii", $idu, $id);
            $en_carrito = $c ? (int)$c['cantidad'] : 0;
        }
        $relacionados = filas(SQL_PRODUCTO . " WHERE p.id_producto <> ? AND p.stock > 0 ORDER BY RAND() LIMIT 4", "i", $id);
    }
}

if ($ver === 'catalogo') {
    $q = trim($_GET['q'] ?? '');
    $ordenes = [
        'recientes'   => 'p.id_producto DESC',
        'valorados'   => 'promedio IS NULL, promedio DESC, num_resenas DESC',
        'precio_asc'  => 'p.precio ASC',
        'precio_desc' => 'p.precio DESC',
        'nombre'      => 'p.nombre ASC',
    ];
    $orden = isset($ordenes[$_GET['orden'] ?? '']) ? $_GET['orden'] : 'valorados';
    $solo_disponibles = isset($_GET['disponibles']);
    $where = " WHERE (p.nombre LIKE ? OR p.descripcion LIKE ?)" . ($solo_disponibles ? " AND p.stock > 0" : "");
    $like = '%' . $q . '%';
    $productos = filas(SQL_PRODUCTO . $where . " ORDER BY " . $ordenes[$orden], "ss", $like, $like);
}

if ($ver === 'carrito' || $ver === 'pagar') {
    $items = itemsCarrito($idu);
    $total = 0;
    $hay_problema = false;
    foreach ($items as $it) {
        $total += $it['precio'] * $it['cantidad'];
        if ($it['cantidad'] > $it['stock']) $hay_problema = true;
    }
    if ($ver === 'pagar') {
        if (!$items) { avisar('Tu carrito está vacío. Agrega productos para continuar.', 'error'); ir('tienda.php?ver=carrito'); }
        if ($hay_problema) { avisar('Algunos productos ya no tienen stock suficiente. Ajusta las cantidades.', 'error'); ir('tienda.php?ver=carrito'); }
        $titulo_pagina = 'Confirmar compra | Chaska Ruway';
        $direcciones = filas("SELECT d.*, ud.alias, ud.predeterminada FROM Usuario_Direccion ud
                              JOIN Direccion d ON d.id_direccion = ud.id_direccion
                              WHERE ud.id_usuario = ? ORDER BY ud.predeterminada DESC, d.id_direccion", "i", $idu);
        $metodos = filas("SELECT mp.id_metodo, mp.nombre, mp.descripcion, um.alias, um.ultimos_digitos, COALESCE(um.predeterminado, 0) AS predeterminado
                          FROM Metodo_Pago mp
                          LEFT JOIN Usuario_MetodoPago um ON um.id_metodo = mp.id_metodo AND um.id_usuario = ?
                          WHERE mp.activo = 1 ORDER BY predeterminado DESC, mp.id_metodo", "i", $idu);
        $yo = fila("SELECT nombres, apellidos, telefono FROM Usuarios WHERE id_usuario = ?", "i", $idu);
    } else {
        $titulo_pagina = 'Tu carrito | Chaska Ruway';
    }
}

if ($ver === 'pedidos') {
    $titulo_pagina = 'Mis pedidos | Chaska Ruway';
    $nuevo = (int)($_GET['nuevo'] ?? 0);
    $pedidos = filas("SELECT pe.id_pedido, pe.fecha_pedido, pe.estado_pedido, pe.total_pedido,
                      (SELECT mp.nombre FROM Pago pa JOIN Metodo_Pago mp ON mp.id_metodo = pa.id_metodo
                       WHERE pa.id_pedido = pe.id_pedido ORDER BY pa.id_pago DESC LIMIT 1) AS metodo,
                      en.empresa_envio, en.numero_seguimiento, en.estado AS estado_envio,
                      d.distrito, d.provincia, d.direccion
                      FROM Pedidos pe
                      LEFT JOIN Envio en ON en.id_pedido = pe.id_pedido
                      LEFT JOIN Direccion d ON d.id_direccion = en.id_direccion
                      WHERE pe.id_usuario = ? ORDER BY pe.fecha_pedido DESC, pe.id_pedido DESC", "i", $idu);
    $detalle = [];
    foreach (filas("SELECT dp.id_pedido, dp.cantidad, dp.precio_unitario, p.id_producto, p.nombre
                    FROM Detalle_Pedidos dp JOIN Pedidos pe ON pe.id_pedido = dp.id_pedido
                    JOIN Productos p ON p.id_producto = dp.id_producto
                    WHERE pe.id_usuario = ? ORDER BY dp.id_detalle", "i", $idu) as $d) {
        $detalle[$d['id_pedido']][] = $d;
    }
    $historial = [];
    foreach (filas("SELECT h.id_pedido, h.id_estado, MIN(h.fecha) AS fecha FROM Historial_Compra h
                    WHERE h.id_usuario = ? GROUP BY h.id_pedido, h.id_estado", "i", $idu) as $h) {
        $historial[$h['id_pedido']][(int)$h['id_estado']] = $h['fecha'];
    }
    $pasos = filas("SELECT id_estado, nombre, orden FROM Estado_Pedido WHERE nombre <> 'Cancelado' ORDER BY orden");
    $orden_estado = array_column(filas("SELECT nombre, orden FROM Estado_Pedido"), 'orden', 'nombre');
}

if ($ver === 'favoritos') {
    $titulo_pagina = 'Favoritos | Chaska Ruway';
    $favoritos = filas(SQL_PRODUCTO . " JOIN Favorito f ON f.id_producto = p.id_producto WHERE f.id_usuario = ? ORDER BY f.fecha DESC", "i", $idu);
}

if ($ver === 'login') $titulo_pagina = 'Iniciar sesión | Chaska Ruway';
if ($ver === 'registro') $titulo_pagina = 'Crear cuenta | Chaska Ruway';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo_pagina) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Alegreya:ital,wght@0,500;0,700;0,800;1,500&family=Alegreya+Sans:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --noche: #171D45;
      --anil: #26306B;
      --cochinilla: #A8174A;
      --cochinilla-osc: #85103A;
      --qolle: #E3A21A;
      --molle: #3D6B4A;
      --tierra: #8B4A2B;
      --maiz: #4E2459;
      --fondo: #F6F6F3;
      --blanco: #FFFFFF;
      --texto: #1C1F33;
      --suave: #5A5E73;
      --linea: #DCDBD3;
      --ok: #2F6B3F;
      --serif: "Alegreya", Georgia, "Times New Roman", serif;
      --sans: "Alegreya Sans", "Segoe UI", system-ui, sans-serif;
      --ancho: 1180px;
    }
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
    body { margin: 0; background: var(--fondo); color: var(--texto); font: 400 1.0625rem/1.55 var(--sans); }
    img { max-width: 100%; display: block; }
    a { color: var(--anil); }
    a:hover { color: var(--cochinilla); }
    :focus-visible { outline: 3px solid var(--qolle); outline-offset: 2px; }
    h1, h2, h3 { font-family: var(--serif); line-height: 1.15; margin: 0 0 .5em; }
    h2 { font-size: clamp(1.6rem, 3vw, 2.1rem); }
    .envoltura { max-width: var(--ancho); margin: 0 auto; padding: 0 1.25rem; }
    [id] { scroll-margin-top: 90px; }
    .sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

    /* ---------- Faja tejida (franja de identidad) ---------- */
    .faja {
      height: 26px;
      border-block: 5px solid var(--cochinilla);
      background:
        linear-gradient(135deg, var(--noche) 25%, transparent 25%) -8px 0 / 16px 16px,
        linear-gradient(225deg, var(--noche) 25%, transparent 25%) -8px 0 / 16px 16px,
        linear-gradient(315deg, var(--noche) 25%, transparent 25%) 0 0 / 16px 16px,
        linear-gradient(45deg,  var(--noche) 25%, transparent 25%) 0 0 / 16px 16px,
        var(--qolle);
    }
    .faja--fina { height: 14px; border-width: 3px; }

    /* ---------- Cabecera ---------- */
    .cabecera { background: var(--blanco); border-bottom: 1px solid var(--linea); position: sticky; top: 0; z-index: 20; }
    .cabecera .envoltura { display: flex; align-items: center; gap: 1.5rem; min-height: 68px; flex-wrap: wrap; }
    .marca { font-family: var(--serif); font-weight: 800; font-size: 1.6rem; color: var(--noche); text-decoration: none; display: flex; align-items: center; gap: .55rem; }
    .marca:hover { color: var(--noche); }
    .marca__lucero { width: 22px; height: 22px; position: relative; flex: none; }
    .marca__lucero::before, .marca__lucero::after { content: ""; position: absolute; inset: 3px; background: var(--qolle); }
    .marca__lucero::after { transform: rotate(45deg); }
    .menu { display: flex; gap: .25rem; margin-left: auto; align-items: center; flex-wrap: wrap; }
    .menu a { text-decoration: none; color: var(--texto); padding: .45rem .7rem; border-radius: 4px; font-weight: 500; }
    .menu a:hover { background: var(--fondo); color: var(--cochinilla); }
    .menu a[aria-current="page"] { color: var(--cochinilla); box-shadow: inset 0 -2px 0 var(--cochinilla); border-radius: 0; }
    .menu a.boton { color: var(--blanco); padding: .5rem 1rem; }
    .menu a.boton:hover { background: var(--cochinilla-osc); color: var(--blanco); }
    .menu .carrito-enlace { display: inline-flex; align-items: center; gap: .4rem; }
    .contador { background: var(--cochinilla); color: var(--blanco); font-size: .8rem; font-weight: 700; min-width: 1.4rem; height: 1.4rem; border-radius: 999px; display: inline-grid; place-items: center; padding: 0 .35rem; }
    .menu .saludo { color: var(--suave); padding: .45rem .3rem .45rem .7rem; }
    @media (max-width: 760px) {
      .cabecera { position: static; }
      .cabecera .envoltura { gap: .35rem 1rem; padding-block: .7rem; }
      .menu { margin-left: -.7rem; width: calc(100% + .7rem); }
      .menu .saludo { display: none; }
      .resumen { position: static; }
    }

    /* ---------- Botones y formularios ---------- */
    .boton { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; font: 700 1rem var(--sans); padding: .75rem 1.3rem; border-radius: 4px; border: 2px solid var(--cochinilla); background: var(--cochinilla); color: var(--blanco); cursor: pointer; text-decoration: none; }
    .boton:hover { background: var(--cochinilla-osc); border-color: var(--cochinilla-osc); color: var(--blanco); }
    .boton--borde { background: transparent; color: var(--cochinilla); }
    .boton--borde:hover { background: var(--cochinilla); color: var(--blanco); }
    .boton--claro { background: var(--qolle); border-color: var(--qolle); color: var(--noche); }
    .boton--claro:hover { background: #F0B53A; border-color: #F0B53A; color: var(--noche); }
    .boton[disabled] { background: var(--linea); border-color: var(--linea); color: var(--suave); cursor: not-allowed; }
    .boton--ancho { width: 100%; }
    .enlace-boton { background: none; border: 0; padding: 0; color: var(--suave); text-decoration: underline; cursor: pointer; font: inherit; font-size: .95rem; }
    .enlace-boton:hover { color: var(--cochinilla); }
    label { display: block; font-weight: 700; margin: 0 0 .3rem; font-size: .95rem; }
    input[type=text], input[type=email], input[type=password], input[type=number], input[type=search], select, textarea {
      width: 100%; font: inherit; padding: .65rem .8rem; border: 1px solid #BEBCB2; border-radius: 4px; background: var(--blanco); color: var(--texto);
    }
    textarea { min-height: 110px; resize: vertical; }
    .campo { margin-bottom: 1rem; }
    .ayuda { color: var(--suave); font-size: .9rem; margin: .25rem 0 0; }

    /* ---------- Avisos ---------- */
    .aviso { margin: 1.25rem 0 0; padding: .85rem 1.1rem; border-left: 5px solid var(--ok); background: #E9F2EA; border-radius: 0 4px 4px 0; }
    .aviso--error { border-color: var(--cochinilla); background: #F8E6EC; }

    /* ---------- Portada de producto ---------- */
    .portada { --tono: var(--anil); position: relative; aspect-ratio: 4 / 3; overflow: hidden; display: grid; place-items: center;
      background:
        linear-gradient(135deg, rgba(255,255,255,.10) 25%, transparent 25%) -14px 0 / 28px 28px,
        linear-gradient(225deg, rgba(255,255,255,.10) 25%, transparent 25%) -14px 0 / 28px 28px,
        linear-gradient(315deg, rgba(255,255,255,.10) 25%, transparent 25%) 0 0 / 28px 28px,
        linear-gradient(45deg,  rgba(255,255,255,.10) 25%, transparent 25%) 0 0 / 28px 28px,
        var(--tono); }
    .portada--anil { --tono: var(--anil); }
    .portada--cochinilla { --tono: var(--cochinilla); }
    .portada--molle { --tono: var(--molle); }
    .portada--tierra { --tono: var(--tierra); }
    .portada--maiz { --tono: var(--maiz); }
    .portada--qolle { --tono: #A86F08; }
    .portada__letra { font: 800 clamp(3.5rem, 8vw, 5rem)/1 var(--serif); color: rgba(255,255,255,.92); }
    .portada img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .portada--grande { aspect-ratio: 1 / 1; border-radius: 6px; }
    .portada--grande .portada__letra { font-size: clamp(6rem, 16vw, 10rem); }
    .portada--mini { width: 84px; aspect-ratio: 1 / 1; border-radius: 4px; flex: none; }
    .portada--mini .portada__letra { font-size: 2.2rem; }

    /* ---------- Portada principal (hero) ---------- */
    .hero { background: var(--noche); color: var(--fondo); overflow: hidden; }
    .hero .envoltura { display: grid; grid-template-columns: 1.4fr 1fr; align-items: center; gap: 2rem; padding-block: clamp(3rem, 8vw, 6rem); }
    .hero h1 { font-size: clamp(3.2rem, 10vw, 7rem); font-weight: 800; letter-spacing: -.015em; margin-bottom: .2em; }
    .hero__lema { font-size: clamp(1.2rem, 2.2vw, 1.5rem); max-width: 30ch; margin: 0 0 .6rem; }
    .hero__quechua { font-family: var(--serif); font-style: italic; color: #C9CCE6; margin: 0 0 2rem; max-width: 40ch; }
    .lucero { width: min(100%, 300px); aspect-ratio: 1; position: relative; justify-self: center; }
    .lucero::before, .lucero::after { content: ""; position: absolute; inset: 15%; background: var(--qolle); }
    .lucero::after { transform: rotate(45deg); }
    .lucero span { position: absolute; inset: 37%; background: var(--noche); border-radius: 50%; z-index: 1; }
    @media (max-width: 760px) { .hero .envoltura { grid-template-columns: 1fr; } .lucero { width: 120px; justify-self: start; order: -1; } }

    /* ---------- Catálogo ---------- */
    .seccion { padding-block: 3rem; }
    .barra-catalogo { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; margin-bottom: 1.75rem; padding: 1rem; background: var(--blanco); border: 1px solid var(--linea); border-radius: 6px; }
    .barra-catalogo .campo { margin: 0; flex: 1 1 220px; }
    .barra-catalogo .campo--orden { flex: 0 1 220px; }
    .barra-catalogo .check { display: flex; align-items: center; gap: .45rem; font-weight: 500; margin: 0 0 .7rem; }
    .resultado { color: var(--suave); margin: -.75rem 0 1.25rem; }
    .rejilla { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 1.5rem 1.25rem; }
    .tarjeta { position: relative; background: var(--blanco); border: 1px solid var(--linea); border-radius: 6px; overflow: hidden; display: flex; flex-direction: column; }
    .tarjeta:hover { border-color: var(--anil); }
    .tarjeta__enlace { text-decoration: none; color: inherit; }
    .tarjeta__enlace:hover { color: var(--cochinilla); }
    .tarjeta h3 { font-size: 1.2rem; margin: .9rem 1rem .3rem; }
    .tarjeta__valoracion { margin: 0 1rem; }
    .tarjeta__pie { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; margin: auto 1rem 1rem; padding-top: .6rem; }
    .tarjeta__fav { position: absolute; top: .6rem; right: .6rem; }
    .precio { font-family: var(--serif); font-weight: 800; font-size: 1.3rem; color: var(--noche); }
    .estrellas { color: #C8C6BE; letter-spacing: 1px; }
    .estrellas .on { color: var(--qolle); }
    .nota { color: var(--suave); font-size: .88rem; margin-left: .35rem; }
    .stock { font-size: .88rem; color: var(--ok); font-weight: 500; }
    .stock--poco { color: var(--tierra); }
    .stock--agotado { color: var(--cochinilla); }
    .corazon { width: 40px; height: 40px; border-radius: 50%; border: 0; background: rgba(255,255,255,.92); color: #A9A79F; font-size: 1.25rem; cursor: pointer; display: grid; place-items: center; }
    .corazon:hover, .corazon--on { color: var(--cochinilla); }
    .vacio { text-align: center; padding: 3rem 1rem; background: var(--blanco); border: 1px dashed #BEBCB2; border-radius: 6px; }
    .vacio p { color: var(--suave); max-width: 46ch; margin: 0 auto 1.25rem; }

    .nosotros { background: var(--blanco); border-top: 1px solid var(--linea); }
    .nosotros .envoltura { display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; padding-block: 3.5rem; }
    .nosotros p { max-width: 58ch; font-size: 1.1rem; }
    .nosotros dl { margin: 0; display: grid; gap: 1.1rem; align-content: start; }
    .nosotros dt { font-family: var(--serif); font-weight: 700; font-size: 1.15rem; }
    .nosotros dd { margin: .15rem 0 0; color: var(--suave); }
    @media (max-width: 760px) { .nosotros .envoltura { grid-template-columns: 1fr; } }

    /* ---------- Detalle de producto ---------- */
    .migas { margin: 1.5rem 0; font-size: .95rem; }
    .detalle { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: clamp(1.5rem, 4vw, 3.5rem); align-items: start; }
    .detalle__info h1 { font-size: clamp(2rem, 4.5vw, 3rem); }
    .detalle__info .precio { font-size: 2rem; display: block; margin: .8rem 0; }
    .detalle__desc { font-size: 1.12rem; max-width: 55ch; }
    .comprar { display: grid; grid-template-columns: 110px 1fr; gap: .75rem; margin: 1.5rem 0 .75rem; align-items: end; }
    .comprar .boton--borde { grid-column: 1 / -1; }
    .detalle__extra { margin-top: 1rem; }
    .garantias { margin: 1.75rem 0 0; padding: 1rem 0 0; border-top: 1px solid var(--linea); display: grid; gap: .5rem; color: var(--suave); }
    .garantias strong { color: var(--texto); }
    @media (max-width: 760px) { .detalle { grid-template-columns: 1fr; } }

    .resenas { margin-top: 3.5rem; display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); gap: 2.5rem; }
    .resumen-nota { font: 800 3.5rem/1 var(--serif); color: var(--noche); }
    .resena { padding: 1.25rem 0; border-bottom: 1px solid var(--linea); }
    .resena:first-child { padding-top: 0; }
    .resena h3 { font-size: 1.15rem; margin: .35rem 0 .3rem; }
    .resena p { margin: 0 0 .4rem; }
    .resena__autor { color: var(--suave); font-size: .9rem; }
    .caja { background: var(--blanco); border: 1px solid var(--linea); border-radius: 6px; padding: 1.25rem; }
    .elegir-estrellas { display: flex; flex-direction: row-reverse; justify-content: flex-end; flex-wrap: wrap; gap: .15rem; border: 0; padding: 0; margin: 0 0 1rem; }
    .elegir-estrellas legend { font-weight: 700; font-size: .95rem; margin-bottom: .3rem; }
    .elegir-estrellas input { position: absolute; opacity: 0; }
    .elegir-estrellas label { font-size: 2rem; color: #C8C6BE; cursor: pointer; margin: 0; font-weight: 400; line-height: 1; }
    .elegir-estrellas input:checked ~ label, .elegir-estrellas label:hover, .elegir-estrellas label:hover ~ label { color: var(--qolle); }
    .elegir-estrellas input:focus-visible + label { outline: 3px solid var(--qolle); }
    @media (max-width: 760px) { .resenas { grid-template-columns: 1fr; } }

    /* ---------- Carrito y pago ---------- */
    .pagina-titulo { margin: 2rem 0 1.5rem; font-size: clamp(2rem, 4vw, 2.6rem); }
    .dos-columnas { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(280px, 1fr); gap: 2rem; align-items: start; }
    @media (max-width: 860px) { .dos-columnas { grid-template-columns: 1fr; } }
    .lista-carrito { list-style: none; margin: 0; padding: 0; background: var(--blanco); border: 1px solid var(--linea); border-radius: 6px; }
    .item { display: flex; gap: 1rem; padding: 1rem; border-bottom: 1px solid var(--linea); align-items: center; flex-wrap: wrap; }
    .item:last-child { border-bottom: 0; }
    .item__info { flex: 1 1 200px; }
    .item__info a { font-family: var(--serif); font-weight: 700; font-size: 1.1rem; text-decoration: none; color: var(--texto); }
    .item__info a:hover { color: var(--cochinilla); }
    .item__info p { margin: .15rem 0 .25rem; color: var(--suave); font-size: .95rem; }
    .item__cantidad { display: flex; gap: .4rem; align-items: center; }
    .item__cantidad input { width: 74px; }
    .item__subtotal { font-weight: 700; min-width: 95px; text-align: right; }
    .alerta-stock { color: var(--cochinilla) !important; font-weight: 500; }
    .resumen { position: sticky; top: 88px; }
    .resumen h2 { font-size: 1.4rem; }
    .resumen dl { display: grid; grid-template-columns: 1fr auto; gap: .5rem; margin: 0 0 1.25rem; }
    .resumen dd { margin: 0; text-align: right; }
    .resumen .total { font-family: var(--serif); font-weight: 800; font-size: 1.45rem; padding-top: .6rem; border-top: 1px solid var(--linea); }
    .resumen ul { list-style: none; padding: 0; margin: 0 0 1rem; display: grid; gap: .5rem; font-size: .95rem; }
    .resumen ul li { display: flex; justify-content: space-between; gap: 1rem; }
    .grupo { border: 0; padding: 0; margin: 0 0 2rem; min-width: 0; }
    .grupo legend { font: 700 1.35rem var(--serif); margin-bottom: .85rem; padding: 0; }
    .opcion { display: flex; gap: .8rem; align-items: flex-start; padding: .9rem 1rem; background: var(--blanco); border: 1px solid var(--linea); border-radius: 6px; margin-bottom: .6rem; cursor: pointer; font-weight: 400; }
    .opcion:has(input:checked) { border-color: var(--anil); box-shadow: inset 0 0 0 1px var(--anil); }
    .opcion input { margin-top: .3rem; accent-color: var(--cochinilla); }
    .opcion strong { display: block; }
    .opcion span { color: var(--suave); font-size: .95rem; }
    .campos-nueva { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; padding: 1rem; background: var(--blanco); border: 1px solid var(--linea); border-radius: 6px; }
    .campos-nueva .completo { grid-column: 1 / -1; }
    form:has(.opcion input[value="nueva"]) .campos-nueva { display: none; }
    form:has(#dir-nueva:checked) .campos-nueva { display: grid; }
    .nota-demo { font-size: .9rem; color: var(--suave); margin: .75rem 0 0; }
    @media (max-width: 560px) { .campos-nueva { grid-template-columns: 1fr; } }

    /* ---------- Pedidos ---------- */
    .pedido { background: var(--blanco); border: 1px solid var(--linea); border-radius: 6px; margin-bottom: 1.25rem; }
    .pedido--nuevo { border-color: var(--ok); box-shadow: inset 4px 0 0 var(--ok); }
    .pedido__cabeza { display: flex; flex-wrap: wrap; gap: .5rem 2rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--linea); align-items: baseline; }
    .pedido__cabeza h2 { font-size: 1.3rem; margin: 0; }
    .pedido__cabeza .dato { color: var(--suave); font-size: .95rem; }
    .pedido__cabeza .dato strong { color: var(--texto); }
    .pedido__cuerpo { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 1.5rem; padding: 1.25rem; }
    @media (max-width: 760px) { .pedido__cuerpo { grid-template-columns: 1fr; } }
    .pedido__items { list-style: none; margin: 0; padding: 0; display: grid; gap: .45rem; align-content: start; }
    .pedido__items li { display: flex; justify-content: space-between; gap: 1rem; }
    .etiqueta { display: inline-block; font-size: .85rem; font-weight: 700; padding: .15rem .6rem; border-radius: 999px; background: #ECEBE6; color: var(--suave); }
    .etiqueta--Pagado { background: #FBF0D6; color: #7A5300; }
    .etiqueta--Enviado { background: #E3E7F7; color: var(--anil); }
    .etiqueta--Entregado { background: #E2EFE4; color: var(--ok); }
    .etiqueta--Cancelado { background: #F8E1E8; color: var(--cochinilla); }
    .seguimiento { list-style: none; margin: 0 0 1rem; padding: 0; display: grid; grid-template-columns: repeat(4, 1fr); }
    .seguimiento li { position: relative; padding-top: 1.6rem; font-size: .88rem; color: var(--suave); text-align: center; }
    .seguimiento li::before { content: ""; position: absolute; top: .35rem; left: 50%; width: 14px; height: 14px; margin-left: -7px; border-radius: 50%; background: var(--blanco); border: 2px solid #BEBCB2; z-index: 1; }
    .seguimiento li::after { content: ""; position: absolute; top: .73rem; left: -50%; width: 100%; height: 2px; background: #BEBCB2; }
    .seguimiento li:first-child::after { display: none; }
    .seguimiento li.hecho { color: var(--texto); font-weight: 500; }
    .seguimiento li.hecho::before { background: var(--molle); border-color: var(--molle); }
    .seguimiento li.hecho::after { background: var(--molle); }
    .seguimiento small { display: block; font-weight: 400; color: var(--suave); }
    .envio-dato { font-size: .95rem; color: var(--suave); margin: 0 0 .25rem; }

    /* ---------- Acceso ---------- */
    .acceso { max-width: 480px; margin: 3rem auto; }
    .acceso .caja { padding: 2rem; }
    .acceso h1 { font-size: 2.2rem; }
    .acceso .fila { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; }
    .acceso .cambio { text-align: center; margin: 1.25rem 0 0; color: var(--suave); }

    /* ---------- Pie ---------- */
    .pie { background: var(--noche); color: #C9CCE6; margin-top: 4rem; }
    .pie .envoltura { padding-block: 2rem; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: end; gap: 1rem; }
    .pie strong { font-family: var(--serif); color: var(--fondo); font-size: 1.25rem; }
    .pie p { margin: 0; }
  </style>
</head>
<body>

<header class="cabecera">
  <div class="envoltura">
    <a class="marca" href="tienda.php"><span class="marca__lucero" aria-hidden="true"></span>Chaska Ruway</a>
    <nav class="menu" aria-label="Principal">
      <a href="tienda.php#catalogo" <?= $ver === 'catalogo' ? 'aria-current="page"' : '' ?>>Catálogo</a>
      <?php if ($idu): ?>
        <a href="tienda.php?ver=favoritos" <?= $ver === 'favoritos' ? 'aria-current="page"' : '' ?>>Favoritos</a>
        <a href="tienda.php?ver=pedidos" <?= $ver === 'pedidos' ? 'aria-current="page"' : '' ?>>Mis pedidos</a>
        <a class="carrito-enlace" href="tienda.php?ver=carrito" <?= in_array($ver, ['carrito', 'pagar'], true) ? 'aria-current="page"' : '' ?>>
          Carrito <span class="contador"><?= $num_carrito ?><span class="sr"> productos</span></span>
        </a>
        <span class="saludo">Hola, <?= e($_SESSION['nombre']) ?></span>
        <a href="tienda.php?salir=1">Salir</a>
      <?php else: ?>
        <a href="tienda.php?ver=login" <?= $ver === 'login' ? 'aria-current="page"' : '' ?>>Iniciar sesión</a>
        <a class="boton" href="tienda.php?ver=registro">Crear cuenta</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<?php if ($ver === 'catalogo' && $q === ''): ?>
<section class="hero">
  <div class="envoltura">
    <div>
      <h1>Chaska Ruway</h1>
      <p class="hero__lema">Artesanía peruana, directo de quien la hace.</p>
      <p class="hero__quechua">En quechua, chaska es el lucero y ruway es hacer.</p>
      <a class="boton boton--claro" href="#catalogo">Ver el catálogo</a>
    </div>
    <div class="lucero" aria-hidden="true"><span></span></div>
  </div>
</section>
<div class="faja" aria-hidden="true"></div>
<?php else: ?>
<div class="faja faja--fina" aria-hidden="true"></div>
<?php endif; ?>

<main class="envoltura">
  <?php if ($aviso): ?>
    <div class="aviso<?= $aviso['tipo'] === 'error' ? ' aviso--error' : '' ?>" role="<?= $aviso['tipo'] === 'error' ? 'alert' : 'status' ?>"><?= e($aviso['texto']) ?></div>
  <?php endif; ?>

<?php if ($ver === 'catalogo'): /* =================== CATÁLOGO =================== */ ?>
  <section id="catalogo" class="seccion">
    <h2>Catálogo</h2>
    <form class="barra-catalogo" method="get" action="tienda.php#catalogo" role="search">
      <div class="campo">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Retablo, alpaca, Chulucanas...">
      </div>
      <div class="campo campo--orden">
        <label for="orden">Ordenar por</label>
        <select id="orden" name="orden">
          <?php foreach (['valorados' => 'Mejor valorados', 'recientes' => 'Más nuevos', 'precio_asc' => 'Precio: menor a mayor', 'precio_desc' => 'Precio: mayor a menor', 'nombre' => 'Nombre (A-Z)'] as $k => $t): ?>
            <option value="<?= $k ?>" <?= $orden === $k ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <label class="check"><input type="checkbox" name="disponibles" value="1" <?= $solo_disponibles ? 'checked' : '' ?>> Solo disponibles</label>
      <button class="boton" type="submit">Buscar</button>
    </form>

    <?php if ($q !== ''): ?>
      <p class="resultado"><?= count($productos) ?> <?= count($productos) == 1 ? 'resultado' : 'resultados' ?> para “<?= e($q) ?>”. <a href="tienda.php#catalogo">Ver todo</a></p>
    <?php endif; ?>

    <?php if ($productos): ?>
      <div class="rejilla">
        <?php foreach ($productos as $p) echo tarjeta($p, $favs, $aqui); ?>
      </div>
    <?php else: ?>
      <div class="vacio">
        <h3>No hay productos que coincidan</h3>
        <p>Prueba con otra palabra, como “cerámica” o el nombre de una región, o quita el filtro de disponibles.</p>
        <a class="boton boton--borde" href="tienda.php#catalogo">Ver todo el catálogo</a>
      </div>
    <?php endif; ?>
  </section>
</main>

<section id="nosotros" class="nosotros">
  <div class="envoltura">
    <div>
      <h2>¿Quiénes somos?</h2>
      <p>Somos una plataforma digital que conecta a maestros artesanos de todas las regiones del Perú directamente con quienes valoran su trabajo. Cada pieza llega con el nombre del taller o la comunidad que la hizo.</p>
    </div>
    <dl>
      <div><dt>Directo del taller</dt><dd>Trabajamos con asociaciones y familias artesanas, sin intermediarios.</dd></div>
      <div><dt>Envío gratis a todo el Perú</dt><dd>Despachamos con Olva Courier y Shalom. Puedes seguir tu pedido desde tu cuenta.</dd></div>
      <div><dt>Piezas hechas a mano</dt><dd>Cada producto puede variar un poco en color y forma: así se reconoce el trabajo manual.</dd></div>
    </dl>
  </div>
</section>
<main class="envoltura">

<?php elseif ($ver === 'producto'): /* =================== PRODUCTO =================== */ ?>
  <?php if (!$p): ?>
    <div class="vacio" style="margin-top:2rem">
      <h3>Este producto no existe</h3>
      <p>Puede que lo hayan retirado del catálogo.</p>
      <a class="boton" href="tienda.php#catalogo">Volver al catálogo</a>
    </div>
  <?php else: ?>
    <p class="migas"><a href="tienda.php#catalogo">Catálogo</a> / <?= e($p['nombre']) ?></p>
    <div class="detalle">
      <?= portada($p, 'portada--grande') ?>
      <div class="detalle__info">
        <h1><?= e($p['nombre']) ?></h1>
        <a href="#resenas" style="text-decoration:none"><?= estrellas($p['promedio'], (int)$p['num_resenas']) ?></a>
        <span class="precio"><?= soles($p['precio']) ?></span>
        <p class="detalle__desc"><?= e($p['descripcion']) ?></p>
        <?= textoStock($p['stock']) ?>
        <?php if ($en_carrito): ?><span class="nota">Tienes <?= $en_carrito ?> en tu carrito.</span><?php endif; ?>

        <?php if ($p['stock'] > 0): ?>
          <form method="post" class="comprar">
            <input type="hidden" name="accion" value="agregar">
            <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>">
            <div>
              <label for="cantidad">Cantidad</label>
              <input type="number" id="cantidad" name="cantidad" value="1" min="1" max="<?= (int)$p['stock'] ?>">
            </div>
            <button class="boton" type="submit">Agregar al carrito</button>
            <button class="boton boton--borde" type="submit" name="comprar_ya" value="1">Comprar ahora</button>
          </form>
        <?php else: ?>
          <div class="comprar"><button class="boton" disabled style="grid-column:1/-1">Agotado</button></div>
        <?php endif; ?>

        <form method="post" class="detalle__extra">
          <input type="hidden" name="accion" value="favorito">
          <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>">
          <input type="hidden" name="volver" value="<?= e($aqui) ?>">
          <button class="enlace-boton" type="submit"><?= in_array((int)$p['id_producto'], $favs, true) ? '♥ Quitar de favoritos' : '♡ Guardar en favoritos' ?></button>
        </form>

        <div class="garantias">
          <span><strong>Envío gratis</strong> a todo el Perú.</span>
          <span><strong>Pago</strong> con tarjeta, Yape, Plin, transferencia o efectivo al recibir.</span>
        </div>
      </div>
    </div>

    <section id="resenas" class="resenas">
      <div>
        <h2>Reseñas</h2>
        <?php if ($p['num_resenas'] > 0): ?>
          <div class="resumen-nota"><?= number_format((float)$p['promedio'], 1) ?></div>
          <?= estrellas($p['promedio']) ?>
          <span class="nota"><?= (int)$p['num_resenas'] ?> <?= $p['num_resenas'] == 1 ? 'reseña' : 'reseñas' ?></span>
        <?php else: ?>
          <p class="ayuda">Todavía nadie ha reseñado este producto.</p>
        <?php endif; ?>

        <div style="margin-top:1.5rem">
          <?php if (!$idu): ?>
            <p class="ayuda"><a href="tienda.php?ver=login">Inicia sesión</a> para reseñar productos que hayas comprado.</p>
          <?php elseif ($ya_reseno): ?>
            <p class="ayuda">Ya publicaste tu reseña de este producto.</p>
          <?php elseif (!$compro): ?>
            <p class="ayuda">Podrás dejar una reseña cuando compres este producto.</p>
          <?php else: ?>
            <form method="post" class="caja">
              <input type="hidden" name="accion" value="resena">
              <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>">
              <fieldset class="elegir-estrellas">
                <legend>Tu calificación</legend>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                  <input type="radio" id="est<?= $i ?>" name="puntuacion" value="<?= $i ?>" <?= $i === 5 ? 'required' : '' ?>>
                  <label for="est<?= $i ?>" title="<?= $i ?> de 5">★<span class="sr"><?= $i ?> de 5</span></label>
                <?php endfor; ?>
              </fieldset>
              <div class="campo">
                <label for="titulo">Título</label>
                <input type="text" id="titulo" name="titulo" maxlength="150" placeholder="Resume tu experiencia">
              </div>
              <div class="campo">
                <label for="comentario">Comentario</label>
                <textarea id="comentario" name="comentario" placeholder="¿Cómo llegó? ¿Qué te pareció la pieza?"></textarea>
              </div>
              <button class="boton" type="submit">Publicar reseña</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <?php foreach ($resenas as $r): ?>
          <article class="resena">
            <?= estrellas($r['puntuacion']) ?>
            <?php if ($r['titulo']): ?><h3><?= e($r['titulo']) ?></h3><?php endif; ?>
            <?php if ($r['comentario']): ?><p><?= nl2br(e($r['comentario'])) ?></p><?php endif; ?>
            <span class="resena__autor"><?= e($r['nombres'] . ' ' . inicial($r['apellidos']) . '.') ?>, <?= fecha($r['fecha']) ?></span>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if ($relacionados): ?>
      <section class="seccion">
        <h2>También te puede gustar</h2>
        <div class="rejilla">
          <?php foreach ($relacionados as $rp) echo tarjeta($rp, $favs, $aqui); ?>
        </div>
      </section>
    <?php endif; ?>
  <?php endif; ?>

<?php elseif ($ver === 'carrito'): /* =================== CARRITO =================== */ ?>
  <h1 class="pagina-titulo">Tu carrito</h1>
  <?php if (!$items): ?>
    <div class="vacio">
      <h3>Tu carrito está vacío</h3>
      <p>Explora el catálogo y agrega las piezas que te gusten.</p>
      <a class="boton" href="tienda.php#catalogo">Ver el catálogo</a>
    </div>
  <?php else: ?>
    <div class="dos-columnas">
      <ul class="lista-carrito">
        <?php foreach ($items as $it): ?>
          <li class="item">
            <?= portada($it, 'portada--mini') ?>
            <div class="item__info">
              <a href="tienda.php?ver=producto&amp;id=<?= (int)$it['id_producto'] ?>"><?= e($it['nombre']) ?></a>
              <p><?= soles($it['precio']) ?> c/u</p>
              <?php if ($it['cantidad'] > $it['stock']): ?>
                <p class="alerta-stock"><?= $it['stock'] > 0 ? 'Solo quedan ' . (int)$it['stock'] . '. Reduce la cantidad.' : 'Se agotó. Quítalo para continuar.' ?></p>
              <?php endif; ?>
              <form method="post">
                <input type="hidden" name="accion" value="quitar">
                <input type="hidden" name="id_detalle" value="<?= (int)$it['id_detalle'] ?>">
                <button class="enlace-boton" type="submit">Quitar</button>
              </form>
            </div>
            <form method="post" class="item__cantidad">
              <input type="hidden" name="accion" value="cantidad">
              <input type="hidden" name="id_detalle" value="<?= (int)$it['id_detalle'] ?>">
              <label class="sr" for="cant<?= (int)$it['id_detalle'] ?>">Cantidad de <?= e($it['nombre']) ?></label>
              <input type="number" id="cant<?= (int)$it['id_detalle'] ?>" name="cantidad" value="<?= (int)$it['cantidad'] ?>" min="0" max="<?= max(1, (int)$it['stock']) ?>">
              <button class="boton boton--borde" type="submit" style="padding:.55rem .8rem">Actualizar</button>
            </form>
            <span class="item__subtotal"><?= soles($it['precio'] * $it['cantidad']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>

      <aside class="caja resumen">
        <h2>Resumen</h2>
        <dl>
          <dt>Productos (<?= $num_carrito ?>)</dt><dd><?= soles($total) ?></dd>
          <dt>Envío</dt><dd>Gratis</dd>
          <dt class="total">Total</dt><dd class="total"><?= soles($total) ?></dd>
        </dl>
        <?php if ($hay_problema): ?>
          <button class="boton boton--ancho" disabled>Continuar con el pago</button>
          <p class="nota-demo">Ajusta las cantidades marcadas para continuar.</p>
        <?php else: ?>
          <a class="boton boton--ancho" href="tienda.php?ver=pagar">Continuar con el pago</a>
        <?php endif; ?>
        <p class="nota-demo"><a href="tienda.php#catalogo">Seguir comprando</a></p>
      </aside>
    </div>
  <?php endif; ?>

<?php elseif ($ver === 'pagar'): /* =================== PAGAR =================== */ ?>
  <h1 class="pagina-titulo">Confirmar compra</h1>
  <form method="post" class="dos-columnas">
    <input type="hidden" name="accion" value="comprar">
    <div>
      <fieldset class="grupo">
        <legend>Dirección de envío</legend>
        <?php foreach ($direcciones as $i => $d): ?>
          <label class="opcion">
            <input type="radio" name="id_direccion" value="<?= (int)$d['id_direccion'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
            <div>
              <strong><?= e($d['alias'] ?: 'Dirección') ?></strong>
              <span><?= e($d['destinatario']) ?>, <?= e($d['direccion']) ?>, <?= e($d['distrito']) ?>, <?= e($d['provincia']) ?>, <?= e($d['departamento']) ?></span>
            </div>
          </label>
        <?php endforeach; ?>
        <label class="opcion">
          <input type="radio" id="dir-nueva" name="id_direccion" value="nueva" <?= !$direcciones ? 'checked' : '' ?>>
          <div><strong>Usar una dirección nueva</strong><span>Se guardará en tu cuenta para próximas compras.</span></div>
        </label>
        <div class="campos-nueva">
          <div class="campo completo"><label for="destinatario">Quién recibe</label><input type="text" id="destinatario" name="destinatario" value="<?= e(trim(($yo['nombres'] ?? '') . ' ' . ($yo['apellidos'] ?? ''))) ?>"></div>
          <div class="campo"><label for="telefono">Teléfono</label><input type="text" id="telefono" name="telefono" value="<?= e($yo['telefono'] ?? '') ?>"></div>
          <div class="campo"><label for="alias">Nombre de la dirección</label><input type="text" id="alias" name="alias" placeholder="Casa, oficina..."></div>
          <div class="campo"><label for="departamento">Departamento</label><input type="text" id="departamento" name="departamento" placeholder="Lima"></div>
          <div class="campo"><label for="provincia">Provincia</label><input type="text" id="provincia" name="provincia" placeholder="Lima"></div>
          <div class="campo"><label for="distrito">Distrito</label><input type="text" id="distrito" name="distrito" placeholder="Miraflores"></div>
          <div class="campo"><label for="direccion">Dirección</label><input type="text" id="direccion" name="direccion" placeholder="Av. Larco 123, dpto 401"></div>
          <div class="campo completo"><label for="referencia">Referencia (opcional)</label><input type="text" id="referencia" name="referencia" placeholder="Frente al parque"></div>
        </div>
      </fieldset>

      <fieldset class="grupo">
        <legend>Método de pago</legend>
        <?php foreach ($metodos as $i => $m): ?>
          <label class="opcion">
            <input type="radio" name="id_metodo" value="<?= (int)$m['id_metodo'] ?>" <?= $i === 0 ? 'checked' : '' ?> required>
            <div>
              <strong><?= e($m['nombre']) ?><?= $m['ultimos_digitos'] ? ' terminada en ' . e($m['ultimos_digitos']) : '' ?></strong>
              <span><?= e($m['alias'] ? 'Guardado como “' . $m['alias'] . '”' : $m['descripcion']) ?></span>
            </div>
          </label>
        <?php endforeach; ?>
      </fieldset>
    </div>

    <aside class="caja resumen">
      <h2>Tu pedido</h2>
      <ul>
        <?php foreach ($items as $it): ?>
          <li><span><?= (int)$it['cantidad'] ?> × <?= e($it['nombre']) ?></span><span><?= soles($it['precio'] * $it['cantidad']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <dl>
        <dt>Envío</dt><dd>Gratis</dd>
        <dt class="total">Total</dt><dd class="total"><?= soles($total) ?></dd>
      </dl>
      <button class="boton boton--ancho" type="submit">Confirmar compra por <?= soles($total) ?></button>
      <p class="nota-demo">Proyecto universitario de demostración: no se realiza ningún cobro real.</p>
      <p class="nota-demo"><a href="tienda.php?ver=carrito">Volver al carrito</a></p>
    </aside>
  </form>

<?php elseif ($ver === 'pedidos'): /* =================== MIS PEDIDOS =================== */ ?>
  <h1 class="pagina-titulo">Mis pedidos</h1>
  <?php if (!$pedidos): ?>
    <div class="vacio">
      <h3>Aún no tienes pedidos</h3>
      <p>Cuando compres, aquí verás el estado de cada pedido y su envío.</p>
      <a class="boton" href="tienda.php#catalogo">Ver el catálogo</a>
    </div>
  <?php else: foreach ($pedidos as $pe):
      $id_pe = (int)$pe['id_pedido'];
      $actual = (int)($orden_estado[$pe['estado_pedido']] ?? 1);
      $cancelado = $pe['estado_pedido'] === 'Cancelado';
  ?>
    <article class="pedido<?= $id_pe === $nuevo ? ' pedido--nuevo' : '' ?>">
      <div class="pedido__cabeza">
        <h2>Pedido #<?= $id_pe ?></h2>
        <span class="dato"><?= fecha($pe['fecha_pedido']) ?></span>
        <span class="dato">Total <strong><?= soles($pe['total_pedido']) ?></strong></span>
        <?php if ($pe['metodo']): ?><span class="dato"><?= e($pe['metodo']) ?></span><?php endif; ?>
        <span class="etiqueta etiqueta--<?= e($pe['estado_pedido']) ?>"><?= e($pe['estado_pedido']) ?></span>
      </div>
      <div class="pedido__cuerpo">
        <ul class="pedido__items">
          <?php foreach ($detalle[$id_pe] ?? [] as $d): ?>
            <li>
              <span><?= (int)$d['cantidad'] ?> × <a href="tienda.php?ver=producto&amp;id=<?= (int)$d['id_producto'] ?>"><?= e($d['nombre']) ?></a></span>
              <span><?= soles($d['precio_unitario'] * $d['cantidad']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <div>
          <?php if (!$cancelado): ?>
            <ol class="seguimiento" aria-label="Estado del pedido">
              <?php foreach ($pasos as $paso): $hecho = (int)$paso['orden'] <= $actual; ?>
                <li class="<?= $hecho ? 'hecho' : '' ?>">
                  <?= e($paso['nombre']) ?>
                  <?php if ($hecho && isset($historial[$id_pe][(int)$paso['id_estado']])): ?>
                    <small><?= fecha($historial[$id_pe][(int)$paso['id_estado']]) ?></small>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
          <?php if ($pe['direccion']): ?>
            <p class="envio-dato">Envío a <?= e($pe['direccion']) ?>, <?= e($pe['distrito']) ?>.</p>
          <?php endif; ?>
          <?php if ($pe['empresa_envio']): ?>
            <p class="envio-dato"><?= e($pe['empresa_envio']) ?>, código de seguimiento <strong><?= e($pe['numero_seguimiento']) ?></strong></p>
          <?php elseif (!$cancelado && $actual < 3): ?>
            <p class="envio-dato">Verás el código de seguimiento cuando despachemos tu pedido.</p>
          <?php endif; ?>
        </div>
      </div>
    </article>
  <?php endforeach; endif; ?>

<?php elseif ($ver === 'favoritos'): /* =================== FAVORITOS =================== */ ?>
  <h1 class="pagina-titulo">Favoritos</h1>
  <?php if (!$favoritos): ?>
    <div class="vacio">
      <h3>Aún no guardas favoritos</h3>
      <p>Toca el corazón de cualquier producto para guardarlo aquí y encontrarlo después.</p>
      <a class="boton" href="tienda.php#catalogo">Ver el catálogo</a>
    </div>
  <?php else: ?>
    <div class="rejilla" style="margin-bottom:1rem">
      <?php foreach ($favoritos as $p) echo tarjeta($p, $favs, $aqui); ?>
    </div>
  <?php endif; ?>

<?php elseif ($ver === 'login'): /* =================== INICIAR SESIÓN =================== */ ?>
  <div class="acceso">
    <form method="post" class="caja">
      <h1>Iniciar sesión</h1>
      <input type="hidden" name="accion" value="login">
      <div class="campo">
        <label for="usuario">Usuario o correo</label>
        <input type="text" id="usuario" name="usuario" value="<?= e($viejo['usuario'] ?? '') ?>" autocomplete="username" required>
      </div>
      <div class="campo">
        <label for="clave">Contraseña</label>
        <input type="password" id="clave" name="clave" autocomplete="current-password" required>
      </div>
      <button class="boton boton--ancho" type="submit">Iniciar sesión</button>
      <p class="cambio">¿No tienes cuenta? <a href="tienda.php?ver=registro">Crea una</a></p>
    </form>
  </div>

<?php elseif ($ver === 'registro'): /* =================== CREAR CUENTA =================== */ ?>
  <div class="acceso">
    <form method="post" class="caja">
      <h1>Crear cuenta</h1>
      <input type="hidden" name="accion" value="registro">
      <div class="fila">
        <div class="campo"><label for="nombres">Nombres</label><input type="text" id="nombres" name="nombres" value="<?= e($viejo['nombres'] ?? '') ?>" autocomplete="given-name" required></div>
        <div class="campo"><label for="apellidos">Apellidos</label><input type="text" id="apellidos" name="apellidos" value="<?= e($viejo['apellidos'] ?? '') ?>" autocomplete="family-name" required></div>
      </div>
      <div class="campo"><label for="email">Correo</label><input type="email" id="email" name="email" value="<?= e($viejo['email'] ?? '') ?>" autocomplete="email" required></div>
      <div class="campo"><label for="telefono">Teléfono (opcional)</label><input type="text" id="telefono" name="telefono" value="<?= e($viejo['telefono'] ?? '') ?>" autocomplete="tel"></div>
      <div class="campo">
        <label for="username">Nombre de usuario</label>
        <input type="text" id="username" name="username" value="<?= e($viejo['username'] ?? '') ?>" autocomplete="username" required>
        <p class="ayuda">Letras, números, punto o guion bajo.</p>
      </div>
      <div class="campo">
        <label for="clave">Contraseña</label>
        <input type="password" id="clave" name="clave" minlength="6" autocomplete="new-password" required>
        <p class="ayuda">Mínimo 6 caracteres.</p>
      </div>
      <button class="boton boton--ancho" type="submit">Crear cuenta</button>
      <p class="cambio">¿Ya tienes cuenta? <a href="tienda.php?ver=login">Inicia sesión</a></p>
    </form>
  </div>
<?php endif; ?>
</main>

<footer class="pie">
  <div class="faja faja--fina" aria-hidden="true"></div>
  <div class="envoltura">
    <div><strong>Chaska Ruway</strong><p>Artesanía peruana, directo de quien la hace.</p></div>
    <p>Proyecto Universitario - Sistemas Operativos UPC</p>
  </div>
</footer>

</body>
</html>
