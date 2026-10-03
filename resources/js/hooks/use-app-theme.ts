import { useSyncExternalStore } from 'react';

type ThemePreference = 'light' | 'dark' | 'system';

const themeStorageKey = 'acms-theme';
const themeChangeEvent = 'acms-theme-change';
const darkModeQuery = '(prefers-color-scheme: dark)';
let temporaryTheme: ThemePreference | null = null;

function storedTheme(): ThemePreference {
    if (typeof window === 'undefined') {
        return 'system';
    }

    try {
        const theme =
            window.localStorage.getItem(themeStorageKey) ??
            window.localStorage.getItem('grademanager-theme');

        return theme === 'light' || theme === 'dark' || theme === 'system'
            ? theme
            : (temporaryTheme ?? 'system');
    } catch {
        return temporaryTheme ?? 'system';
    }
}

function themeIsDark(theme: ThemePreference): boolean {
    return (
        theme === 'dark' ||
        (theme === 'system' && window.matchMedia(darkModeQuery).matches)
    );
}

function themeSnapshot(): string {
    const theme = storedTheme();

    return `${theme}:${themeIsDark(theme) ? 'dark' : 'light'}`;
}

function subscribeToThemeChanges(callback: () => void): () => void {
    const mediaQuery = window.matchMedia(darkModeQuery);
    const handleThemeChange = () => {
        document.documentElement.classList.toggle(
            'dark',
            themeIsDark(storedTheme()),
        );
        callback();
    };

    window.addEventListener('storage', handleThemeChange);
    window.addEventListener(themeChangeEvent, handleThemeChange);
    mediaQuery.addEventListener('change', handleThemeChange);

    return () => {
        window.removeEventListener('storage', handleThemeChange);
        window.removeEventListener(themeChangeEvent, handleThemeChange);
        mediaQuery.removeEventListener('change', handleThemeChange);
    };
}

export function useAppTheme(): [ThemePreference, 'light' | 'dark'] {
    const snapshot = useSyncExternalStore(
        subscribeToThemeChanges,
        themeSnapshot,
        () => 'system:light',
    );

    return snapshot.split(':') as [ThemePreference, 'light' | 'dark'];
}

export function setAppTheme(theme: ThemePreference): void {
    temporaryTheme = theme;

    try {
        window.localStorage.setItem(themeStorageKey, theme);
    } catch {
        // Keep the selection for this page when storage is unavailable.
    }

    document.documentElement.classList.toggle('dark', themeIsDark(theme));
    window.dispatchEvent(new Event(themeChangeEvent));
}
