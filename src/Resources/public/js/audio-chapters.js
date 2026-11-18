(() => {
    'use strict';
    function init() {
        document.querySelectorAll('[data-auriga-audio-player]').forEach(root => {
            if (root.dataset.initialized) return;
            root.dataset.initialized = 'true';
            const audio = root.querySelector('audio');
            const items = Array.from(root.querySelectorAll('[data-chapter-start]'));
            const times = items.map(item => Number(item.dataset.chapterStart));
            const previous = root.querySelector('[data-chapter-previous]');
            const next = root.querySelector('[data-chapter-next]');
            const current = root.querySelector('[data-chapter-current]');
            const details = root.querySelector('details');
            const placeholder = current.textContent;
            let pending = null;
            const index = () => times.reduce((found, time, i) => time <= audio.currentTime + 0.05 ? i : found, -1);
            const available = i => i >= 0 && i < times.length && (!Number.isFinite(audio.duration) || times[i] < audio.duration);
            function update() {
                const active = index();
                previous.disabled = !available(active - 1);
                next.disabled = !available(active + 1);
                items.forEach((item, i) => {
                    item.disabled = !available(i);
                    if (i === active) item.setAttribute('aria-current', 'true');
                    else item.removeAttribute('aria-current');
                });
                current.textContent = active < 0 ? placeholder : `${active + 1}/${items.length} · ${items[active].querySelector('[data-chapter-title]').textContent}`;
                current.title = current.textContent;
            }
            function jump(i) {
                if (!available(i)) return;
                if (audio.readyState === 0) {
                    pending = i;
                    audio.load();
                    return;
                }
                audio.currentTime = times[i];
                update();
            }
            audio.addEventListener('loadedmetadata', () => {
                if (pending !== null) { const target = pending; pending = null; jump(target); }
                update();
            });
            ['timeupdate', 'durationchange', 'seeked'].forEach(event => audio.addEventListener(event, update));
            previous.addEventListener('click', () => jump(index() - 1));
            next.addEventListener('click', () => jump(index() + 1));
            items.forEach((item, i) => item.addEventListener('click', () => {
                jump(i);
                details.open = false;
                details.querySelector('summary').focus();
            }));
            root.addEventListener('keydown', event => {
                if (event.key === 'Escape' && details.open) {
                    details.open = false;
                    details.querySelector('summary').focus();
                }
            });
            root.querySelector('.auriga-audio-chapters').hidden = false;
            update();
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
