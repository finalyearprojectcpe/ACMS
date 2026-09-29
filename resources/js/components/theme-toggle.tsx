import { RiMoonLine, RiSunLine } from '@remixicon/react';

import { Button } from '@/components/ui/button';
import { setAppTheme, useAppTheme } from '@/hooks/use-app-theme';

export function ThemeToggle() {
    const [, resolvedTheme] = useAppTheme();
    const isDark = resolvedTheme === 'dark';
    const nextTheme = isDark ? 'light' : 'dark';

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            aria-label={`Switch to ${nextTheme} mode`}
            title={`Switch to ${nextTheme} mode`}
            onClick={() => setAppTheme(nextTheme)}
        >
            {isDark ? <RiSunLine /> : <RiMoonLine />}
        </Button>
    );
}
