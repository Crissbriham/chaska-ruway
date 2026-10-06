</main>

<?php if (!empty($nosotros)): ?>
<section id="nosotros" class="nosotros">
  <div class="envoltura">
    <div>
      <h2 class="titulo-tejido">¿Quiénes somos?</h2>
      <p>Somos una plataforma digital que conecta a maestros artesanos de todas las regiones del Perú directamente con quienes valoran su trabajo. Cada pieza llega con el nombre del taller o la comunidad que la hizo.</p>
    </div>
    <dl>
      <div><dt>Directo del taller</dt><dd>Trabajamos con asociaciones y familias artesanas, sin intermediarios.</dd></div>
      <div><dt>Envío gratis a todo el Perú</dt><dd>Despachamos con Olva Courier y Shalom. Puedes seguir tu pedido desde tu cuenta.</dd></div>
      <div><dt>Piezas hechas a mano</dt><dd>Cada producto puede variar un poco en color y forma: así se reconoce el trabajo manual.</dd></div>
    </dl>
  </div>
</section>
<?php endif; ?>

<footer class="pie">
  <div class="faja faja--fina" aria-hidden="true"></div>
  <div class="envoltura pie__columnas">
    <div class="pie__marca">
      <strong>Chaska Ruway</strong>
      <p>Artesanía peruana, directo de quien la hace. Cada pieza llega con el nombre del taller que la tejió, talló o moldeó.</p>
    </div>
    <div>
      <h3>Explora</h3>
      <ul>
        <li><a href="index.php#catalogo">Catálogo</a></li>
        <li><a href="index.php#nosotros">Quiénes somos</a></li>
        <?php if (!compradorActual() && !vendedorActual()): ?>
          <li><a href="login.php">Iniciar sesión</a></li>
        <?php endif; ?>
      </ul>
    </div>
    <div>
      <h3>Para artesanos</h3>
      <ul>
        <?php if (vendedorActual()): ?>
          <li><a href="vendedor_productos.php">Mis productos</a></li>
          <li><a href="vendedor_panel.php">Pedidos por despachar</a></li>
        <?php else: ?>
          <li><a href="registro.php?rol=vendedor">Vende en Chaska Ruway</a></li>
          <li>Envío gratis a todo el Perú</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="envoltura pie__base">
    <p>&copy; 2026 Chaska Ruway. Todos los derechos reservados.</p>
    <p> Siguenos Facebook, Instagram, Telegram y en Whatsapp como chaskaRuway2026. <p>
  </div>
</footer>

</body>
</html>
