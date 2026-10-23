/*
 | App-wide touch feedback, applied to every page without per-view wiring:
 | - pressed state + ripple the moment a finger lands, so a tap visibly "took"
 | - links and submit buttons switch to a loading state and ignore repeat taps
 | - a top progress bar while the next page loads
 | - count-up numbers, header shadow on scroll, bottom bar hidden while typing
 */

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const PRESSABLE = 'a[href], button, [role="button"], summary, label[for], [data-pressable]';
const MIN_PRESS_MS = 140;
const LOADING_TIMEOUT_MS = 12000;

function haptic(ms = 8) {
    if (navigator.vibrate && window.matchMedia('(pointer: coarse)').matches) {
        try {
            navigator.vibrate(ms);
        } catch {
            /* not allowed */
        }
    }
}

/* ---------- Pressed state + ripple ---------- */

function spawnRipple(host, event) {
    if (reduceMotion) {
        return;
    }
    const rect = host.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height) * 2.2;
    const ripple = document.createElement('span');
    ripple.className = 'ripple';
    ripple.style.width = ripple.style.height = `${size}px`;
    ripple.style.left = `${event.clientX - rect.left - size / 2}px`;
    ripple.style.top = `${event.clientY - rect.top - size / 2}px`;
    host.appendChild(ripple);
    ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
}

function setupPress() {
    let pressed = null;
    let pressedAt = 0;

    const release = () => {
        if (!pressed) {
            return;
        }
        const el = pressed;
        pressed = null;
        setTimeout(() => el.classList.remove('is-pressed'), Math.max(0, MIN_PRESS_MS - (Date.now() - pressedAt)));
    };

    document.addEventListener(
        'pointerdown',
        (event) => {
            if (event.button !== 0) {
                return;
            }
            const el = event.target.closest(PRESSABLE);
            if (!el || el.matches(':disabled, [aria-disabled="true"]')) {
                return;
            }
            pressed = el;
            pressedAt = Date.now();
            el.classList.add('is-pressed');

            const rippleHost = event.target.closest('[data-ripple]');
            if (rippleHost && (el.contains(rippleHost) || rippleHost.contains(el))) {
                spawnRipple(rippleHost, event);
            }
            if (el.matches('[data-haptic]')) {
                haptic();
            }
        },
        { passive: true },
    );

    ['pointerup', 'pointercancel', 'dragstart'].forEach((type) => document.addEventListener(type, release, { passive: true }));
    document.addEventListener('scroll', release, { passive: true, capture: true });
}

/* ---------- Page-load progress bar ---------- */

const progress = {
    el: null,
    timer: null,
    mount() {
        this.el = document.createElement('div');
        this.el.id = 'nav-progress';
        this.el.setAttribute('aria-hidden', 'true');
        document.body.appendChild(this.el);
    },
    start() {
        if (!this.el) {
            return;
        }
        clearTimeout(this.timer);
        this.el.classList.remove('is-done');
        this.el.classList.add('is-active');
        this.timer = setTimeout(() => this.done(), LOADING_TIMEOUT_MS);
    },
    done() {
        if (!this.el || !this.el.classList.contains('is-active')) {
            return;
        }
        clearTimeout(this.timer);
        this.el.classList.add('is-done');
        setTimeout(() => this.el.classList.remove('is-active', 'is-done'), 350);
    },
};

/* ---------- Loading states for links and forms ---------- */

const busyElements = new Set();

function markBusy(el) {
    el.classList.add('is-loading');
    el.setAttribute('aria-busy', 'true');
    busyElements.add(el);
    setTimeout(() => clearBusy(el), LOADING_TIMEOUT_MS);
}

function clearBusy(el) {
    el.classList.remove('is-loading');
    el.removeAttribute('aria-busy');
    busyElements.delete(el);
    if (el.tagName === 'FORM') {
        delete el.dataset.submitting;
    }
}

function resetAll() {
    [...busyElements].forEach(clearBusy);
    progress.done();
}

function isDownload(link, url) {
    return link.hasAttribute('download') || /(\.pdf|\/pdf|\.csv|\/export)$/i.test(url.pathname);
}

