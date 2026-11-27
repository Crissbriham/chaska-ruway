const API_RESENAS = 'http://158.23.24.60:3000/api/resenas';
let cacheResenas = [];

function escaparHTML(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}

function dibujarEstrellas(puntuacion) {
  const n = Math.max(1, Math.min(5, Math.round(Number(puntuacion) || 5)));
  return `<span style="color:#d99b26;letter-spacing:2px;font-size:1.1rem;" aria-label="${n} de 5 estrellas">${'★'.repeat(n)}${'☆'.repeat(5 - n)}</span>`;
}

async function cargarResenasMongo() {
  const contenedorLista = document.getElementById('lista-resenas-mongo');
  const resumenBox = document.getElementById('resumen-resenas-mongo');
  const estadoApi = document.getElementById('estado-api-mongo');

  try {
    const respuesta = await fetch(API_RESENAS);
    if (!respuesta.ok) throw new Error('Error HTTP ' + respuesta.status);
    const data = await respuesta.json();

    const todas = Array.isArray(data.datos) ? data.datos : [];
    cacheResenas = todas
      .filter(r => Number(r.id_producto) === Number(PRODUCTO_ID))
      .reverse();

    if (estadoApi) {
      estadoApi.textContent = '● Conectado a MongoDB (API REST :3000)';
      estadoApi.style.color = '#1b7a3d';
    }

    if (cacheResenas.length > 0) {
      const suma = cacheResenas.reduce((acc, r) => acc + (Number(r.puntuacion) || 0), 0);
      const promedio = (suma / cacheResenas.length).toFixed(1);
      resumenBox.innerHTML = `
        <div class="resumen-nota">${promedio}</div>
        ${dibujarEstrellas(promedio)}
        <span class="nota">${cacheResenas.length} ${cacheResenas.length === 1 ? 'reseña' : 'reseñas'} en MongoDB</span>
      `;
    } else {
      resumenBox.innerHTML = `<p class="ayuda">Todavía nadie ha reseñado este producto. ¡Sé el primero!</p>`;
    }

    if (cacheResenas.length === 0) {
      contenedorLista.innerHTML = `<div class="caja"><p class="ayuda">No hay reseñas registradas para este producto en MongoDB aún.</p></div>`;
      return;
    }

    contenedorLista.innerHTML = cacheResenas.map(r => renderizarTarjetaResena(r)).join('');
  } catch (err) {
    if (estadoApi) {
      estadoApi.textContent = '● Sin conexión con API REST (:3000)';
      estadoApi.style.color = '#b02a2a';
    }
    contenedorLista.innerHTML = `<p class="aviso aviso--error">No se pudieron cargar las reseñas desde MongoDB: ${escaparHTML(err.message)}</p>`;
  }
}

function renderizarTarjetaResena(r) {
  const autor = r.perfil_comprador?.username || 'Usuario_Chaska';
  const etiqueta = r.perfil_comprador?.etiqueta || 'Compra Verificada';
  const likes = Number(r.likes) || 0;
  const fecha = r.fecha_publicacion ? new Date(r.fecha_publicacion).toLocaleDateString('es-PE') : 'Reciente';
  const hilos = Array.isArray(r.hilos_respuesta) ? r.hilos_respuesta : [];

  const htmlHilos = hilos.map(h => `
    <div style="margin-top:0.75rem;padding:0.75rem 1rem;background:#f8f5f0;border-left:3px solid #8c2d38;border-radius:6px;">
      <div style="font-size:0.85rem;font-weight:700;color:#2b1d16;">
        ↳ ${escaparHTML(h.username || 'Taller_Artesano')}
        <span style="font-weight:400;font-size:0.78rem;background:#e8dfd5;padding:2px 7px;border-radius:999px;margin-left:4px;">${escaparHTML(h.rol_usuario || 'Vendedor')}</span>
      </div>
      <p style="margin:0.35rem 0 0;font-size:0.92rem;">${escaparHTML(h.comentario)}</p>
    </div>
  `).join('');

  return `
    <article class="resena" style="margin-bottom:1.25rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;flex-wrap:wrap;">
        <div>${dibujarEstrellas(r.puntuacion)}</div>
        <span style="font-size:0.78rem;background:#eef6f0;color:#1b7a3d;padding:2px 8px;border-radius:999px;font-weight:600;">
          ✓ ${escaparHTML(etiqueta)}
        </span>
      </div>
      ${r.titulo ? `<h3 style="margin-top:0.4rem;">${escaparHTML(r.titulo)}</h3>` : ''}
      ${r.comentario ? `<p>${escaparHTML(r.comentario)}</p>` : ''}
      <span class="resena__autor">Por <strong>${escaparHTML(autor)}</strong> · ${fecha}</span>

      <div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-top:0.85rem;align-items:center;">
        <button type="button" class="boton boton--borde" style="padding:0.35rem 0.75rem;font-size:0.85rem;" onclick="darLikeResena('${r._id}')">
          👍 Útil (${likes})
        </button>
        <button type="button" class="boton boton--borde" style="padding:0.35rem 0.75rem;font-size:0.85rem;" onclick="toggleFormRespuesta('${r._id}')">
          💬 Responder
        </button>
        <button type="button" class="enlace-boton" style="color:#a12626;font-size:0.85rem;margin-left:auto;" onclick="eliminarResena('${r._id}')">
          Eliminar
        </button>
      </div>

      <div id="form-resp-${r._id}" style="display:none;margin-top:0.75rem;padding:0.75rem;background:#faf8f5;border-radius:8px;border:1px solid #e5ddd3;">
        <div style="display:flex;gap:0.5rem;margin-bottom:0.5rem;flex-wrap:wrap;">
          <input type="text" id="resp-user-${r._id}" placeholder="Tu nombre o taller" value="Artesanias_Chaska" style="flex:1;min-width:140px;padding:0.4rem 0.6rem;border:1px solid #ccc;border-radius:6px;">
          <select id="resp-rol-${r._id}" style="padding:0.4rem 0.6rem;border:1px solid #ccc;border-radius:6px;">
            <option value="Vendedor">Vendedor</option>
            <option value="Comprador">Comprador</option>
          </select>
        </div>
        <div style="display:flex;gap:0.5rem;">
          <input type="text" id="resp-texto-${r._id}" placeholder="Escribe una respuesta al comentario..." style="flex:1;padding:0.45rem 0.65rem;border:1px solid #ccc;border-radius:6px;">
          <button type="button" class="boton" style="padding:0.45rem 0.9rem;font-size:0.85rem;" onclick="enviarRespuesta('${r._id}')">Enviar</button>
        </div>
      </div>

      ${htmlHilos}
    </article>
  `;
}

