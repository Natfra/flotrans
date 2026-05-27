// =====================================================
// session_guard.js
// Colocar en: C:\xampp\htdocs\flotrans\session_guard.js
// Incluir en TODAS las páginas protegidas con:
//   <script src="../session_guard.js"></script>
//   (ajusta la ruta relativa según la carpeta del HTML)
// =====================================================

(function () {
  const raw = sessionStorage.getItem('flotrans_user');

  if (!raw) {
    // No hay sesión → redirigir al login
    window.location.replace('login.html');
    return;
  }

  try {
    const user = JSON.parse(raw);

    // Verificar campos mínimos
    if (!user || !user.id || !user.rol) {
      sessionStorage.removeItem('flotrans_user');
      window.location.replace('login.html');
      return;
    }

    // Restricción por rol (opcional — ampliar según páginas)
    // Si estás en conductor_panel.html y el rol no es conductor → bloquear
    const pagina = window.location.pathname.split('/').pop();
    if (pagina === 'conductor_panel.html' && user.rol !== 'conductor') {
      window.location.replace('dashboard.html');
      return;
    }

    // Inyectar nombre/avatar en el sidebar si existen esos elementos
    document.addEventListener('DOMContentLoaded', () => {
      const nameEl   = document.getElementById('sidebarName');
      const avatarEl = document.getElementById('sidebarAvatar');
      const topAvatar = document.querySelector('.topbar-avatar');

      if (nameEl) nameEl.textContent = user.nombre;

      const iniciales = user.nombre
        .split(' ')
        .slice(0, 2)
        .map(p => p[0].toUpperCase())
        .join('');

      if (avatarEl)   avatarEl.textContent = iniciales;
      if (topAvatar)  topAvatar.textContent = iniciales;
    });

  } catch (e) {
    sessionStorage.removeItem('flotrans_user');
    window.location.replace('login.html');
  }
})();

// =====================================================
// Función global para cerrar sesión
// Llamar desde el botón "Cerrar sesión":
//   onclick="cerrarSesion()"
// =====================================================
function cerrarSesion() {
  sessionStorage.removeItem('flotrans_user');
  window.location.replace('login.html');
}