function setupNavigationFeedback() {
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        const link = event.target.closest('a[href]');
        if (!link || link.target === '_blank' || link.hasAttribute('data-no-loading')) {
            return;
        }
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || /^(mailto|tel|sms):/.test(link.getAttribute('href'))) {
            return;
        }
        if (url.pathname === location.pathname && url.search === location.search && url.hash) {
            return;
        }

        // Second tap while the first is still loading: swallow it.
        if (link.classList.contains('is-loading')) {
            event.preventDefault();
            return;
        }
        if (isDownload(link, url)) {
            link.classList.add('is-loading');
            setTimeout(() => link.classList.remove('is-loading'), 2500);
            return;
        }
        markBusy(link);
        progress.start();
    });

    // Bubble phase on document: runs after Alpine/Livewire/inline handlers, so a submit they cancelled is skipped.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.hasAttribute('data-no-loading')) {
            return;
        }
        if (form.dataset.submitting) {
            event.preventDefault();
            return;
        }
        if (event.defaultPrevented) {
            return;
        }
        form.dataset.submitting = '1';
        markBusy(form);
        const button = event.submitter ?? form.querySelector('button:not([type="button"]), input[type="submit"]');
        if (button) {
            markBusy(button);
        }
        haptic(10);
        if (form.target !== '_blank') {
            progress.start();
        }
    });

    // Back/forward cache restores the old page with its loading state frozen; clear it.
    window.addEventListener('pageshow', resetAll);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            setTimeout(resetAll, 600);
        }
    });
}

/* ---------- Count-up numbers ---------- */

function countUp(el) {
    if (el.children.length) {
        return;
    }
    const text = el.textContent.trim();
    const match = text.match(/-?\d[\d,]*(\.\d+)?/);
    if (!match) {
        return;
    }
    const target = parseFloat(match[0].replace(/,/g, ''));
    if (!isFinite(target) || target === 0) {
        return;
    }
    const decimals = match[1] ? match[1].length - 1 : 0;
    const grouped = match[0].includes(',');
    const prefix = text.slice(0, match.index);
    const suffix = text.slice(match.index + match[0].length);
    const format = (n) => n.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals, useGrouping: grouped });
    const duration = 700;
    const start = performance.now();

    el.style.minWidth = `${el.getBoundingClientRect().width}px`;
    const step = (now) => {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - t, 3);
        el.textContent = prefix + format(target * eased) + suffix;
        if (t < 1) {
            requestAnimationFrame(step);
        } else {
            el.textContent = text;
        }
    };
    requestAnimationFrame(step);
}

function setupCountUp() {
    const nodes = document.querySelectorAll('[data-countup]');
    if (reduceMotion || !nodes.length || !('IntersectionObserver' in window)) {
        return;
    }
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                observer.unobserve(entry.target);
                countUp(entry.target);
            }
        });
    });
    nodes.forEach((node) => observer.observe(node));
}

/* ---------- Scroll + keyboard awareness ---------- */

function setupChrome() {
    const root = document.documentElement;
    let ticking = false;
    const onScroll = () => {
        if (!ticking) {
            requestAnimationFrame(() => {
                root.classList.toggle('is-scrolled', window.scrollY > 6);
                ticking = false;
            });
            ticking = true;
        }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Hide the bottom bar while the on-screen keyboard is up so it never covers the field being typed in.
    const typing = (el) => el?.matches?.('input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]), textarea, select, [contenteditable="true"]');
    document.addEventListener('focusin', (event) => root.classList.toggle('is-typing', !!typing(event.target)));
    document.addEventListener('focusout', () => setTimeout(() => root.classList.toggle('is-typing', !!typing(document.activeElement)), 50));
}

export function setupInteractions() {
    progress.mount();
    setupPress();
    setupNavigationFeedback();
    setupCountUp();
    setupChrome();

    // Livewire swaps content in place; re-run count-ups on newly added nodes.
    document.addEventListener('livewire:navigated', setupCountUp);
}
