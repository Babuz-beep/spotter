/**
 * SPOTTER Session Check
 * Include this script on all protected pages (index.html, c1f.html, c2h.html, p2h.html)
 * Add before closing </body>: <script src="session_check.js"></script>
 */
(function() {
  const API = '/auth.php';
  const token = localStorage.getItem('spotter_token');

  if (!token) {
    window.location.href = '/login.html';
    return;
  }

  // Verify session is still valid
  fetch(API, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'verify_session', token})
  })
  .then(r => r.json())
  .then(data => {
    if (!data.valid) {
      localStorage.removeItem('spotter_token');
      localStorage.removeItem('spotter_email');
      localStorage.removeItem('spotter_domain');
      window.location.href = '/login.html';
    }
    // Session valid — show user email in header if element exists
    const emailEl = document.getElementById('user-email');
    if (emailEl) emailEl.textContent = data.email;
  })
  .catch(() => {
    // Network error — don't block access, just continue
  });
})();
