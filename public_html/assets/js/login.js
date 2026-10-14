document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.querySelector('.signin-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const submitBtn = loginForm.querySelector('button[type="submit"]');

            const email = emailInput.value.trim();
            const password = passwordInput.value.trim();

            if (!email || !password) {
                alert('Please enter both email and password.');
                return;
            }

            // Optional: visual feedback
            const originalText = submitBtn.innerText;
            submitBtn.innerText = 'Signing in...';
            submitBtn.disabled = true;

            try {
                const response = await fetch('../api/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ email, password })
                });

                const data = await response.json();

                if (data.success) {
                    // Redirect based on role
                    window.location.href = data.redirect;
                } else {
                    alert(data.message || 'Login failed. Please check your credentials.');
                    submitBtn.innerText = originalText;
                    submitBtn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred while connecting to the server.');
                submitBtn.innerText = originalText;
                submitBtn.disabled = false;
            }
        });
    }
});
