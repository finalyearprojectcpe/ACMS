import { RiMoonLine, RiSunLine } from '@remixicon/react';
import { useEffect, useSyncExternalStore } from 'react';

import { Button } from '@/components/ui/button';

type ThemePreference = 'light' | 'dark' | 'system';

const themeStorageKey = 'grademanager-theme';
const themeChangeEvent = 'grademanager-theme-change';
const darkModeQuery = '(prefers-color-scheme: dark)';
const serverThemeSnapshot = 'system:light';

function storedTheme(): ThemePreference {
    if (typeof window === 'undefined') {
        return 'system';
    }

    let theme: string | null = null;

    try {
        theme = window.localStorage.getItem(themeStorageKey);
    } catch {
        return 'system';
    }

    return theme === 'light' || theme === 'dark' || theme === 'system'
        ? theme
        : 'system';
}

function prefersDarkMode(): boolean {
    return (
        typeof window !== 'undefined' &&
        typeof window.matchMedia === 'function' &&
        window.matchMedia(darkModeQuery).matches
    );
}

function themeIsDark(theme: ThemePreference): boolean {
    return theme === 'dark' || (theme === 'system' && prefersDarkMode());
}

function applyTheme(theme: ThemePreference): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle('dark', themeIsDark(theme));
}

function themeSnapshot(): string {
    const theme = storedTheme();

    return `${theme}:${themeIsDark(theme) ? 'dark' : 'light'}`;
}

function subscribeToThemeChanges(callback: () => void) {
    if (typeof window === 'undefined') {
        return () => {};
    }

    const mediaQuery = window.matchMedia(darkModeQuery);

    window.addEventListener('storage', callback);
    window.addEventListener(themeChangeEvent, callback);
    mediaQuery.addEventListener('change', callback);

    return () => {
        window.removeEventListener('storage', callback);
        window.removeEventListener(themeChangeEvent, callback);
        mediaQuery.removeEventListener('change', callback);
    };
}

export function ThemeToggle() {
    const snapshot = useSyncExternalStore(
        subscribeToThemeChanges,
        themeSnapshot,
        () => serverThemeSnapshot,
    );
    const [theme, resolvedTheme] = snapshot.split(':') as [
        ThemePreference,
        'dark' | 'light',
    ];
    const isDark = resolvedTheme === 'dark';
    const nextTheme = isDark ? 'light' : 'dark';

    useEffect(() => {
        applyTheme(theme);
    }, [snapshot, theme]);

    function toggleTheme(): void {
        try {
            window.localStorage.setItem(themeStorageKey, nextTheme);
        } catch {
            //
        }

        applyTheme(nextTheme);
        window.dispatchEvent(new Event(themeChangeEvent));
    }

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            aria-label={`Switch to ${nextTheme} mode`}
            title={`Switch to ${nextTheme} mode`}
            onClick={toggleTheme}
        >
            {isDark ? <RiSunLine /> : <RiMoonLine />}
        </Button>
    );
}
