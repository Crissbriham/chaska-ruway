<?php
session_start();

// 1. CONEXIÓN CON LA BASE DE DATOS
require_once 'conexion.php';

// 2. LÓGICA DE LOGIN
if(isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $sql = "SELECT id_cliente FROM usuarios WHERE username='$user' AND password_hash='$pass'";
    $resultado = $conexion->query($sql);
    
    if($resultado && $resultado->num_rows > 0) {
        $_SESSION['id_cliente'] = $resultado->fetch_assoc()['id_cliente'];
        $_SESSION['nombre'] = $user;
    } else {
        $error_login = "Usuario o clave incorrecta";
    }
}

if(isset($_GET['logout'])) { 
    session_destroy(); 
    header("Location: tienda.php"); 
    exit();
}

// 3. LÓGICA DE REGISTRO NUEVO
if(isset($_POST['btn_registrar'])) {
    $nombres = $_POST['nombres'];
    $email = $_POST['email'];
    $direccion = $_POST['direccion_envio'];
    
    $nuevo_user = $_POST['nuevo_user'];
    $nueva_pass = $_POST['password_reg'];
    
    $conexion->begin_transaction();
    
    try {
        $stmt_cliente = $conexion->prepare("INSERT INTO clientes (nombres, email, direccion_envio) VALUES (?, ?, ?)");
        if (!$stmt_cliente) throw new Exception("Error al preparar Cliente: " . $conexion->error);
        
        $stmt_cliente->bind_param("sss", $nombres, $email, $direccion);
        $stmt_cliente->execute();
        
        $id_cliente_nuevo = $conexion->insert_id;
        $stmt_cliente->close();
        
        $stmt_user = $conexion->prepare("INSERT INTO usuarios (id_cliente, username, password_hash) VALUES (?, ?, ?)");
        if (!$stmt_user) throw new Exception("Error al preparar Usuario: " . $conexion->error);
        
        $stmt_user->bind_param("iss", $id_cliente_nuevo, $nuevo_user, $nueva_pass);
        $stmt_user->execute();
        $stmt_user->close();
        
        $conexion->commit();
        $msg_registro = "<div class='alerta'>¡Registro exitoso! Ya puedes iniciar sesión.</div>";
        
    } catch (Exception $e) {
        $conexion->rollback();
        $msg_registro = "<div class='alerta error' style='font-size: 0.9rem;'>" . $e->getMessage() . "</div>";
    }
}

// 4. LÓGICA DE COMPRA (Stored Procedure)
if(isset($_POST['comprar']) && isset($_SESSION['id_cliente'])) {
    $id_cliente = $_SESSION['id_cliente'];
    $id_producto = $_POST['id_producto']; 
    $cantidad = 1; 
    
    $stmt = $conexion->prepare("CALL ProcesarCompraDirecta(?, ?, ?)");
    $stmt->bind_param("iii", $id_cliente, $id_producto, $cantidad);
    
    if($stmt->execute()) {
        $msg_compra = "<div class='alerta'>¡Éxito! Compra procesada por Stored Procedure. Stock descontado.</div>";
    }
    $stmt->close();
}

// 5. CONSULTAR TODO EL CATÁLOGO
$query_catalogo = "SELECT p.id_producto, p.nombre, p.precio, p.stock, c.nombre AS categoria, a.nombre_completo AS artesano, a.region_origen AS region 
                   FROM productos p 
                   JOIN categorias c ON p.id_categoria = c.id_categoria 
                   JOIN artesanos a ON p.id_artesano = a.id_artesano";
$res_catalogo = $conexion->query($query_catalogo); 
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Chaska Ruway | Artesanía Peruana</title>
  <link href="https://fonts.googleapis.com/css2?family=Alegreya:wght@500;700;800&family=Alegreya+Sans:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="estilos.css">
