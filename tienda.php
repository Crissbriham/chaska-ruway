<?php
session_start();
// 1 CONEXION CON MI BD
require_once 'conexion.php';
// 2. LÓGICA DE LOGIN
if(isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $sql = "SELECT id_cliente FROM Usuarios WHERE username='$user' AND password_hash='$pass'";
    $resultado = $conexion->query($sql);
    if($resultado && $resultado->num_rows > 0) {
        $_SESSION['id_cliente'] = $resultado->fetch_assoc()['id_cliente'];
        $_SESSION['nombre'] = $user;
    } else {
        $error_login = "Usuario o clave incorrecta";
    }
}
if(isset($_GET['logout'])) { session_destroy(); header("Location: tienda.php"); }

// 3. LÓGICA DE COMPRA (Stored Procedure)
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

// 4. CONSULTAR TODO EL CATÁLOGO
   $query_catalogo = "SELECT p.id_producto, p.nombre, p.precio, p.stock, c.nombre AS categoria, a.nombre_completo AS artesano, a.region_origen AS region 
                      FROM Productos p 
                      JOIN Categorias c ON p.id_categoria = c.id_categoria 
                      JOIN Artesanos a ON p.id_artesano = a.id_artesano";
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
  
  <!-- CABECERA Y MENÚ HORIZONTAL -->
  <header>
    <div class="barra">
      <a class="marca" href="tienda.php">Chaska Ruway</a>
<nav class="menu-horizontal">
        <a href="tienda.php#catalogo">Catálogo</a>
        <a href="tienda.php#nosotros">Nosotros</a>
        <?php if(isset($_SESSION['id_cliente'])): ?>
          <a href="#" style="color: var(--qolle);">Hola, <?php echo $_SESSION['nombre']; ?></a>
          <a href="?logout=1" class="boton pequeno">Salir</a>
        <?php else: ?>
          <a href="?login=1">Iniciar Sesión</a>
          <a href="?registro=1" class="boton pequeno" style="background: var(--qolle); color: var(--indigo);">Crear Cuenta</a>
        <?php endif; ?>
      
      </nav>
    </div>
  </header>
<?php if(isset($_GET['producto'])): 
    // 1. VISTA DE DETALLE DEL PRODUCTO (Se oculta el Hero y Nosotros)
    $id_prod = (int)$_GET['producto'];
    $res_detalle = $conexion->query("SELECT p.id_producto, p.nombre, p.precio, p.stock, c.nombre AS categoria, a.nombre_completo AS artesano, a.region_origen AS region FROM Productos p JOIN Categorias c ON p.id_categoria = c.id_categoria JOIN Artesanos a ON p.id_artesano = a.id_artesano WHERE p.id_producto = $id_prod");
    $detalle = $res_detalle->fetch_assoc();
  ?>
  <main style="padding-top: 2rem;">
    <div class="contenedor">
      <a href="tienda.php" style="color: var(--suave); text-decoration: none;">← Volver al catálogo</a>
      
      <?php if(isset($msg_compra)) echo "<br><br>".$msg_compra; ?>

      <div class="detalle" style="margin-top: 1rem;">
        <div class="foto">📦</div>
        <div>
          <p style="color: var(--cochinilla); font-weight: bold; text-transform: uppercase; margin: 0;"><?php echo $detalle['categoria']; ?></p>
          <h1><?php echo $detalle['nombre']; ?></h1>
          <p>Hecho por <strong><?php echo $detalle['artesano']; ?></strong>, <?php echo $detalle['region']; ?></p>
          <span class="precio">S/ <?php echo $detalle['precio']; ?></span>
          
          <dl class="ficha">
            <dt>Stock Disponible</dt>
            <dd style="color: <?php echo ($detalle['stock'] > 0) ? 'var(--verde)' : 'var(--cochinilla)'; ?>; font-weight: bold; font-size: 1.2rem;">
              <?php echo $detalle['stock']; ?> unidades
            </dd>
          </dl>

          <?php if(!isset($_SESSION['id_cliente'])): ?>
            <p style="color: var(--cochinilla); font-weight: bold;">Debes iniciar sesión para comprar.</p>
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
    </div>
  </main>

<?php else: ?>

    <?php if(isset($_GET['login']) || isset($_GET['registro'])): ?>
      <!-- PANTALLAS DE LOGIN Y REGISTRO AISLADAS -->
      <main style="padding-top: 4rem; min-height: 60vh;">
        <div class="contenedor">
          <div class="formulario" style="max-width: 400px; margin: 0 auto; background: white; padding: 2rem; border: 1px solid var(--linea); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            
            <?php if(isset($_GET['login'])): ?>
              <h2 style="text-align: center; color: var(--indigo); margin-top:0;">Ingresar</h2>
              <form method="POST" action="tienda.php?login=1">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Usuario</label>
                <input type="text" name="username" placeholder="ej. cmendoza85" required style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px;">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Contraseña</label>
                <input type="password" name="password" placeholder="******" required style="width: 100%; padding: 10px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 4px;">
                <button type="submit" name="login" class="boton" style="width: 100%; padding: 10px; font-size: 1.1rem;">Iniciar Sesión</button>
              </form>
              <?php if(isset($error_login)) echo "<p style='color:var(--cochinilla); text-align:center; font-weight:bold; margin-top:15px;'>$error_login</p>"; ?>
            
            <?php elseif(isset($_GET['registro'])): ?>
              <h2 style="text-align: center; color: var(--indigo); margin-top:0;">Crear Cuenta</h2>
              <p style="text-align: center; color: var(--suave);">El formulario de registro de nuevos clientes se conectará a la base de datos en el examen parcial.</p>
              <a href="tienda.php" class="boton" style="display: block; text-align: center; text-decoration: none;">Volver al inicio</a>
            <?php endif; ?>

          </div>
        </div>
      </main>

    <?php else: ?>
      <!-- VISTA PRINCIPAL (Hero, Nosotros y Grilla) -->
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
          <h2 style="text-align: center; font-size: 2.2rem; margin-bottom: 2rem; color: var(--indigo);">Nuestro Catálogo</h2>
          
          <ul class="rejilla">
            <?php 
            $res_catalogo = $conexion->query($query_catalogo);
            while($prod = $res_catalogo->fetch_assoc()): ?>
              <li>
                <a class="tarjeta" href="?producto=<?php echo $prod['id_producto']; ?>">
                  <div class="foto">📦<span class="chip"><?php echo $prod['categoria']; ?></span></div>
                  <div class="cuerpo">
                    <h3><?php echo $prod['nombre']; ?></h3>
                    <p class="autor"><?php echo $prod['artesano']; ?> · <?php echo $prod['region']; ?></p>
                    <span class="precio">S/ <?php echo $prod['precio']; ?></span>
                    <span style="font-size: 0.85rem; font-weight: bold; margin-top: 5px; color: <?php echo ($prod['stock']>0)?'var(--verde)':'var(--cochinilla)'; ?>;">Stock: <?php echo $prod['stock']; ?></span>
                  </div>
                </a>
              </li>
            <?php endwhile; ?>
          </ul>
        </section>
      </main>
    <?php endif; ?>
  <?php endif; ?>

  <!-- PIE DE PÁGINA -->
  <footer style="background: var(--texto); color: white; text-align: center; padding: 2rem; margin-top: 4rem;">
    <p>Proyecto Universitario - Sistemas Operativos UPC</p>
  </footer>
</body>
</html>
