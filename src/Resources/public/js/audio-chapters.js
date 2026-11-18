(() => {
    'use strict';
    const format = seconds => {
        if (!Number.isFinite(seconds)) return '—';
        const total = Math.max(0, Math.floor(seconds));
        return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
    };
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
            const items = Array.from(root.querySelectorAll('[data-chapter-start]'));
            const times = items.map(item => Number(item.dataset.chapterStart));
            const current = find('[data-chapter-current]');
            const details = find('details');
            const placeholder = current ? current.textContent : '';
            let pending = null;
            let scrubbing = false;
            const index = () => times.reduce((found, time, i) => time <= audio.currentTime + 0.05 ? i : found, -1);
            const available = i => i >= 0 && i < times.length && (!Number.isFinite(audio.duration) || times[i] < audio.duration);
            function message(text = '') { status.textContent = text; status.hidden = !text; }
            function update() {
                const duration = audio.duration;
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
                seek.disabled = !Number.isFinite(duration) || duration <= 0;
                if (!scrubbing) {
                    seek.max = seek.disabled ? 100 : duration;
                    seek.value = audio.currentTime;
                    seek.setAttribute('aria-valuetext', `${format(audio.currentTime)} / ${format(duration)}`);
                }
                find('[data-audio-clock]').textContent = `${format(audio.currentTime)} / ${format(duration)}`;
                mute.setAttribute('aria-pressed', String(audio.muted));
                find('[data-muted-icon]').toggleAttribute('hidden', !audio.muted && audio.volume > 0);
                volume.value = audio.muted ? 0 : audio.volume;
            }
            function jump(i) {
                if (!available(i)) return;
                if (audio.readyState === 0) { pending = i; audio.load(); return; }
                audio.currentTime = times[i];
                update();
            }
            play.addEventListener('click', async () => {
                if (!audio.paused) { audio.pause(); return; }
                message();
                try { await audio.play(); }
                catch (error) { if (error.name !== 'AbortError') message(status.dataset.error); }
                update();
            });
            audio.addEventListener('play', () => {
                document.querySelectorAll('[data-auriga-audio-player] audio').forEach(other => { if (other !== audio) other.pause(); });
                update();
            });
            audio.addEventListener('loadedmetadata', () => {
                if (pending !== null) { const target = pending; pending = null; jump(target); }
                update();
            });
            ['timeupdate', 'durationchange', 'seeked', 'pause', 'ended', 'volumechange'].forEach(event => audio.addEventListener(event, update));
            ['waiting', 'stalled'].forEach(event => audio.addEventListener(event, () => { if (!audio.paused) message(status.dataset.loading); }));
            ['playing', 'canplay'].forEach(event => audio.addEventListener(event, () => message()));
            audio.addEventListener('error', () => { message(status.dataset.error); update(); });
            seek.addEventListener('input', () => {
                scrubbing = true;
                find('[data-audio-clock]').textContent = `${format(Number(seek.value))} / ${format(audio.duration)}`;
                seek.setAttribute('aria-valuetext', format(Number(seek.value)));
            });
            seek.addEventListener('change', () => {
                if (!seek.disabled) audio.currentTime = Number(seek.value);
                scrubbing = false;
                update();
            });
            volume.addEventListener('input', () => { audio.volume = Number(volume.value); audio.muted = false; update(); });
            mute.addEventListener('click', () => { audio.muted = !audio.muted; update(); });
            previous.addEventListener('click', () => jump(index() - 1));
            next.addEventListener('click', () => jump(index() + 1));
            items.forEach((item, i) => item.addEventListener('click', () => {
                jump(i);
                details.open = false;
                details.querySelector('summary').focus();
            }));
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