async function crearResenaMongo(evento) {
  evento.preventDefault();
  const btn = document.getElementById('btn-publicar-mongo');
  btn.disabled = true;
  btn.textContent = 'Guardando en MongoDB...';

  const puntuacion = Number(document.querySelector('input[name="puntuacion"]:checked')?.value || 5);
  const username = document.getElementById('mongo-username').value.trim() || 'Cliente_Chaska';
  const etiqueta = document.getElementById('mongo-etiqueta').value;
  const titulo = document.getElementById('titulo').value.trim();
  const comentario = document.getElementById('comentario').value.trim();

  const payload = {
    id_producto: Number(PRODUCTO_ID),
    id_comprador: Number(COMPRADOR_ID) || 101,
    perfil_comprador: { username, etiqueta },
    titulo,
    comentario,
    puntuacion,
    likes: 0,
    hilos_respuesta: []
  };

  try {
    const res = await fetch(API_RESENAS, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    if (!res.ok) throw new Error('Error al guardar reseña');
    document.getElementById('form-resena-mongo').reset();
    await cargarResenasMongo();
  } catch (err) {
    alert('Error al publicar en MongoDB: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Publicar reseña';
  }
}

async function darLikeResena(id) {
  const actual = cacheResenas.find(r => r._id === id);
  if (!actual) return;
  const nuevosLikes = (Number(actual.likes) || 0) + 1;
  await fetch(`${API_RESENAS}/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ likes: nuevosLikes })
  });
  await cargarResenasMongo();
}

function toggleFormRespuesta(id) {
  const box = document.getElementById(`form-resp-${id}`);
  if (box) box.style.display = box.style.display === 'none' ? 'block' : 'none';
}

async function enviarRespuesta(id) {
  const actual = cacheResenas.find(r => r._id === id);
  if (!actual) return;
  const texto = document.getElementById(`resp-texto-${id}`).value.trim();
  if (!texto) return;

  const username = document.getElementById(`resp-user-${id}`).value.trim() || 'Artesanias_Chaska';
  const rol_usuario = document.getElementById(`resp-rol-${id}`).value;
  const hilosActuales = Array.isArray(actual.hilos_respuesta) ? actual.hilos_respuesta : [];

  const nuevoHilo = [
    ...hilosActuales,
    { id_respuesta: 'resp_' + Date.now(), username, rol_usuario, comentario: texto, likes: 0 }
  ];

  await fetch(`${API_RESENAS}/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ hilos_respuesta: nuevoHilo })
  });
  await cargarResenasMongo();
}

async function eliminarResena(id) {
  if (!confirm('¿Eliminar esta reseña de MongoDB?')) return;
  await fetch(`${API_RESENAS}/${id}`, { method: 'DELETE' });
  await cargarResenasMongo();
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('form-resena-mongo');
  if (form) form.addEventListener('submit', crearResenaMongo);
  cargarResenasMongo();
});