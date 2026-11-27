<?php
require_once 'includes/helpers.php';
require_once 'modelo/productos.php';
require_once 'modelo/carrito.php';

$id = (int)($_GET['id'] ?? 0);
$p = obtenerProductoPorId($id);
$idu = compradorActual();
$idv = vendedorActual();
if ($p && !$p['visible'] && (int)$p['id_vendedor'] !== (int)$idv) $p = null;   // oculto: solo lo ve su artesano
$favs = obtenerIdsFavoritos($idu);

if ($p) {
    $titulo_pagina = $p['nombre'] . ' | Chaska Ruway';
    $en_carrito = 0;
    if ($idu) {
        foreach (obtenerItemsCarrito($idu) as $it) if ((int)$it['id_producto'] === $id) $en_carrito = (int)$it['cantidad'];
    }
    $relacionados = obtenerRelacionados($id);
} else {
    $titulo_pagina = 'Producto no encontrado | Chaska Ruway';
}
$pagina = 'catalogo';
include 'includes/cabecera.php';
?>
  <?php if (!$p): ?>
    <div class="vacio" style="margin-top:2rem">
      <h3>Este producto no existe</h3>
      <p>Puede que lo hayan retirado del catálogo.</p>
      <a class="boton" href="index.php#catalogo">Volver al catálogo</a>
    </div>
  <?php else: ?>
    <p class="migas"><a href="index.php#catalogo">Catálogo</a> / <?= e($p['nombre']) ?></p>
    <div class="detalle">
      <?= portada($p, 'portada--grande') ?>
      <div class="detalle__info">
        <?php if (!$p['visible']): ?><p class="aviso aviso--error" style="margin:0 0 1rem">Este producto está oculto: los compradores no lo ven. Puedes volver a mostrarlo desde "Editar este producto".</p><?php endif; ?>
        <h1><?= e($p['nombre']) ?></h1>
        <p class="taller-linea">Hecho en <strong><?= e($p['nombre_taller']) ?></strong>, <?= e($p['region']) ?></p>
        <a href="#resenas" style="text-decoration:none;font-weight:600;color:#8c2d38;">★ Ver reseñas y comunidad</a>
        <span class="precio"><?= soles($p['precio']) ?></span>
        <p class="detalle__desc"><?= e($p['descripcion']) ?></p>
        <p class="disponibles"><?= (int)$p['stock'] > 0
            ? '<strong>' . (int)$p['stock'] . '</strong> ' . ((int)$p['stock'] === 1 ? 'unidad disponible' : 'unidades disponibles')
            : '<span class="stock stock--agotado">Agotado por ahora</span>' ?></p>
        <?php if ($en_carrito): ?><span class="nota">Tienes <?= $en_carrito ?> en tu carrito.</span><?php endif; ?>

        <?php if ($idv): /* cuenta de vendedor: no compra */ ?>
          <?php if ((int)$p['id_vendedor'] === (int)$idv): ?>
            <div class="comprar"><a class="boton" style="grid-column:1/-1" href="vendedor_producto.php?id=<?= (int)$p['id_producto'] ?>">Editar este producto</a></div>
          <?php else: ?>
            <p class="ayuda">Estás viendo la tienda con tu cuenta de vendedor.</p>
          <?php endif; ?>
        <?php elseif ($p['stock'] > 0): ?>
          <form method="post" action="accion.php" class="comprar">
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

        <?php if (!$idv): ?>
        <form method="post" action="accion.php" class="detalle__extra">
          <input type="hidden" name="accion" value="favorito">
          <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>">
          <input type="hidden" name="volver" value="<?= e(paginaActual()) ?>">
          <button class="enlace-boton" type="submit"><?= in_array((int)$p['id_producto'], $favs, true) ? '♥ Quitar de favoritos' : '♡ Guardar en favoritos' ?></button>
        </form>
        <?php endif; ?>

        <div class="garantias">
          <span><strong>Envío gratis</strong> a todo el Perú.</span>
          <span><strong>Pago</strong> con tarjeta, Yape, Plin, transferencia o efectivo al recibir.</span>
          <?php if ($p['taller_descripcion']): ?><span><strong><?= e($p['nombre_taller']) ?>:</strong> <?= e($p['taller_descripcion']) ?></span><?php endif; ?>
        </div>
      </div>
    </div>

    <section id="resenas" class="resenas">
      <div>
        <h2>Reseñas</h2>
        <p id="estado-api-mongo" style="font-size:0.82rem;font-weight:600;margin-bottom:0.5rem;">Conectando con MongoDB...</p>
        <div id="resumen-resenas-mongo">
          <p class="ayuda">Cargando calificaciones...</p>
        </div>

        <div style="margin-top:1.5rem">
          <form id="form-resena-mongo" class="caja">
            <h3 style="margin-top:0;font-size:1.05rem;">Deja tu reseña</h3>
            <fieldset class="elegir-estrellas">
              <legend>Tu calificación</legend>
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="est<?= $i ?>" name="puntuacion" value="<?= $i ?>" <?= $i === 5 ? 'checked required' : '' ?>>
                <label for="est<?= $i ?>" title="<?= $i ?> de 5">★<span class="sr"><?= $i ?> de 5</span></label>
              <?php endfor; ?>
            </fieldset>
            <div class="campo">
              <label for="mongo-username">Tu usuario</label>
              <input type="text" id="mongo-username" required placeholder="Ej. diego_andes" value="<?= $idv ? 'Taller_Artesano' : '' ?>">
            </div>
            <div class="campo">
              <label for="mongo-etiqueta">Tipo de perfil</label>
              <select id="mongo-etiqueta">
                <option value="Compra Verificada">Compra Verificada</option>
                <option value="Visitante">Visitante</option>
                <option value="Artesano Verificado">Artesano Verificado</option>
              </select>
            </div>
            <div class="campo">
              <label for="titulo">Título</label>
              <input type="text" id="titulo" maxlength="150" required placeholder="Resume tu experiencia">
            </div>
            <div class="campo">
              <label for="comentario">Comentario</label>
              <textarea id="comentario" required placeholder="¿Cómo llegó? ¿Qué te pareció la pieza?"></textarea>
            </div>
            <button id="btn-publicar-mongo" class="boton" type="submit">Publicar reseña</button>
          </form>
        </div>
      </div>

      <div id="lista-resenas-mongo">
        <p class="ayuda">Cargando reseñas desde MongoDB...</p>
      </div>
    </section>

    <script>
      const PRODUCTO_ID = <?= (int)$p['id_producto'] ?>;
      const COMPRADOR_ID = <?= (int)($idu ?? 0) ?>;
    </script>
   <script src="js/resenas-mongo.js?v=<?= time() ?>"></script>

    <?php if ($relacionados): ?>
      <section class="seccion">
        <h2>También te puede gustar</h2>
        <div class="rejilla">
          <?php foreach ($relacionados as $rp) echo tarjeta($rp, $favs, paginaActual()); ?>
        </div>
      </section>
    <?php endif; ?>
  <?php endif; ?>
<?php include 'includes/pie.php'; ?>