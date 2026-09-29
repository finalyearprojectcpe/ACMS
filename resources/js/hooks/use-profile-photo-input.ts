import { useEffect, useRef, useState } from 'react';

export function useProfilePhotoInput(initialAvatar: string | null) {
    const [file, setFile] = useState<File | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    function select(nextFile: File | undefined) {
        if (!nextFile) {
            return;
        }

        setFile(nextFile);
        setPreviewUrl(URL.createObjectURL(nextFile));
    }

    function reset() {
        setFile(null);
        setPreviewUrl(null);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }

    return {
        file,
        inputRef,
        preview: file ? previewUrl : initialAvatar,
        reset,
        select,
    };
}
