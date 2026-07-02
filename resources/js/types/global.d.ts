import type { SchoolContext } from '@/types/academic';
import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            school: SchoolContext | null;
            flash: {
                success?: string | null;
                error?: string | null;
            };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
