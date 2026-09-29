import { usePoll } from '@inertiajs/react';
import { useEffect } from 'react';

export function useReceiptWorkPolling(hasPendingWork: boolean): void {
    const { start, stop } = usePoll(3000, {}, { autoStart: false });

    useEffect(() => {
        if (hasPendingWork) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [hasPendingWork, start, stop]);
}
