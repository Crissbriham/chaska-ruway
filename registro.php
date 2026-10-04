<?php
require_once 'includes/helpers.php';

if (compradorActual()) ir('index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $nuevo = ejecutar("INSERT INTO Compradores (username, email, password_hash, nombres, apellidos, telefono)
                               VALUES (?, ?, ?, ?, ?, ?)", "ssssss",
                               $username, $email, $hash, $nombres, $apellidos, $telefono);
            session_regenerate_id(true);
            $_SESSION['id_comprador'] = (int)$nuevo;
            $_SESSION['nombre'] = $nombres;
            $volver = volverSeguro($_SESSION['volver'] ?? 'index.php');
            unset($_SESSION['volver']);
            avisar('Tu cuenta está lista, ' . $nombres . '.');
            ir($volver);
        } catch (mysqli_sql_exception $ex) {
            $errores[] = $ex->getCode() == 1062
                ? 'Ese usuario o correo ya tiene una cuenta. Inicia sesión o usa otros datos.'
                : 'No pudimos crear la cuenta. Intenta de nuevo en un momento.';
        }
    }
    $_SESSION['form'] = compact('nombres', 'apellidos', 'email', 'telefono', 'username');
    avisar(implode(' ', $errores), 'error');
    ir('registro.php');
}

$viejo = $_SESSION['form'] ?? [];
unset($_SESSION['form']);
$titulo_pagina = 'Crear cuenta | Chaska Ruway';
$pagina = 'registro';
include 'includes/cabecera.php';
?>
  <div class="acceso">
    <form method="post" action="registro.php" class="caja">
      <h1>Crear cuenta</h1>
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
      <p class="cambio">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
    </form>
  </div>
<?php include 'includes/pie.php'; ?>
