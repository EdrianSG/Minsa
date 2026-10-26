/**
 * Lógica de Cliente: Pantalla de Login Institucional
 * Hospital Distrital de Pacasmayo - SGH RR.HH.
 */

document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('loginForm');
  const identificadorInput = document.getElementById('identificador');
  const passwordInput = document.getElementById('password');
  const submitBtn = document.getElementById('btnSubmitLogin');
  const togglePassBtn = document.getElementById('togglePasswordBtn');
  const alertContainer = document.getElementById('loginAlertContainer');

  // Foco inicial
  if (identificadorInput && !identificadorInput.value) {
    identificadorInput.focus();
  }

  // Toggle visibilidad de contraseña
  if (togglePassBtn && passwordInput) {
    togglePassBtn.addEventListener('click', () => {
      const isPassword = passwordInput.getAttribute('type') === 'password';
      passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
      togglePassBtn.innerHTML = isPassword
        ? `<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" /></svg>`
        : `<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>`;
    });
  }

  // Envío del formulario
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const identificador = identificadorInput.value.trim();
      const password = passwordInput.value;
      const redirect = document.getElementById('redirectUrl')?.value || 'index.php';

      if (!identificador || !password) {
        mostrarAlerta('Por favor ingrese su usuario/DNI y su contraseña.', 'danger');
        return;
      }

      setLoading(true);
      limpiarAlerta();

      try {
        const response = await fetch('api/auth/login.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            identificador,
            password,
            redirect
          })
        });

        const data = await response.json();

        if (response.ok && data.ok) {
          mostrarAlerta(data.mensaje || 'Autenticación exitosa. Ingresando...', 'success');
          setTimeout(() => {
            window.location.href = data.redirect_url || 'index.php';
          }, 450);
        } else {
          mostrarAlerta(data.mensaje || 'Credenciales inválidas. Verifique sus datos.', 'danger');
          setLoading(false);
          passwordInput.value = '';
          passwordInput.focus();
        }
      } catch (err) {
        console.error('Error en autenticación:', err);
        mostrarAlerta('No se pudo conectar con el servidor de autenticación. Intente nuevamente.', 'danger');
        setLoading(false);
      }
    });
  }

  // Función para rellenar credenciales de prueba con 1 clic
  window.fillQuickAccount = function(username, pass) {
    if (identificadorInput && passwordInput) {
      identificadorInput.value = username;
      passwordInput.value = pass;
      limpiarAlerta();
      passwordInput.focus();
    }
  };

  function setLoading(loading) {
    if (!submitBtn) return;
    if (loading) {
      submitBtn.classList.add('loading');
      submitBtn.setAttribute('disabled', 'disabled');
    } else {
      submitBtn.classList.remove('loading');
      submitBtn.removeAttribute('disabled');
    }
  }

  function mostrarAlerta(mensaje, tipo = 'danger') {
    if (!alertContainer) return;
    
    let icono = '';
    if (tipo === 'danger') {
      icono = `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`;
    } else if (tipo === 'success') {
      icono = `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`;
    } else {
      icono = `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;
    }

    alertContainer.innerHTML = `
      <div class="login-alert ${tipo}">
        ${icono}
        <div>${mensaje}</div>
      </div>
    `;
  }

  function limpiarAlerta() {
    if (alertContainer) {
      alertContainer.innerHTML = '';
    }
  }
});
