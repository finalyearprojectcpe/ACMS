import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

type FlashMessages = {
    success?: string | null;
    error?: string | null;
};

export function FlashToasts() {
    const page = usePage<{ flash?: FlashMessages }>();
    const flash = page.props.flash;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error, flash?.success, page.url]);

    return null;
}
