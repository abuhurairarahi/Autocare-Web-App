document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('loginForm');
  const errorDiv = document.getElementById('login-error');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const roleCards = document.querySelectorAll('.role-card');

  // Pre-fill email and password when a role card is clicked
  roleCards.forEach(card => {
    card.addEventListener('click', () => {
      const email = card.getAttribute('data-email');
      if (email) {
        emailInput.value = email;
        passwordInput.value = 'password123';
        
        // Visual feedback
        roleCards.forEach(c => c.style.border = '1px solid #e2e8f0');
        card.style.border = '2px solid #2563eb';
      }
    });
  });

  // Handle form submission
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    errorDiv.style.display = 'none';
    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();

    if (!email || !password) {
      errorDiv.innerText = 'Please enter both email and password.';
      errorDiv.style.display = 'block';
      return;
    }

    const submitBtn = document.getElementById('loginBtn');
    submitBtn.innerText = 'Signing in...';
    submitBtn.disabled = true;

      let rawText = '';
      try {
        const response = await fetch('../api/login-api.php?action=login', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ email, password })
        });
        
        rawText = await response.text();
        const data = JSON.parse(rawText);

        if (data.success) {
          // Redirect to the assigned dashboard
          window.location.href = data.redirect_url;
        } else {
          let debugMsg = data.debug ? ' Debug: ' + JSON.stringify(data.debug) : '';
          errorDiv.innerText = (data.error || 'Login failed. Please check your credentials.') + debugMsg;
          errorDiv.style.display = 'block';
          submitBtn.innerText = 'Continue';
          submitBtn.disabled = false;
        }
      } catch (err) {
        console.error('Fetch Error:', err);
        console.error('Raw Server Response:', rawText);
        
        if (rawText.includes('Unknown database')) {
          errorDiv.innerHTML = `<strong>Setup Required:</strong> You must create a database named <code>autocare</code> in XAMPP phpMyAdmin before logging in!`;
        } else if (rawText.includes('<?php')) {
          errorDiv.innerHTML = `<strong>Server Error:</strong> You are running this via VS Code Live Server. You must open it through XAMPP (http://localhost/...).`;
        } else {
          errorDiv.innerText = 'Network error: ' + (rawText ? rawText.substring(0, 50) : err.message);
        }
        
        errorDiv.style.display = 'block';
        submitBtn.innerText = 'Continue';
        submitBtn.disabled = false;
      }
    });
});
