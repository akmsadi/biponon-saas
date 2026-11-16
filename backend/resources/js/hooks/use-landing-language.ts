import { useSyncExternalStore } from 'react';

export type LandingLanguage = 'en' | 'bn';

const STORAGE_KEY = 'landing-language';

const listeners = new Set<() => void>();
let currentLanguage: LandingLanguage | null = null;

/**
 * The visitor's saved choice, else Bangla when their browser prefers it, else English.
 */
const detectLanguage = (): LandingLanguage => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);

        if (stored === 'en' || stored === 'bn') {
            return stored;
        }
    } catch {
        // Storage can be blocked (private mode); fall through to the browser language.
    }

    return navigator.languages.some((language) => language.startsWith('bn'))
        ? 'bn'
        : 'en';
};

const getLanguage = (): LandingLanguage => {
    currentLanguage ??= detectLanguage();

    return currentLanguage;
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

/**
 * Landing page language. The server always renders English; the browser switches
 * to the visitor's language right after hydration.
 */
export function useLandingLanguage(): {
    readonly language: LandingLanguage;
    readonly updateLanguage: (language: LandingLanguage) => void;
} {
    const language = useSyncExternalStore(
        subscribe,
        getLanguage,
        (): LandingLanguage => 'en',
    );

    const updateLanguage = (next: LandingLanguage): void => {
        currentLanguage = next;

        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Not remembered when storage is blocked; the switch still works.
        }

        listeners.forEach((listener) => listener());
    };

    return { language, updateLanguage } as const;
}
