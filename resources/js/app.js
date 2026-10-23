import { setupInteractions } from './interactions';

/*
 | Alpine ships with Livewire. Components are registered on alpine:init so they
 | work on every page, with or without a Livewire component present.
 */
document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    // Copy text to the clipboard with transient feedback.
    Alpine.data('clipboard', (text) => ({
        copied: false,
        async copy() {
            try {
                await navigator.clipboard.writeText(text);
            } catch {
                const el = document.createElement('textarea');
                el.value = text;
                document.body.appendChild(el);
                el.select();
                document.execCommand('copy');
                el.remove();
            }
            this.copied = true;
            setTimeout(() => (this.copied = false), 1800);
        },
    }));

    // Native share sheet with clipboard fallback.
    Alpine.data('share', (payload) => ({
        shared: false,
        canShare: typeof navigator.share === 'function',
        async share() {
            if (this.canShare) {
                try {
                    await navigator.share(payload);
                    return;
                } catch {
                    /* user cancelled */
                }
            }
            await navigator.clipboard.writeText([payload.text, payload.url].filter(Boolean).join('\n'));
            this.shared = true;
            setTimeout(() => (this.shared = false), 1800);
        },
    }));

    // Drag a bottom sheet down to close it (phones only), like native sheets.
    Alpine.directive('swipe-dismiss', (el, { expression }, { evaluate, cleanup }) => {
        let startY = null;
        let lastY = 0;
        let startTime = 0;
        const mobile = () => window.matchMedia('(max-width: 639px)').matches;
        const scrolledInside = (target) => {
            for (let node = target; node && node !== el.parentElement; node = node.parentElement) {
                if (node.scrollTop > 0) {
                    return true;
                }
            }
            return false;
        };

        const onStart = (event) => {
            if (!mobile() || event.touches.length !== 1 || scrolledInside(event.target)) {
                startY = null;
                return;
            }
            startY = lastY = event.touches[0].clientY;
            startTime = Date.now();
        };
        const onMove = (event) => {
            if (startY === null) {
                return;
            }
            lastY = event.touches[0].clientY;
            const dy = Math.max(0, lastY - startY);
            el.style.transition = 'none';
            el.style.transform = `translateY(${dy}px)`;
        };
        const onEnd = () => {
            if (startY === null) {
                return;
            }
            const dy = lastY - startY;
            const fast = dy > 40 && dy / Math.max(1, Date.now() - startTime) > 0.6;
            startY = null;
            el.style.transition = 'transform 220ms cubic-bezier(0.22, 1, 0.36, 1)';
            if (dy > 110 || fast) {
                el.style.transform = 'translateY(100%)';
                evaluate(expression);
                setTimeout(() => {
                    el.style.transition = '';
                    el.style.transform = '';
                }, 320);
            } else {
                el.style.transform = '';
            }
        };

        el.addEventListener('touchstart', onStart, { passive: true });
        el.addEventListener('touchmove', onMove, { passive: true });
        el.addEventListener('touchend', onEnd);
        el.addEventListener('touchcancel', onEnd);
        cleanup(() => {
            el.removeEventListener('touchstart', onStart);
            el.removeEventListener('touchmove', onMove);
            el.removeEventListener('touchend', onEnd);
            el.removeEventListener('touchcancel', onEnd);
        });
    });

    // Network status for the offline banner.
    Alpine.store('network', {
        online: navigator.onLine,
        init() {
            window.addEventListener('online', () => (this.online = true));
            window.addEventListener('offline', () => (this.online = false));
        },
    });

    // Disable a form's submit button and show progress once submitted (prevents double submits).
    Alpine.data('submitting', () => ({
        busy: false,
        submit(event) {
            if (this.busy) {
                event.preventDefault();
                return;
            }
            this.busy = true;
        },
    }));

    // Camera QR scanning for the gate console. Scans are only ever *sent* to the server for verification.
    Alpine.data('passScanner', () => ({
        scanner: null,
        active: false,
        error: null,
        hasCamera: true,
        async start() {
            this.error = null;
            try {
                const { default: QrScanner } = await import('qr-scanner');
                this.hasCamera = await QrScanner.hasCamera();
                if (!this.hasCamera) {
                    this.error = 'No camera found on this device. Enter the access code instead.';
                    return;
                }
                this.scanner = new QrScanner(
                    this.$refs.video,
                    (result) => this.found(result.data),
                    { highlightScanRegion: true, highlightCodeOutline: true, preferredCamera: 'environment', maxScansPerSecond: 5 },
                );
                await this.scanner.start();
                this.active = true;
            } catch (e) {
                this.error = 'Camera access was blocked. Allow camera access or use manual entry.';
            }
        },
        stop() {
            this.scanner?.stop();
            this.active = false;
        },
        found(value) {
            this.stop();
            if (navigator.vibrate) navigator.vibrate(40);
            this.$dispatch('pass-scanned', { value });
        },
        destroy() {
            this.scanner?.destroy();
        },
    }));

    // Chart.js wrapper. Data comes from the server as JSON; nothing is invented client-side.
    Alpine.data('chart', (config) => ({
        chart: null,
        async init() {
            const { Chart, registerables } = await import('chart.js');
            Chart.register(...registerables);
            Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
            Chart.defaults.color = '#6a716c';
            Chart.defaults.borderColor = '#ece9e2';
            this.chart = new Chart(this.$refs.canvas, config);
        },
        destroy() {
            this.chart?.destroy();
        },
    }));
});

setupInteractions();

if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}
