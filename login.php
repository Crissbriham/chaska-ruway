<?php
require_once 'includes/helpers.php';

if (compradorActual()) ir('index.php');
if (vendedorActual()) ir('vendedor_panel.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['usuario'] ?? '');
    $clave = $_POST['clave'] ?? '';
    $cuentas = [];
    $c = fila("SELECT id_comprador AS id, nombres, password_hash, estado FROM Compradores WHERE username = ? OR email = ?", "ss", $u, $u);
    if ($c) $cuentas[] = ['rol' => 'comprador', 'tabla' => 'Compradores', 'campo' => 'id_comprador', 'f' => $c];
    $v = fila("SELECT id_vendedor AS id, nombres, nombre_taller, password_hash, estado FROM Vendedores WHERE username = ? OR email = ?", "ss", $u, $u);
    if ($v) $cuentas[] = ['rol' => 'vendedor', 'tabla' => 'Vendedores', 'campo' => 'id_vendedor', 'f' => $v];

    // Revisa si la cuenta es de comprador o de vendedor y lo lleva a su sección
    foreach ($cuentas as $cta) {
        if (!verificarClave($cta['tabla'], $cta['campo'], $cta['f'], $clave)) continue;
        if (!$cta['f']['estado']) { avisar('Esta cuenta está desactivada.', 'error'); ir('login.php'); }
        abrirSesion($cta['rol'], $cta['f']);
        if ($cta['rol'] === 'vendedor') {
            avisar('Hola, ' . $cta['f']['nombres'] . '. Este es el panel de ' . $cta['f']['nombre_taller'] . '.');
            ir('vendedor_panel.php');
        }
        $volver = volverSeguro($_SESSION['volver'] ?? 'index.php');
        unset($_SESSION['volver']);
        avisar('Hola, ' . $cta['f']['nombres'] . '.');
        ir($volver);
    }
    $_SESSION['form'] = ['usuario' => $u];
    avisar('El usuario o la contraseña no coinciden. Revísalos e intenta de nuevo.', 'error');
    ir('login.php');
}

$viejo = $_SESSION['form'] ?? [];
unset($_SESSION['form']);
$titulo_pagina = 'Iniciar sesión | Chaska Ruway';
$pagina = 'login';
include 'includes/cabecera.php';
?>
<div class="acceso">
    <form method="post" action="login.php" class="caja">
        <h1>Iniciar sesión</h1>
        <div class="campo">
            <label for="usuario">Usuario o correo</label>
            <input type="text" id="usuario" name="usuario" value="<?= e($viejo['usuario'] ?? '') ?>" autocomplete="username" required>
        </div>
        <div class="campo">
            <label for="clave">Contraseña</label>
            <input type="password" id="clave" name="clave" autocomplete="current-password" required>
        </div>
        <button class="boton boton--ancho" type="submit">Iniciar sesión</button>
        <p class="cambio">¿No tienes cuenta? <a href="registro.php">Crea una</a></p>
    </form>
</div>
<?php include 'includes/pie.php'; ?>
