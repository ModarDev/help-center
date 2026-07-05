(() => {
    const form = document.getElementById('loginForm');
    const message = document.getElementById('message');
    const submitBtn = document.getElementById('submitBtn');
    const baseUrl = document.querySelector('meta[name="base-url"]')?.content || '';

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        message.textContent = '';
        message.className = 'message';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Logging in...';

        const formData = new FormData(form);

        try {
            const response = await fetch(`${baseUrl}/auth/login`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                credentials: 'same-origin',
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Login failed.');
            }

            message.textContent = data.message;
            message.classList.add('success');
            window.location.href = data.redirect;
        } catch (error) {
            message.textContent = error.message;
            message.classList.add('error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Login';
        }
    });
})();
