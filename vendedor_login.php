<?php
header('Location: login.php'); exit;  // ahora todos entran por login.php
require_once 'includes/helpers.php';
require_once 'modelo/vendedor.php';

if (vendedorActual()) ir('vendedor_panel.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['usuario'] ?? '');
    $clave = $_POST['clave'] ?? '';
    $f = buscarVendedor($u);
    // Acepta contraseñas encriptadas y las de texto plano que ya existen en la BD
    $ok = $f && $f['estado'] && (password_verify($clave, $f['password_hash']) || hash_equals($f['password_hash'], $clave));
    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['id_vendedor'] = (int)$f['id_vendedor'];
        $_SESSION['taller'] = $f['nombre_taller'];
        avisar('Bienvenido, ' . $f['nombres'] . '. Panel de ' . $f['nombre_taller'] . '.');
        ir('vendedor_panel.php');
    }
    $_SESSION['form'] = ['usuario' => $u];
    avisar('El usuario o la contraseña no coinciden. Revísalos e intenta de nuevo.', 'error');
    ir('vendedor_login.php');
}

$viejo = $_SESSION['form'] ?? [];
unset($_SESSION['form']);
$titulo_pagina = 'Acceso para vendedores | Chaska Ruway';
$pagina = 'v_login';
$es_vendedor = true;
include 'includes/cabecera.php';
?>
<div class="acceso">
    <form method="post" action="vendedor_login.php" class="caja">
        <h1>Acceso para vendedores</h1>
        <p class="ayuda" style="margin-bottom:1.25rem">Entra con la cuenta de tu taller para ver tus pedidos y administrar tus productos.</p>
        <div class="campo">
            <label for="usuario">Usuario o correo</label>
            <input type="text" id="usuario" name="usuario" value="<?= e($viejo['usuario'] ?? '') ?>" autocomplete="username" required>
        </div>
        <div class="campo">
            <label for="clave">Contraseña</label>
            <input type="password" id="clave" name="clave" autocomplete="current-password" required>
        </div>
        <button class="boton boton--ancho" type="submit">Entrar al panel</button>
        <p class="cambio">¿Quieres comprar? <a href="login.php">Inicia sesión como cliente</a></p>
    </form>
</div>
<?php include 'includes/pie.php'; ?>
