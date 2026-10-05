<?php
require_once 'includes/helpers.php';

if (compradorActual()) ir('index.php');
if (vendedorActual()) ir('vendedor_panel.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombres   = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $clave     = $_POST['clave'] ?? '';
    $telefono  = $telefono === '' ? null : $telefono;
    $rol          = ($_POST['rol'] ?? '') === 'vendedor' ? 'vendedor' : 'comprador';
    $taller       = trim($_POST['nombre_taller'] ?? '');
    $departamento = trim($_POST['departamento'] ?? '');
    $descripcion  = trim($_POST['descripcion'] ?? '');

    $errores = [];
    if ($nombres === '' || $apellidos === '') $errores[] = 'Escribe tus nombres y apellidos.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Escribe un correo válido.';
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) $errores[] = 'El usuario debe tener de 3 a 50 caracteres: letras, números, punto o guion bajo.';
    if (strlen($clave) < 6) $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    if ($rol === 'vendedor') {
        if ($taller === '') $errores[] = 'Escribe el nombre de tu taller o asociación.';
        if (!in_array($departamento, DEPARTAMENTOS, true)) $errores[] = 'Elige el departamento de tu taller.';
    }
    if (!$errores) {   // el usuario y correo no se repiten entre compradores y vendedores
        $usado = fila("SELECT 1 AS x FROM Compradores WHERE username = ? OR email = ?
                       UNION SELECT 1 FROM Vendedores WHERE username = ? OR email = ? LIMIT 1",
                      "ssss", $username, $email, $username, $email);
        if ($usado) $errores[] = 'Ese usuario o correo ya tiene una cuenta. Inicia sesión o usa otros datos.';
    }

    if (!$errores) {
        try {
            $hash = password_hash($clave, PASSWORD_DEFAULT);
            if ($rol === 'vendedor') {
                $nuevo = ejecutar("INSERT INTO Vendedores (username, email, password_hash, nombres, apellidos, telefono, nombre_taller, departamento, descripcion)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", "sssssssss",
                                   $username, $email, $hash, $nombres, $apellidos, $telefono, $taller, $departamento, $descripcion === '' ? null : $descripcion);
                abrirSesion('vendedor', ['id' => $nuevo, 'nombres' => $nombres, 'nombre_taller' => $taller]);
                avisar('Tu taller ya está en Chaska Ruway. Agrega tu primer producto.');
                ir('vendedor_producto.php');
            }
            $nuevo = ejecutar("INSERT INTO Compradores (username, email, password_hash, nombres, apellidos, telefono)
                               VALUES (?, ?, ?, ?, ?, ?)", "ssssss",
                               $username, $email, $hash, $nombres, $apellidos, $telefono);
            abrirSesion('comprador', ['id' => $nuevo, 'nombres' => $nombres]);
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
    $_SESSION['form'] = compact('rol', 'nombres', 'apellidos', 'email', 'telefono', 'username', 'taller', 'departamento', 'descripcion');
    avisar(implode(' ', $errores), 'error');
    ir('registro.php');
}

$viejo = $_SESSION['form'] ?? [];
unset($_SESSION['form']);
$rol_elegido = ($viejo['rol'] ?? ($_GET['rol'] ?? '')) === 'vendedor' ? 'vendedor' : 'comprador';
$titulo_pagina = 'Crear cuenta | Chaska Ruway';
$pagina = 'registro';
include 'includes/cabecera.php';
?>
  <div class="acceso acceso--ancho">
    <form method="post" action="registro.php" class="caja">
      <h1>Crear cuenta</h1>
      <fieldset class="rol">
        <legend>¿Qué quieres hacer en Chaska Ruway?</legend>
        <label class="opcion">
          <input type="radio" id="rol-comprador" name="rol" value="comprador" <?= $rol_elegido === 'comprador' ? 'checked' : '' ?>>
          <div><strong>Comprar</strong><span>Compra piezas, guarda favoritos y sigue tus pedidos.</span></div>
        </label>
        <label class="opcion">
          <input type="radio" id="rol-vendedor" name="rol" value="vendedor" <?= $rol_elegido === 'vendedor' ? 'checked' : '' ?>>
          <div><strong>Vender mis productos</strong><span>Publica las piezas de tu taller y despacha tus pedidos.</span></div>
        </label>
      </fieldset>
      <div class="fila">
        <div class="campo"><label for="nombres">Nombres</label><input type="text" id="nombres" name="nombres" value="<?= e($viejo['nombres'] ?? '') ?>" autocomplete="given-name" required></div>
        <div class="campo"><label for="apellidos">Apellidos</label><input type="text" id="apellidos" name="apellidos" value="<?= e($viejo['apellidos'] ?? '') ?>" autocomplete="family-name" required></div>
      </div>
      <div class="campo"><label for="email">Correo</label><input type="email" id="email" name="email" value="<?= e($viejo['email'] ?? '') ?>" autocomplete="email" required></div>
      <div class="campo"><label for="telefono">Teléfono (opcional)</label><input type="text" id="telefono" name="telefono" value="<?= e($viejo['telefono'] ?? '') ?>" autocomplete="tel"></div>
      <div class="solo-vendedor">
        <div class="campo">
          <label for="nombre_taller">Nombre del taller o asociación</label>
          <input type="text" id="nombre_taller" name="nombre_taller" maxlength="150" value="<?= e($viejo['taller'] ?? '') ?>" placeholder="Ej. Taller Huamán">
          <p class="ayuda">Así aparecerá en cada uno de tus productos.</p>
        </div>
        <div class="campo">
          <label for="departamento">Departamento</label>
          <select id="departamento" name="departamento">
            <option value="">Elige uno</option>
            <?php foreach (DEPARTAMENTOS as $dep): ?>
              <option <?= ($viejo['departamento'] ?? '') === $dep ? 'selected' : '' ?>><?= e($dep) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="descripcion">Cuéntanos de tu taller (opcional)</label>
          <textarea id="descripcion" name="descripcion" placeholder="Qué hacen, con qué técnica, desde cuándo..."><?= e($viejo['descripcion'] ?? '') ?></textarea>
        </div>
      </div>
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
