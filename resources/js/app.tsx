import { createInertiaApp } from '@inertiajs/react';

import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    withApp: (app) => (
        <TooltipProvider>
            {app}
            <Toaster closeButton richColors />
        </TooltipProvider>
    ),
    progress: {
        color: '#4B5563',
    },
});
