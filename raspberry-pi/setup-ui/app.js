(function () {
    'use strict';

    const form = document.getElementById('setup-form');
    const submitButton = document.getElementById('submit-button');
    const statusBanner = document.getElementById('status-banner');
    const workingState = document.getElementById('working-state');
    const workingMessage = document.getElementById('working-message');
    const errorEls = Array.from(document.querySelectorAll('[data-error-for]'));

    function showBanner(message, isError) {
        statusBanner.textContent = message;
        statusBanner.hidden = false;
        statusBanner.classList.toggle('is-error', Boolean(isError));
    }

    function clearErrors() {
        errorEls.forEach((el) => {
            el.textContent = '';
        });
    }

    function setErrors(errors) {
        clearErrors();
        Object.entries(errors || {}).forEach(([key, message]) => {
            const el = document.querySelector(`[data-error-for="${key}"]`);
            if (el) {
                el.textContent = message;
            }
        });
    }

    function setWorking(active, message) {
        workingState.hidden = !active;
        submitButton.disabled = active;
        if (message) {
            workingMessage.textContent = message;
        }
    }

    function populateForm(config) {
        if (!config) {
            return;
        }

        if (config.wifi_ssid) {
            form.wifi_ssid.value = config.wifi_ssid;
        }
        if (config.screen_code) {
            form.screen_code.value = config.screen_code;
        }
    }

    async function loadStatus() {
        try {
            const response = await fetch('/api/status', { cache: 'no-store' });
            const payload = await response.json();
            if (!payload.success) {
                return;
            }

            populateForm(payload.data.config);

            const state = payload.data.state || {};
            if (state.last_message) {
                showBanner(state.last_message, Boolean(state.last_error));
            }
        } catch (error) {
            showBanner('Status could not be loaded. You can still enter the Wi-Fi details and screen code manually.', true);
        }
    }

    async function submitForm(event) {
        event.preventDefault();
        clearErrors();

        const payload = {
            wifi_ssid: form.wifi_ssid.value.trim(),
            wifi_password: form.wifi_password.value,
            screen_code: form.screen_code.value.trim().toUpperCase()
        };

        setWorking(true, 'Saving settings and testing the venue Wi-Fi...');

        try {
            const response = await fetch('/api/provision', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                setErrors(data.errors || {});
                showBanner(data.message || 'Provisioning failed.', true);
                setWorking(false);
                return;
            }

            showBanner(data.message, false);
            setWorking(true, 'The Pi is leaving setup mode and joining the venue Wi-Fi. This hotspot will disconnect shortly.');
        } catch (error) {
            showBanner('The request failed before the Pi could save the setup details. Check the hotspot connection and try again.', true);
            setWorking(false);
        }
    }

    form.addEventListener('submit', submitForm);
    loadStatus();
})();
