<?php
require_once 'includes/helpers.php';

if (compradorActual()) ir('index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['usuario'] ?? '');
    $clave = $_POST['clave'] ?? '';
    $f = fila("SELECT id_comprador, nombres, password_hash, estado FROM Compradores WHERE username = ? OR email = ?", "ss", $u, $u);
    // Acepta contraseñas encriptadas (cuentas nuevas) y las de texto plano que ya existen en la BD
    $ok = $f && $f['estado'] && (password_verify($clave, $f['password_hash']) || hash_equals($f['password_hash'], $clave));
    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['id_comprador'] = (int)$f['id_comprador'];
        $_SESSION['nombre'] = $f['nombres'];
        $volver = volverSeguro($_SESSION['volver'] ?? 'index.php');
        unset($_SESSION['volver']);
        avisar('Hola, ' . $f['nombres'] . '.');
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