</head>
<body>
  <div class="tejido" aria-hidden="true"></div>
  
  <header>
    <div class="barra">
      <a class="marca" href="tienda.php">Chaska Ruway</a>
      <nav class="menu-horizontal">
        <a href="tienda.php#catalogo">Catálogo</a>
        <a href="tienda.php#nosotros">Nosotros</a>
        <?php if(isset($_SESSION['id_cliente'])): ?>
          <a href="#">Hola, <?php echo htmlspecialchars($_SESSION['nombre']); ?></a>
          <a href="?logout=1" class="boton">Salir</a>
        <?php else: ?>
          <a href="?login=1">Iniciar Sesión</a>
          <a href="?registro=1" class="boton">Crear Cuenta</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <?php if(isset($_GET['producto'])): 
    $id_prod = (int)$_GET['producto'];
    $res_detalle = $conexion->query("SELECT p.id_producto, p.nombre, p.precio, p.stock, c.nombre AS categoria, a.nombre_completo AS artesano, a.region_origen AS region FROM Productos p JOIN Categorias c ON p.id_categoria = c.id_categoria JOIN Artesanos a ON p.id_artesano = a.id_artesano WHERE p.id_producto = $id_prod");
    $detalle = $res_detalle ? $res_detalle->fetch_assoc() : null;
  ?>
  <main>
    <div class="contenedor">
      <a href="tienda.php">← Volver al catálogo</a>
      
      <?php if(isset($msg_compra)) echo "<br><br>".$msg_compra; ?>

      <?php if($detalle): ?>
      <div class="detalle">
        <div class="foto">📦</div>
        <div>
          <p><strong><?php echo htmlspecialchars($detalle['categoria']); ?></strong></p>
          <h1><?php echo htmlspecialchars($detalle['nombre']); ?></h1>
          <p>Hecho por <strong><?php echo htmlspecialchars($detalle['artesano']); ?></strong>, <?php echo htmlspecialchars($detalle['region']); ?></p>
          <span class="precio">S/ <?php echo $detalle['precio']; ?></span>
          
          <dl class="ficha">
            <dt>Stock Disponible</dt>
            <dd><?php echo $detalle['stock']; ?> unidades</dd>
          </dl>

          <?php if(!isset($_SESSION['id_cliente'])): ?>
            <p><strong>Debes iniciar sesión para comprar.</strong></p>
          <?php elseif($detalle['stock'] > 0): ?>
            <form method="POST" action="tienda.php?producto=<?php echo $detalle['id_producto']; ?>">
              <input type="hidden" name="id_producto" value="<?php echo $detalle['id_producto']; ?>">
              <button class="boton grande" type="submit" name="comprar">PAGAR AHORA</button>
            </form>
          <?php else: ?>
            <button class="boton grande" disabled>Agotado</button>
          <?php endif; ?>
        </div>
      </div>
      <?php else: ?>
        <p>Producto no encontrado.</p>
      <?php endif; ?>
    </div>
  </main>

  <?php elseif(isset($_GET['login']) || isset($_GET['registro'])): ?>
    <main>
      <div class="contenedor">
        <div class="formulario">
          
          <?php if(isset($_GET['login'])): ?>
            <h2>Ingresar</h2>
            <form method="POST" action="tienda.php?login=1">
              <label>Usuario</label>
              <input type="text" name="username" placeholder="ej. cmendoza85" required>
              <label>Contraseña</label>
              <input type="password" name="password" placeholder="******" required>
              <button type="submit" name="login" class="boton grande">Iniciar Sesión</button>
            </form>
            <?php if(isset($error_login)) echo "<div class='alerta error'>$error_login</div>"; ?>
          
          <?php elseif(isset($_GET['registro'])): ?>
            <h2>Crear Cuenta</h2>
            
            <?php if(isset($msg_registro)) echo $msg_registro; ?>
            
            <form method="POST" action="tienda.php?registro=1">
              <label>Nombres Completos</label>
              <input type="text" name="nombres" placeholder="Ej. Juan Pérez" required>
              
              <label>Correo Electrónico</label>
              <input type="email" name="email" placeholder="juan@correo.com" required>
              
              <label>Dirección de Envío (Opcional)</label>
              <input type="text" name="direccion_envio" placeholder="Av. Los Incas 123">
              
              <hr style="border: 0; border-top: 1px solid var(--linea); margin: 20px 0;">
              
              <label>Nombre de Usuario</label>
              <input type="text" name="nuevo_user" placeholder="ej. jperez88" required>
              
              <label>Contraseña</label>
              <input type="password" name="password_reg" placeholder="******" required>
              
              <button type="submit" name="btn_registrar" class="boton grande">Registrarme</button>
            </form>
            <br>
            <div style="text-align: center;">
                <a href="tienda.php">Volver al inicio</a>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </main>

  <?php else: ?>
    <section class="hero">
      <h1>Chaska Ruway</h1>
      <p>Artesanía peruana, directo de quien la hace.</p>
    </section>

    <main>
      <section id="nosotros" class="quienes-somos">
        <div class="texto">
          <h2>¿Quiénes Somos?</h2>
          <p>En quechua, <strong>chaska</strong> es el lucero y <strong>ruway</strong> es hacer. Somos una plataforma digital que conecta a los maestros artesanos de todas las regiones del Perú directamente con el mundo.</p>
        </div>
        <div class="imagen-placeholder">🏺</div>
      </section>

      <section id="catalogo" class="contenedor">
        <h2>Nuestro Catálogo</h2>
        
        <ul class="rejilla">
          <?php 
          if($res_catalogo && $res_catalogo->num_rows > 0):
            while($prod = $res_catalogo->fetch_assoc()): ?>
              <li>
                <a class="tarjeta" href="?producto=<?php echo $prod['id_producto']; ?>">
                  <div class="foto">📦<span class="chip"><?php echo htmlspecialchars($prod['categoria']); ?></span></div>
                  <div class="cuerpo">
                    <h3><?php echo htmlspecialchars($prod['nombre']); ?></h3>
                    <p class="autor"><?php echo htmlspecialchars($prod['artesano']); ?> · <?php echo htmlspecialchars($prod['region']); ?></p>
                    <span class="precio">S/ <?php echo $prod['precio']; ?></span>
                    <span>Stock: <?php echo $prod['stock']; ?></span>
                  </div>
                </a>
              </li>
            <?php endwhile;
          else: ?>
            <p>No se encontraron productos en el catálogo.</p>
          <?php endif; ?>
        </ul>
      </section>
    </main>
  <?php endif; ?>

  <footer style="text-align: center; padding: 2rem;">
    <p>Proyecto Universitario - Sistemas Operativos UPC</p>
  </footer>
</body>
</html>