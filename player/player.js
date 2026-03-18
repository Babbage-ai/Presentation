(function () {
    'use strict';

    const PLAYER_VERSION = '1.0.0';
    const CONFIG_PATH = './config.json';
    const DB_NAME = 'cloud_signage_player';
    const STORE_NAME = 'media_cache';

    const stage = document.getElementById('stage');
    const statusEl = document.getElementById('status');
    const overlayEl = document.getElementById('overlay');

    const state = {
        config: null,
        playlist: [],
        currentIndex: 0,
        currentTimer: null,
        refreshTimer: null,
        heartbeatTimer: null,
        playbackStarted: false,
        idb: null,
        currentObjectUrl: null
    };

    function setStatus(message, keepVisible = true) {
        statusEl.textContent = message;
        overlayEl.classList.toggle('hidden', !keepVisible);
    }

    function sleep(ms) {
        return new Promise((resolve) => window.setTimeout(resolve, ms));
    }

    function readUrlConfig() {
        const params = new URLSearchParams(window.location.search);
        const apiBaseUrl = (params.get('api_base_url') || '').trim();
        const screenToken = (params.get('token') || '').trim();
        const cacheNamespace = (params.get('cache_namespace') || '').trim();
        const refreshInterval = Number.parseInt(params.get('refresh_interval_seconds') || '', 10);
        const heartbeatInterval = Number.parseInt(params.get('heartbeat_interval_seconds') || '', 10);

        return {
            api_base_url: apiBaseUrl || null,
            screen_token: screenToken || null,
            cache_namespace: cacheNamespace || null,
            refresh_interval_seconds: Number.isFinite(refreshInterval) && refreshInterval > 0 ? refreshInterval : null,
            heartbeat_interval_seconds: Number.isFinite(heartbeatInterval) && heartbeatInterval > 0 ? heartbeatInterval : null
        };
    }

    async function loadConfig() {
        const urlConfig = readUrlConfig();
        let fileConfig = {};

        try {
            const response = await fetch(CONFIG_PATH + '?ts=' + Date.now(), { cache: 'no-store' });
            if (response.ok) {
                fileConfig = await response.json();
            }
        } catch (error) {
            console.warn('config.json could not be loaded, falling back to URL parameters only.', error);
        }

        const config = {
            refresh_interval_seconds: 300,
            heartbeat_interval_seconds: 60,
            cache_namespace: 'default',
            ...fileConfig
        };

        if (urlConfig.api_base_url) {
            config.api_base_url = urlConfig.api_base_url;
        }
        if (urlConfig.screen_token) {
            config.screen_token = urlConfig.screen_token;
        }
        if (urlConfig.cache_namespace) {
            config.cache_namespace = urlConfig.cache_namespace;
        }
        if (urlConfig.refresh_interval_seconds) {
            config.refresh_interval_seconds = urlConfig.refresh_interval_seconds;
        }
        if (urlConfig.heartbeat_interval_seconds) {
            config.heartbeat_interval_seconds = urlConfig.heartbeat_interval_seconds;
        }

        if (!config.api_base_url || !config.screen_token) {
            throw new Error('Provide token and api_base_url in the URL or in config.json.');
        }

        return config;
    }

    function apiUrl(path) {
        return state.config.api_base_url.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
    }

    async function fetchApiJson(path, options) {
        const response = await fetch(apiUrl(path), options);
        if (!response.ok) {
            throw new Error('API request failed with status ' + response.status);
        }

        const payload = await response.json();
        if (!payload.success) {
            throw new Error(payload.message || 'API error');
        }

        return payload.data;
    }

    function openDatabase() {
        return new Promise((resolve, reject) => {
            const request = window.indexedDB.open(DB_NAME, 1);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    db.createObjectStore(STORE_NAME, { keyPath: 'cache_key' });
                }
            };
        });
    }

    function cacheKeyForItem(item) {
        return state.config.cache_namespace + ':' + item.media_id + ':' + item.filename;
    }

    function saveCachedMedia(item, blob) {
        return new Promise((resolve, reject) => {
            const transaction = state.idb.transaction(STORE_NAME, 'readwrite');
            const store = transaction.objectStore(STORE_NAME);

            store.put({
                cache_key: cacheKeyForItem(item),
                media_id: item.media_id,
                filename: item.filename,
                mime_type: blob.type,
                blob: blob,
                updated_at: Date.now()
            });

            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject(transaction.error);
        });
    }

    function getCachedMedia(item) {
        return new Promise((resolve, reject) => {
            const transaction = state.idb.transaction(STORE_NAME, 'readonly');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.get(cacheKeyForItem(item));

            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });
    }

    async function fetchAndCacheItem(item) {
        try {
            const response = await fetch(item.download_url, { cache: 'no-store' });
            if (!response.ok) {
                throw new Error('Download failed with status ' + response.status);
            }

            const blob = await response.blob();
            await saveCachedMedia(item, blob);
            return true;
        } catch (error) {
            console.error('Cache refresh failed for', item.filename, error);
            return false;
        }
    }

    async function syncPlaylist() {
        const query = '?token=' + encodeURIComponent(state.config.screen_token);
        const data = await fetchApiJson('/api/get_playlist.php' + query);
        state.playlist = Array.isArray(data.items) ? data.items : [];
        state.currentIndex = Math.min(state.currentIndex, Math.max(0, state.playlist.length - 1));

        if (state.playlist.length === 0) {
            setStatus('No playlist assigned or playlist is empty.');
        } else {
            setStatus('Playlist synced: ' + state.playlist.length + ' item(s).');
        }

        window.setTimeout(() => overlayEl.classList.add('hidden'), 4000);
        void prefetchMissingMedia();
    }

    async function prefetchMissingMedia() {
        for (const item of state.playlist) {
            try {
                const cached = await getCachedMedia(item);
                if (!cached) {
                    await fetchAndCacheItem(item);
                }
            } catch (error) {
                console.error('Prefetch skipped for', item.filename, error);
            }
        }
    }

    function clearStage() {
        if (state.currentTimer) {
            window.clearTimeout(state.currentTimer);
            state.currentTimer = null;
        }

        if (state.currentObjectUrl) {
            URL.revokeObjectURL(state.currentObjectUrl);
            state.currentObjectUrl = null;
        }

        stage.innerHTML = '';
    }

    async function resolvePlayableSource(item) {
        const cached = await getCachedMedia(item);
        if (cached && cached.blob) {
            state.currentObjectUrl = URL.createObjectURL(cached.blob);
            return state.currentObjectUrl;
        }

        return item.download_url || item.full_url;
    }

    function nextIndex() {
        if (state.playlist.length === 0) {
            return 0;
        }

        return (state.currentIndex + 1) % state.playlist.length;
    }

    function scheduleNext(delayMs) {
        state.currentTimer = window.setTimeout(() => {
            state.currentIndex = nextIndex();
            void playCurrentItem();
        }, delayMs);
    }

    async function playImage(item, sourceUrl) {
        return new Promise((resolve, reject) => {
            const img = document.createElement('img');
            img.alt = item.title || '';
            img.onload = () => {
                stage.appendChild(img);
                scheduleNext((item.duration || 10) * 1000);
                resolve();
            };
            img.onerror = () => reject(new Error('Image failed to load.'));
            img.src = sourceUrl;
        });
    }

    async function playVideo(item, sourceUrl) {
        return new Promise((resolve, reject) => {
            const video = document.createElement('video');
            video.autoplay = true;
            video.muted = true;
            video.playsInline = true;
            video.setAttribute('webkit-playsinline', 'true');
            video.preload = 'auto';
            video.src = sourceUrl;

            const cleanup = () => {
                video.onloadeddata = null;
                video.onended = null;
                video.onerror = null;
            };

            video.onloadeddata = async () => {
                try {
                    stage.appendChild(video);
                    await video.play();
                    resolve();
                } catch (error) {
                    cleanup();
                    reject(error);
                }
            };

            video.onended = () => {
                cleanup();
                state.currentIndex = nextIndex();
                void playCurrentItem();
            };

            video.onerror = () => {
                cleanup();
                reject(new Error('Video failed to play.'));
            };
        });
    }

    async function playCurrentItem() {
        clearStage();

        if (state.playlist.length === 0) {
            setStatus('Waiting for playlist...');
            state.currentTimer = window.setTimeout(() => {
                void syncPlaylist().then(playCurrentItem).catch(handleRuntimeError);
            }, 15000);
            return;
        }

        const item = state.playlist[state.currentIndex];
        if (!item) {
            state.currentIndex = 0;
            state.currentTimer = window.setTimeout(() => void playCurrentItem(), 1000);
            return;
        }

        try {
            const sourceUrl = await resolvePlayableSource(item);

            if (item.type === 'video') {
                await playVideo(item, sourceUrl);
            } else {
                await playImage(item, sourceUrl);
            }
        } catch (error) {
            console.error('Playback failed for', item, error);
            state.currentIndex = nextIndex();
            state.currentTimer = window.setTimeout(() => void playCurrentItem(), 500);
        }
    }

    async function sendHeartbeat() {
        const payload = {
            token: state.config.screen_token,
            ip: '',
            resolution: window.innerWidth + 'x' + window.innerHeight,
            player_version: PLAYER_VERSION
        };

        try {
            await fetchApiJson('/api/heartbeat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
        } catch (error) {
            console.error('Heartbeat failed', error);
        }
    }

    function startTimers() {
        if (!state.refreshTimer) {
            state.refreshTimer = window.setInterval(() => {
                void syncPlaylist().catch(handleRuntimeError);
            }, state.config.refresh_interval_seconds * 1000);
        }

        if (!state.heartbeatTimer) {
            state.heartbeatTimer = window.setInterval(() => {
                void sendHeartbeat();
            }, state.config.heartbeat_interval_seconds * 1000);
        }
    }

    function handleRuntimeError(error) {
        console.error(error);
        setStatus(error.message || 'Runtime error. Retrying...');
    }

    async function boot() {
        try {
            state.config = await loadConfig();
            state.idb = await openDatabase();
            await syncPlaylist();
            await sendHeartbeat();
            startTimers();
            setStatus('Player ready.', false);
            if (!state.playbackStarted) {
                state.playbackStarted = true;
                await playCurrentItem();
            }
        } catch (error) {
            handleRuntimeError(error);
            await sleep(10000);
            await boot();
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && state.playbackStarted) {
            void sendHeartbeat();
        }
    });

    boot();
})();
