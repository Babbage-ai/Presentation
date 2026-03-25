(function () {
    'use strict';

    const ssidEl = document.getElementById('setup-ssid');
    const statusEl = document.getElementById('screen-status');

    function showStatus(message, isError) {
        if (!message) {
            statusEl.hidden = true;
            return;
        }

        statusEl.textContent = message;
        statusEl.hidden = false;
        statusEl.classList.toggle('is-error', Boolean(isError));
    }

    async function loadStatus() {
        try {
            const response = await fetch('/api/status', { cache: 'no-store' });
            const payload = await response.json();
            if (!payload.success) {
                return;
            }

            const state = payload.data.state || {};
            if (state.setup_ssid) {
                ssidEl.textContent = state.setup_ssid;
            }

            showStatus(state.last_message || '', Boolean(state.last_error));
        } catch (error) {
            showStatus('Open http://192.168.4.1 on your phone if setup does not appear automatically.', false);
        }
    }

    loadStatus();
    window.setInterval(loadStatus, 5000);
})();
