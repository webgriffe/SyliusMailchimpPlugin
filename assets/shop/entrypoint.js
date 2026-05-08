document.addEventListener('DOMContentLoaded', () => {
    /**
     * Standalone newsletter subscribe form (.js-mailchimp-newsletter-form).
     * Intercepts submit, sends as XHR, shows success/error message inline.
     */
    document.querySelectorAll('.js-mailchimp-newsletter-form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const messageEl = form.querySelector('.js-mailchimp-newsletter-message');
            const submitBtn = form.querySelector('[type=submit]');
            if (submitBtn) submitBtn.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                });

                const data = await response.json();

                if (messageEl) {
                    messageEl.style.display = '';
                    if (data.success) {
                        messageEl.textContent = form.dataset.successMessage || '';
                        messageEl.className = 'js-mailchimp-newsletter-message positive message';
                        form.reset();
                    } else {
                        messageEl.textContent = (data.errors || []).join(', ');
                        messageEl.className = 'js-mailchimp-newsletter-message negative message';
                    }
                }
            } catch (e) {
                if (messageEl) {
                    messageEl.style.display = '';
                    messageEl.className = 'js-mailchimp-newsletter-message negative message';
                    messageEl.textContent = 'An error occurred. Please try again.';
                }
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    });

    /**
     * Newsletter opt-in checkbox (.js-mailchimp-newsletter-checkbox).
     * Embedded in register/checkout/profile forms. On parent form submit,
     * if checked, posts the email to the subscribe endpoint.
     */
    document.querySelectorAll('.js-mailchimp-newsletter-checkbox').forEach((checkbox) => {
        const parentForm = checkbox.closest('form');
        if (!parentForm) return;

        parentForm.addEventListener('submit', () => {
            if (!checkbox.checked) return;

            const subscribeUrl = checkbox.dataset.subscribeUrl;
            if (!subscribeUrl) return;

            // Find the email field in the parent form (Sylius uses sylius_customer_registration[email])
            const emailInput = parentForm.querySelector('input[type=email]');
            if (!emailInput || !emailInput.value) return;

            const body = new URLSearchParams();
            body.set('newsletter_subscribe[email]', emailInput.value);

            // Fire-and-forget: don't block the main form submit
            fetch(subscribeUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: body.toString(),
                keepalive: true,
            }).catch(() => {
                // Silently ignore errors — newsletter subscribe is non-critical
            });
        });
    });
});
