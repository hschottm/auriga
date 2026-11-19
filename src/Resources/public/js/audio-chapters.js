(() => {
    'use strict';
    const format = seconds => {
        if (!Number.isFinite(seconds)) return '—';
        const total = Math.max(0, Math.floor(seconds));
        return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
    };
    const players = [];
    let saveQueue = Promise.resolve();
    function savePreference(root, mode, report) {
        if (!root.dataset.preferenceToken) return;
        // Serialize writes so a quick second choice cannot be overwritten by the first.
        saveQueue = saveQueue.catch(() => {}).then(async () => {
            try {
                const body = new URLSearchParams({FORM_SUBMIT: 'auriga_player_preference', REQUEST_TOKEN: root.dataset.preferenceToken, mode});
                const response = await fetch(window.location.href, {method: 'POST', credentials: 'same-origin', body, headers: {'Accept': 'application/json'}});
                if (!response.ok || (await response.json()).mode !== mode) throw new Error('Preference not saved');
                report('');
            } catch (error) { report(root.dataset.preferenceError); }
        });
    }
    function init() {
        document.querySelectorAll('[data-auriga-audio-player]').forEach(root => {
            if (root.dataset.initialized) return;
            const find = selector => root.querySelector(selector);
            const audio = find('audio');
            const ui = find('.auriga-audio-ui');
            const play = find('[data-audio-play]');
            const seek = find('[data-audio-seek]');
            const volume = find('[data-audio-volume]');
            const mute = find('[data-audio-mute]');
            const status = find('[data-audio-status]');
            const previous = find('[data-chapter-previous]');
            const next = find('[data-chapter-next]');
            const fullItems = Array.from(root.querySelectorAll('[data-chapter-start]'));
            const storyItems = Array.from(root.querySelectorAll('[data-story-start]'));
            const modes = Array.from(root.querySelectorAll('[data-audio-mode]'));
            let storyMode = false;
            let storyFinished = false;
            let boundaryTimer = null;
            let items = fullItems;
            let times = items.map(item => Number(item.dataset.chapterStart));
            const ranges = () => storyItems.map(item => ({start: Number(item.dataset.storyStart), end: Math.min(Number(item.dataset.storyEnd), Number.isFinite(audio.duration) ? audio.duration : Infinity)})).filter(range => range.end > range.start);
            const storyDuration = () => ranges().reduce((sum, range) => sum + range.end - range.start, 0);
            const position = () => storyMode ? ranges().reduce((sum, range) => sum + Math.max(0, Math.min(audio.currentTime, range.end) - range.start), 0) : audio.currentTime;
            const duration = () => storyMode ? storyDuration() : audio.duration;
            function sourceTime(value) {
                const segments = ranges();
                for (const range of segments) {
                    const length = range.end - range.start;
                    if (value < length) return range.start + value;
                    value -= length;
                }
                return segments.length ? segments[segments.length - 1].end : 0;
            }
            function enforceStory(restart = false) {
                if (!storyMode || audio.readyState === 0) return;
                const segments = ranges();
                if (!segments.length) { audio.pause(); return; }
                if (restart && storyFinished) { audio.currentTime = segments[0].start; storyFinished = false; }
                const segment = segments.find(range => audio.currentTime < range.end);
                if (!segment) {
                    storyFinished = true;
                    audio.pause();
                    if (audio.currentTime !== segments[segments.length - 1].end) audio.currentTime = segments[segments.length - 1].end;
                } else if (audio.currentTime < segment.start) {
                    audio.currentTime = segment.start;
                    storyFinished = false;
                }
            }
            function scheduleBoundary() {
                clearTimeout(boundaryTimer);
                if (!storyMode || audio.paused || audio.seeking) return;
                const segment = ranges().find(range => audio.currentTime >= range.start && audio.currentTime < range.end);
                if (!segment) return;
                const delay = Math.max(10, (segment.end - audio.currentTime) * 1000 / (audio.playbackRate || 1));
                boundaryTimer = setTimeout(() => { enforceStory(); update(); }, Math.min(delay, 1000));
            }
            const current = find('[data-chapter-current]');
            const details = find('details');
            const placeholder = current ? current.textContent : '';
            let pending = null;
            let scrubbing = false;
            const index = () => times.reduce((found, time, i) => time <= audio.currentTime + 0.05 ? i : found, -1);
            const available = i => i >= 0 && i < times.length && (!Number.isFinite(audio.duration) || times[i] < audio.duration);
            function message(text = '') { status.textContent = text; status.hidden = !text; }
            function update() {
                const total = duration();
                const active = index();
                previous.disabled = !available(active - 1);
                next.disabled = !available(active + 1);
                items.forEach((item, i) => {
                    item.disabled = !available(i);
                    if (i === active) item.setAttribute('aria-current', 'true');
                    else item.removeAttribute('aria-current');
                });
                if (current) {
                    current.textContent = active < 0 ? placeholder : `${active + 1}/${items.length} · ${items[active].querySelector('[data-chapter-title]').textContent}`;
                    current.title = current.textContent;
                }
                play.setAttribute('aria-label', audio.paused ? play.dataset.playLabel : play.dataset.pauseLabel);
                play.title = play.getAttribute('aria-label');
                find('[data-play-icon]').toggleAttribute('hidden', !audio.paused);
                find('[data-pause-icon]').toggleAttribute('hidden', audio.paused);
                seek.disabled = !Number.isFinite(total) || total <= 0;
                if (!scrubbing) {
                    seek.max = seek.disabled ? 100 : total;
                    seek.value = position();
                    seek.setAttribute('aria-valuetext', `${format(position())} / ${format(total)}`);
                }
                find('[data-audio-clock]').textContent = `${format(position())} / ${format(total)}`;
                mute.setAttribute('aria-pressed', String(audio.muted));
                find('[data-muted-icon]').toggleAttribute('hidden', !audio.muted && audio.volume > 0);
                volume.value = audio.muted ? 0 : audio.volume;
                scheduleBoundary();
            }
            function jump(i) {
                if (!available(i)) return;
                if (audio.readyState === 0) { pending = i; audio.load(); return; }
                storyFinished = false;
                audio.currentTime = times[i];
                update();
            }
            play.addEventListener('click', async () => {
                if (!audio.paused) { audio.pause(); return; }
                message();
                try { enforceStory(true); await audio.play(); }
                catch (error) { if (error.name !== 'AbortError') message(status.dataset.error); }
                update();
            });
            audio.addEventListener('play', () => {
                enforceStory(true);
                document.querySelectorAll('[data-auriga-audio-player] audio').forEach(other => { if (other !== audio) other.pause(); });
                update();
            });
            audio.addEventListener('loadedmetadata', () => {
                enforceStory();
                if (pending !== null) { const target = pending; pending = null; jump(target); }
                update();
            });
            ['timeupdate', 'durationchange', 'seeking', 'seeked', 'pause', 'ended', 'volumechange', 'ratechange'].forEach(event => audio.addEventListener(event, () => {
                if (['timeupdate', 'durationchange', 'seeking', 'seeked'].includes(event)) enforceStory();
                update();
            }));
            ['waiting', 'stalled'].forEach(event => audio.addEventListener(event, () => { if (!audio.paused) message(status.dataset.loading); }));
            ['playing', 'canplay'].forEach(event => audio.addEventListener(event, () => message()));
            audio.addEventListener('error', () => { message(status.dataset.error); update(); });
            seek.addEventListener('input', () => {
                scrubbing = true;
                find('[data-audio-clock]').textContent = `${format(Number(seek.value))} / ${format(duration())}`;
                seek.setAttribute('aria-valuetext', format(Number(seek.value)));
            });
            seek.addEventListener('change', () => {
                if (!seek.disabled) {
                    storyFinished = false;
                    audio.currentTime = storyMode ? sourceTime(Number(seek.value)) : Number(seek.value);
                    enforceStory();
                }
                scrubbing = false;
                update();
            });
            volume.addEventListener('input', () => { audio.volume = Number(volume.value); audio.muted = false; update(); });
            mute.addEventListener('click', () => { audio.muted = !audio.muted; update(); });
            previous.addEventListener('click', () => jump(index() - 1));
            next.addEventListener('click', () => jump(index() + 1));
            [...fullItems, ...storyItems].forEach(item => item.addEventListener('click', () => {
                jump(items.indexOf(item));
                details.open = false;
                details.querySelector('summary').focus();
            }));
            function setMode(value, explicit = false) {
                if (!modes.length) return;
                storyMode = value === 'story';
                storyFinished = false;
                pending = null;
                items = storyMode ? storyItems : fullItems;
                times = items.map(item => Number(storyMode ? item.dataset.storyStart : item.dataset.chapterStart));
                find('[data-full-list]').hidden = storyMode;
                find('[data-story-list]').hidden = !storyMode;
                modes.forEach(mode => mode.setAttribute('aria-pressed', String(mode.dataset.audioMode === value)));
                if (details) details.hidden = items.length === 0;
                if (storyMode) {
                    if (audio.readyState === 0) { pending = 0; if (explicit) audio.load(); }
                    else {
                        const segments = ranges();
                        if (segments.length && audio.currentTime >= segments[segments.length - 1].end) audio.currentTime = segments[0].start;
                        enforceStory();
                    }
                }
                update();
            }
            players.push({root, setMode});
            modes.forEach(button => button.addEventListener('click', () => {
                const value = button.dataset.audioMode;
                players.forEach(player => player.setMode(value, player.root === root));
                savePreference(root, value, message);
            }));
            setMode(root.dataset.preferredMode === 'story' ? 'story' : 'full');
            if (details) details.hidden = items.length === 0;
            root.addEventListener('keydown', event => {
                if (event.key === 'Escape' && details && details.open) {
                    details.open = false;
                    details.querySelector('summary').focus();
                }
            });
            update();
            audio.controls = false;
            audio.hidden = true;
            ui.hidden = false;
            root.dataset.initialized = 'true';
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
