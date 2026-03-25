(function () {
    'use strict';

    const PLAYER_VERSION = '1.0.0';
    const CONFIG_PATH = './config.json';
    const DB_NAME = 'cloud_signage_player';
    const STORE_NAME = 'media_cache';

    const stage = document.getElementById('stage');
    const statusEl = document.getElementById('status');
    const overlayEl = document.getElementById('overlay');
    const playlistBannerEl = document.getElementById('playlist-banner');
    const playlistBannerLabelEl = playlistBannerEl ? playlistBannerEl.querySelector('.playlist-banner-label') : null;
    const announcementBarEl = document.getElementById('announcement-bar');
    const announcementTrackEl = announcementBarEl ? announcementBarEl.querySelector('[data-announcement-track]') : null;

    const state = {
        config: null,
        playlist: [],
        currentIndex: 0,
        currentTimer: null,
        currentInterval: null,
        refreshTimer: null,
        heartbeatTimer: null,
        playbackStarted: false,
        idb: null,
        currentObjectUrl: null,
        syncRevision: 0,
        syncPromise: null,
        playbackToken: 0,
        playlistBannerIdentity: null,
        playlistBannerTimer: null,
        apiAnnouncement: null
    };

    function setStatus(message, keepVisible = true) {
        statusEl.textContent = message;
        overlayEl.classList.toggle('hidden', !keepVisible);
    }

    function sleep(ms) {
        return new Promise((resolve) => window.setTimeout(resolve, ms));
    }

    function showPlaylistBanner(playlistName) {
        if (!playlistBannerEl) {
            return;
        }

        if (state.playlistBannerTimer) {
            window.clearTimeout(state.playlistBannerTimer);
            state.playlistBannerTimer = null;
        }

        if (playlistBannerLabelEl) {
            playlistBannerLabelEl.textContent = playlistName || 'No playlist assigned';
        }

        playlistBannerEl.classList.remove('hidden', 'is-active');
        void playlistBannerEl.offsetWidth;
        playlistBannerEl.classList.add('is-active');

        state.playlistBannerTimer = window.setTimeout(() => {
            playlistBannerEl.classList.remove('is-active');
            playlistBannerEl.classList.add('hidden');
            state.playlistBannerTimer = null;
        }, 5000);
    }

    function readUrlConfig() {
        const params = new URLSearchParams(window.location.search);
        const apiBaseUrl = (params.get('api_base_url') || '').trim();
        const screenToken = (params.get('screen') || params.get('token') || '').trim().toUpperCase();
        const cacheNamespace = (params.get('cache_namespace') || '').trim();
        const refreshInterval = Number.parseInt(params.get('refresh_interval_seconds') || '', 10);
        const heartbeatInterval = Number.parseInt(params.get('heartbeat_interval_seconds') || '', 10);
        const announcementText = (params.get('announcement_text') || '').trim();
        const announcementSpeedSeconds = Number.parseInt(params.get('announcement_speed_seconds') || '', 10);
        const announcementPosition = (params.get('announcement_position') || '').trim().toLowerCase();
        const announcementHeightPx = Number.parseInt(params.get('announcement_height_px') || '', 10);

        return {
            api_base_url: apiBaseUrl || null,
            screen_token: screenToken || null,
            cache_namespace: cacheNamespace || null,
            refresh_interval_seconds: Number.isFinite(refreshInterval) && refreshInterval > 0 ? refreshInterval : null,
            heartbeat_interval_seconds: Number.isFinite(heartbeatInterval) && heartbeatInterval > 0 ? heartbeatInterval : null,
            announcement_text: announcementText || null,
            announcement_speed_seconds: Number.isFinite(announcementSpeedSeconds) && announcementSpeedSeconds > 0 ? announcementSpeedSeconds : null,
            announcement_position: announcementPosition === 'top' ? 'top' : (announcementPosition === 'bottom' ? 'bottom' : null),
            announcement_height_px: Number.isFinite(announcementHeightPx) && announcementHeightPx > 0 ? announcementHeightPx : null
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
            announcement_text: '',
            announcement_speed_seconds: 28,
            announcement_position: 'bottom',
            announcement_height_px: 72,
            ...fileConfig
        };

        if (urlConfig.api_base_url) {
            config.api_base_url = urlConfig.api_base_url;
        }
        if (urlConfig.screen_token) {
            config.screen_token = String(urlConfig.screen_token).trim().toUpperCase();
        }
        if (config.screen_code && !config.screen_token) {
            config.screen_token = String(config.screen_code).trim().toUpperCase();
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
        if (urlConfig.announcement_text !== null) {
            config.announcement_text = urlConfig.announcement_text;
        }
        if (urlConfig.announcement_speed_seconds) {
            config.announcement_speed_seconds = urlConfig.announcement_speed_seconds;
        }
        if (urlConfig.announcement_position !== null) {
            config.announcement_position = urlConfig.announcement_position;
        }
        if (urlConfig.announcement_height_px) {
            config.announcement_height_px = urlConfig.announcement_height_px;
        }

        if (!config.api_base_url || !config.screen_token) {
            throw new Error('Provide screen code and api_base_url in the URL or in config.json.');
        }

        return config;
    }

    function applyAnnouncementBar() {
        if (!announcementBarEl || !announcementTrackEl) {
            return;
        }

        const apiAnnouncementText = String(state.apiAnnouncement && state.apiAnnouncement.message_text ? state.apiAnnouncement.message_text : '').trim();
        const announcementText = apiAnnouncementText !== ''
            ? apiAnnouncementText
            : String(state.config.announcement_text || '').trim();

        if (announcementText === '') {
            announcementBarEl.classList.add('hidden');
            announcementBarEl.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('has-announcement');
            document.body.classList.remove('has-announcement-top', 'has-announcement-bottom');
            announcementBarEl.classList.remove('is-top', 'is-bottom');
            announcementTrackEl.innerHTML = '';
            return;
        }

        const speedSource = state.apiAnnouncement && state.apiAnnouncement.speed_seconds
            ? state.apiAnnouncement.speed_seconds
            : state.config.announcement_speed_seconds;
        const positionSource = state.apiAnnouncement && state.apiAnnouncement.position
            ? state.apiAnnouncement.position
            : state.config.announcement_position;
        const heightSource = state.apiAnnouncement && state.apiAnnouncement.height_px
            ? state.apiAnnouncement.height_px
            : state.config.announcement_height_px;
        const speedSeconds = Math.max(10, Number.parseInt(speedSource, 10) || 28);
        const position = positionSource === 'top' ? 'top' : 'bottom';
        const heightPx = Math.max(40, Math.min(220, Number.parseInt(heightSource, 10) || 72));
        const fontSizePx = Math.max(18, Math.round(heightPx * 0.58));
        const chipHeightPx = Math.max(22, Math.round(heightPx * 0.42));
        const chipFontSizePx = Math.max(10, Math.round(heightPx * 0.22));
        announcementBarEl.style.setProperty('--announcement-duration', speedSeconds + 's');
        announcementBarEl.style.setProperty('--announcement-height', heightPx + 'px');
        announcementBarEl.style.setProperty('--announcement-font-size', fontSizePx + 'px');
        announcementBarEl.style.setProperty('--announcement-chip-height', chipHeightPx + 'px');
        announcementBarEl.style.setProperty('--announcement-chip-font-size', chipFontSizePx + 'px');
        document.body.style.setProperty('--announcement-height', heightPx + 'px');
        announcementTrackEl.innerHTML = '';
        announcementBarEl.classList.remove('is-top', 'is-bottom');
        announcementBarEl.classList.add(position === 'top' ? 'is-top' : 'is-bottom');

        for (let index = 0; index < 6; index += 1) {
            const item = document.createElement('div');
            item.className = 'announcement-item';
            item.textContent = announcementText;
            announcementTrackEl.appendChild(item);
        }

        announcementBarEl.classList.remove('hidden');
        announcementBarEl.setAttribute('aria-hidden', 'false');
        document.body.classList.add('has-announcement');
        document.body.classList.toggle('has-announcement-top', position === 'top');
        document.body.classList.toggle('has-announcement-bottom', position === 'bottom');
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

    function playlistIdentity(item) {
        if (!item) {
            return '';
        }

        if (item.type === 'quiz') {
            return [
                item.playlist_item_id || '',
                item.quiz_question_id || '',
                item.quiz_selection_mode || 'fixed'
            ].join(':');
        }

        return [
            item.playlist_item_id || '',
            item.media_id || '',
            item.filename || '',
            item.duration || ''
        ].join(':');
    }

    function buildPlaylistBannerIdentity(playlistInfo) {
        return playlistInfo && playlistInfo.id ? String(playlistInfo.id) : 'none';
    }

    async function syncPlaylist(options = {}) {
        const shouldRestartPlayback = Boolean(options.restartPlayback);

        if (state.syncPromise) {
            return state.syncPromise;
        }

        state.syncPromise = (async () => {
            const query = '?screen=' + encodeURIComponent(state.config.screen_token);
            const data = await fetchApiJson('/api/get_playlist.php' + query);
            const nextPlaylist = Array.isArray(data.items) ? data.items : [];
            const currentItemIdentity = playlistIdentity(state.playlist[state.currentIndex]);
            const hadPreviousPlaylist = state.playlistBannerIdentity !== null;
            const nextPlaylistBannerIdentity = buildPlaylistBannerIdentity(data.playlist || null);
            const playlistChanged = hadPreviousPlaylist && state.playlistBannerIdentity !== nextPlaylistBannerIdentity;

            state.playlist = nextPlaylist;
            state.syncRevision = Number.parseInt(data.screen && data.screen.sync_revision, 10) || 0;
            state.playlistBannerIdentity = nextPlaylistBannerIdentity;
            state.apiAnnouncement = data.ticker || null;
            applyAnnouncementBar();

            if (state.playlist.length === 0) {
                state.currentIndex = 0;
                setStatus('No playlist assigned or playlist is empty.');
            } else {
                const matchingIndex = state.playlist.findIndex((item) => playlistIdentity(item) === currentItemIdentity);
                state.currentIndex = matchingIndex >= 0
                    ? matchingIndex
                    : Math.min(state.currentIndex, Math.max(0, state.playlist.length - 1));
                setStatus('Playlist synced: ' + state.playlist.length + ' item(s).');
            }

            if (!hadPreviousPlaylist || playlistChanged) {
                showPlaylistBanner(data.playlist && data.playlist.name ? data.playlist.name : '');
            }

            window.setTimeout(() => overlayEl.classList.add('hidden'), 4000);
            void prefetchMissingMedia();

            if (shouldRestartPlayback && state.playbackStarted) {
                restartPlayback();
            }

            return data;
        })();

        try {
            return await state.syncPromise;
        } finally {
            state.syncPromise = null;
        }
    }

    async function prefetchMissingMedia() {
        for (const item of state.playlist) {
            if (item.type === 'quiz') {
                continue;
            }

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

        if (state.currentInterval) {
            window.clearInterval(state.currentInterval);
            state.currentInterval = null;
        }

        if (state.currentObjectUrl) {
            URL.revokeObjectURL(state.currentObjectUrl);
            state.currentObjectUrl = null;
        }

        const videos = stage.querySelectorAll('video');
        videos.forEach((video) => {
            try {
                video.pause();
            } catch (error) {
                console.warn('Video pause failed during stage clear.', error);
            }
        });

        stage.innerHTML = '';
    }

    async function resolvePlayableSource(item) {
        if (item.type === 'quiz') {
            return null;
        }

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

    function restartPlayback() {
        state.currentIndex = 0;
        clearStage();
        void playCurrentItem();
    }

    async function playImage(item, sourceUrl, playbackToken) {
        return new Promise((resolve, reject) => {
            const img = document.createElement('img');
            img.alt = item.title || '';
            img.onload = () => {
                if (playbackToken !== state.playbackToken) {
                    resolve();
                    return;
                }
                stage.appendChild(img);
                scheduleNext((item.duration || 10) * 1000);
                resolve();
            };
            img.onerror = () => reject(new Error('Image failed to load.'));
            img.src = sourceUrl;
        });
    }

    async function playVideo(item, sourceUrl, playbackToken) {
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
                if (playbackToken !== state.playbackToken) {
                    cleanup();
                    resolve();
                    return;
                }

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
                if (playbackToken !== state.playbackToken) {
                    cleanup();
                    return;
                }
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

    async function playQuiz(item, playbackToken) {
        return new Promise((resolve) => {
            let remainingSeconds = Math.max(1, Number.parseInt(item.countdown_seconds || 10, 10));
            const revealDuration = Math.max(1, Number.parseInt(item.reveal_duration || 5, 10));

            const wrapper = document.createElement('div');
            wrapper.className = 'quiz-card';

            const badge = document.createElement('div');
            badge.className = 'quiz-badge';
            badge.textContent = 'Quiz';

            const question = document.createElement('div');
            question.className = 'quiz-question';
            question.textContent = item.question || item.title || 'Quiz question';

            const countdown = document.createElement('div');
            countdown.className = 'quiz-countdown';

            const answersList = document.createElement('div');
            answersList.className = 'quiz-answers';

            const answerNodes = new Map();
            for (const answer of item.answers || []) {
                const answerEl = document.createElement('div');
                answerEl.className = 'quiz-answer';
                answerEl.dataset.answerKey = answer.key;
                answerEl.innerHTML = '<span class="quiz-answer-key"></span><span class="quiz-answer-text"></span>';
                answerEl.querySelector('.quiz-answer-key').textContent = answer.key;
                answerEl.querySelector('.quiz-answer-text').textContent = answer.text;
                answersList.appendChild(answerEl);
                answerNodes.set(answer.key, answerEl);
            }

            const footer = document.createElement('div');
            footer.className = 'quiz-footer';
            footer.textContent = 'Choose the best answer before the timer ends.';

            wrapper.appendChild(badge);
            wrapper.appendChild(question);
            wrapper.appendChild(countdown);
            wrapper.appendChild(answersList);
            wrapper.appendChild(footer);

            if (playbackToken !== state.playbackToken) {
                resolve();
                return;
            }

            stage.appendChild(wrapper);

            const updateCountdown = () => {
                countdown.textContent = 'Answer in ' + remainingSeconds + ' second' + (remainingSeconds === 1 ? '' : 's');
            };

            updateCountdown();

            state.currentInterval = window.setInterval(() => {
                if (playbackToken !== state.playbackToken) {
                    window.clearInterval(state.currentInterval);
                    state.currentInterval = null;
                    resolve();
                    return;
                }

                remainingSeconds -= 1;
                if (remainingSeconds <= 0) {
                    window.clearInterval(state.currentInterval);
                    state.currentInterval = null;
                    countdown.textContent = 'Time is up.';
                    footer.textContent = 'Correct answer: ' + item.correct_answer;

                    const correctAnswerNode = answerNodes.get(item.correct_answer);
                    if (correctAnswerNode) {
                        correctAnswerNode.classList.add('is-correct');
                    }

                    state.currentTimer = window.setTimeout(() => {
                        state.currentIndex = nextIndex();
                        void playCurrentItem();
                    }, revealDuration * 1000);
                    resolve();
                    return;
                }

                updateCountdown();
            }, 1000);
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
        const playbackToken = ++state.playbackToken;
        if (!item) {
            state.currentIndex = 0;
            state.currentTimer = window.setTimeout(() => void playCurrentItem(), 1000);
            return;
        }

        try {
            const sourceUrl = await resolvePlayableSource(item);

            if (item.type === 'quiz') {
                await playQuiz(item, playbackToken);
            } else if (item.type === 'video') {
                await playVideo(item, sourceUrl, playbackToken);
            } else {
                await playImage(item, sourceUrl, playbackToken);
            }
        } catch (error) {
            console.error('Playback failed for', item, error);
            state.currentIndex = nextIndex();
            state.currentTimer = window.setTimeout(() => void playCurrentItem(), 500);
        }
    }

    async function sendHeartbeat() {
        const payload = {
            screen: state.config.screen_token,
            ip: '',
            resolution: window.innerWidth + 'x' + window.innerHeight,
            player_version: PLAYER_VERSION
        };

        try {
            const response = await fetchApiJson('/api/heartbeat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const latestRevision = Number.parseInt(response.sync_revision, 10) || 0;
            if (latestRevision > state.syncRevision) {
                await syncPlaylist({ restartPlayback: true });
            }
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
            applyAnnouncementBar();
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
