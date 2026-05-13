import { createInertiaApp } from '@inertiajs/react';

import { TooltipProvider } from '@/components/ui/tooltip';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    withApp: (app) => <TooltipProvider>{app}</TooltipProvider>,
    progress: {
        color: '#4B5563',
    },
});
