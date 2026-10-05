<?php
/* Ayudas generales: sesión, formato, consultas preparadas y piezas de HTML reutilizables */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../conexion.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexion->set_charset('utf8mb4');   // tildes y ñ correctas en todo el sitio

const DEPARTAMENTOS = ['Amazonas', 'Áncash', 'Apurímac', 'Arequipa', 'Ayacucho', 'Cajamarca', 'Callao', 'Cusco',
    'Huancavelica', 'Huánuco', 'Ica', 'Junín', 'La Libertad', 'Lambayeque', 'Lima', 'Loreto', 'Madre de Dios',
    'Moquegua', 'Pasco', 'Piura', 'Puno', 'San Martín', 'Tacna', 'Tumbes', 'Ucayali'];

function e($t) { return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'); }
function soles($n) { return 'S/ ' . number_format((float)$n, 2, '.', ','); }
function formatearPrecio($n) { return soles($n); }
function ir($url) { header('Location: ' . $url); exit; }
function avisar($texto, $tipo = 'ok') { $_SESSION['aviso'] = ['texto' => $texto, 'tipo' => $tipo]; }
function inicial($t) { return function_exists('mb_substr') ? mb_substr($t, 0, 1) : substr($t, 0, 1); }
function fecha($f) {
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','set','oct','nov','dic'];
    $t = strtotime($f);
    return date('j', $t) . ' ' . $meses[date('n', $t) - 1] . ' ' . date('Y', $t);
}

/* ---------- Consultas preparadas ---------- */
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

/* ---------- Sesión ---------- */
/* Una sesión es de comprador o de vendedor, nunca de los dos a la vez */
function compradorActual() { return $_SESSION['id_comprador'] ?? null; }
function vendedorActual() { return $_SESSION['id_vendedor'] ?? null; }

/* $f trae: id, nombres y, si es vendedor, nombre_taller */
function abrirSesion($rol, $f) {
    session_regenerate_id(true);
    unset($_SESSION['id_comprador'], $_SESSION['id_vendedor'], $_SESSION['taller'], $_SESSION['nombre']);
    $_SESSION['nombre'] = $f['nombres'];
    if ($rol === 'vendedor') {
        $_SESSION['id_vendedor'] = (int)$f['id'];
        $_SESSION['taller'] = $f['nombre_taller'];
    } else {
        $_SESSION['id_comprador'] = (int)$f['id'];
    }
}

/* Compara la contraseña escrita con la guardada.
   Las cuentas antiguas tienen la clave en texto plano: si coincide, se encripta en ese momento.
   Un hash guardado nunca se acepta como contraseña. */
function verificarClave($tabla, $campo_id, $f, $clave) {
    $guardada = (string)($f['password_hash'] ?? '');
    if (password_verify($clave, $guardada)) return true;
    if (strpos($guardada, '$2y$') !== 0 && $guardada !== '' && hash_equals($guardada, $clave)) {
        ejecutar("UPDATE $tabla SET password_hash = ? WHERE $campo_id = ?", "si", password_hash($clave, PASSWORD_DEFAULT), $f['id']);
        return true;
    }
    return false;
}
function paginaActual() {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    return basename($_SERVER['SCRIPT_NAME']) . ($qs !== '' ? '?' . $qs : '');
}
/* Solo se vuelve a páginas propias (evita redirecciones a otros sitios) */
function volverSeguro($url) {
    return preg_match('#^[a-z_]+\.php(\?[^\r\n]*)?(\#[\w-]*)?$#', (string)$url) ? $url : 'index.php';
}
function exigirSesion($volver, $mensaje = 'Inicia sesión para continuar.') {
    if (vendedorActual()) {
        avisar('Estás con tu cuenta de vendedor. Para comprar necesitas una cuenta de comprador.', 'error');
        ir('vendedor_panel.php');
    }
    if (!compradorActual()) {
        $_SESSION['volver'] = volverSeguro($volver);
        avisar($mensaje, 'error');
        ir('login.php');
    }
}

/* ---------- Piezas de HTML ---------- */
/* Portada del producto: foto si existe; si no, un tejido de color con la inicial */
function portada($p, $clase = '') {
    $tonos = ['anil', 'cochinilla', 'molle', 'tierra', 'maiz', 'qolle'];
    $tono = $tonos[(int)$p['id_producto'] % count($tonos)];
    $img = '';
    $url = $p['imagen'] ?? '';
    if ($url !== '' && $url !== null) {
        $remota = preg_match('#^https?://#i', $url);
        if ($remota || is_file(__DIR__ . '/../' . ltrim($url, '/'))) {
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

/* Muestra cuántas unidades hay */
function textoStock($stock) {
    $stock = (int)$stock;
    if ($stock <= 0) return '<span class="stock stock--agotado">Agotado</span>';
    if ($stock === 1) return '<span class="stock stock--poco">Última unidad</span>';
    if ($stock <= 5) return '<span class="stock stock--poco">Quedan ' . $stock . '</span>';
    return '<span class="stock">' . $stock . ' disponibles</span>';
}

function tarjeta($p, $favs, $volver) {
    $id = (int)$p['id_producto'];
    $esFav = in_array($id, $favs, true);
    ob_start(); ?>
    <article class="tarjeta">
      <a class="tarjeta__enlace" href="producto.php?id=<?= $id ?>">
        <?= portada($p) ?>
        <h3><?= e($p['nombre']) ?></h3>
      </a>
      <p class="tarjeta__taller"><?= e($p['nombre_taller']) ?>, <?= e($p['region']) ?></p>
      <div class="tarjeta__valoracion"><?= estrellas($p['promedio'], (int)$p['num_resenas']) ?></div>
      <div class="tarjeta__pie">
        <span class="precio"><?= soles($p['precio']) ?></span>
        <?= textoStock($p['stock']) ?>
      </div>
      <?php if (!vendedorActual()): /* los vendedores no guardan favoritos */ ?>
      <form method="post" action="accion.php" class="tarjeta__fav">
        <input type="hidden" name="accion" value="favorito">
        <input type="hidden" name="id_producto" value="<?= $id ?>">
        <input type="hidden" name="volver" value="<?= e($volver) ?>">
        <button type="submit" class="corazon<?= $esFav ? ' corazon--on' : '' ?>"
                aria-label="<?= $esFav ? 'Quitar de favoritos' : 'Guardar en favoritos' ?>"
                aria-pressed="<?= $esFav ? 'true' : 'false' ?>">♥</button>
      </form>
      <?php elseif ((int)($p['id_vendedor'] ?? 0) === (int)vendedorActual()): ?>
      <span class="tarjeta__mio">Tu producto</span>
      <?php endif; ?>
    </article>
    <?php return ob_get_clean();
}
