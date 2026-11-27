/**
 * PWA install + cache lifecycle.
 * Browsers do not fire an uninstall event — when we leave standalone after a
 * prior install, wipe caches/SW so Chrome can offer install again.
 */

import { trackEvent } from '@/composables/useAnalytics';

export type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

const INSTALLED_KEY = 'fuelpk.pwaInstalled';

type InstallListener = (canInstall: boolean) => void;
type InstalledListener = () => void;

let deferred: BeforeInstallPromptEvent | null = null;
let promptReady = false;
const installListeners = new Set<InstallListener>();
const installedListeners = new Set<InstalledListener>();
let bootstrapped = false;

export function isStandalone(): boolean {
    if (typeof window === 'undefined') return false;
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.matchMedia('(display-mode: fullscreen)').matches ||
        (window.navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

export function canPromptInstall(): boolean {
    return promptReady && deferred !== null;
}

function notifyInstallListeners(): void {
    const can = canPromptInstall();
    installListeners.forEach((fn) => fn(can));
}

export function onPwaInstallAvailable(fn: InstallListener): () => void {
    installListeners.add(fn);
    fn(canPromptInstall());
    return () => installListeners.delete(fn);
}

export function onPwaInstalled(fn: InstalledListener): () => void {
    installedListeners.add(fn);
    return () => installedListeners.delete(fn);
}

export async function clearPwaCaches(): Promise<void> {
    if (!('caches' in window)) return;
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => caches.delete(k)));
}

export async function unregisterServiceWorkers(): Promise<void> {
    if (!('serviceWorker' in navigator)) return;
    const regs = await navigator.serviceWorker.getRegistrations();
    await Promise.all(regs.map((r) => r.unregister()));
}

/** Full wipe used after uninstall detection. */
export async function resetPwaStorage(): Promise<void> {
    await clearPwaCaches();
    await unregisterServiceWorkers();
    try {
        localStorage.removeItem(INSTALLED_KEY);
    } catch {
        /* ignore */
    }
}

async function registerServiceWorker(): Promise<void> {
    if (!('serviceWorker' in navigator)) return;
    try {
        // Bump ?v= when sw.js cache name changes so browsers fetch the new worker.
        const reg = await navigator.serviceWorker.register('/sw.js?v=29', { scope: '/' });
        await reg.update();
        if (reg.waiting) {
            reg.waiting.postMessage({ type: 'SKIP_WAITING' });
        }
        const ready = await navigator.serviceWorker.ready;
        ready.active?.postMessage({ type: 'CLEAR_OLD_CACHES' });
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            // New SW took over — reload once so PWA matches the live web UI.
            if (sessionStorage.getItem('fuelpk.swReloaded') === '1') return;
            sessionStorage.setItem('fuelpk.swReloaded', '1');
            window.location.reload();
        });

        // When returning to a standalone PWA, check for a newer worker (parity with web).
        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                void reg.update();
            }
        };
        document.addEventListener('visibilitychange', onVisible);
        window.addEventListener('focus', () => void reg.update());
    } catch {
        /* ignore */
    }
}

export async function promptPwaInstall(): Promise<'accepted' | 'dismissed' | 'unavailable'> {
    if (!deferred) return 'unavailable';
    const event = deferred;
    deferred = null;
    promptReady = false;
    notifyInstallListeners();
    await event.prompt();
    const { outcome } = await event.userChoice;
    if (outcome === 'accepted') {
        try {
            localStorage.setItem(INSTALLED_KEY, '1');
        } catch {
            /* ignore */
        }
        installedListeners.forEach((fn) => fn());
    }
    return outcome;
}

/**
 * Call once from the app entry. Safe to call multiple times.
 */
export async function initPwa(): Promise<void> {
    if (typeof window === 'undefined' || bootstrapped) return;
    bootstrapped = true;

    const standalone = isStandalone();

    if (standalone) {
        try {
            localStorage.setItem(INSTALLED_KEY, '1');
        } catch {
            /* ignore */
        }
        await registerServiceWorker();
        return;
    }

    // In browser mode we no longer wipe eagerly on load — that used to delete the
    // caches/SW shared with an installed PWA whenever the owner merely opened the
    // site in a normal tab. `beforeinstallprompt` only fires when the app is NOT
    // installed, so it is our reliable signal of a genuine uninstall.
    let wasInstalled = false;
    try {
        wasInstalled = localStorage.getItem(INSTALLED_KEY) === '1';
    } catch {
        /* ignore */
    }

    window.addEventListener('beforeinstallprompt', (e: Event) => {
        e.preventDefault();
        deferred = e as BeforeInstallPromptEvent;
        promptReady = true;

        // We previously recorded an install but the browser is now offering to
        // install again → the PWA was uninstalled. Clear its leftover storage once.
        if (wasInstalled) {
            wasInstalled = false;
            void resetPwaStorage().then(() => registerServiceWorker());
        }

        notifyInstallListeners();
        trackEvent('pwa_install_available');
    });

    window.addEventListener('appinstalled', () => {
        deferred = null;
        promptReady = false;
        try {
            localStorage.setItem(INSTALLED_KEY, '1');
        } catch {
            /* ignore */
        }
        notifyInstallListeners();
        installedListeners.forEach((fn) => fn());
        trackEvent('pwa_install', { outcome: 'installed' });
    });

    // Fresh SW after wipe so the site becomes installable again.
    await registerServiceWorker();
}
