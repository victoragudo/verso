import { useState } from 'react';
import { toast } from 'sonner';
import { uploadMedia } from '@/components/verso/media/media-api';
import { t } from '@/lib/i18n';
import type { MediaLibraryItem } from '@/types';

// Uploads files one by one and reports errors as toasts.
export function useMediaUpload() {
    const [uploading, setUploading] = useState(0);

    const upload = async (files: FileList | File[]) => {
        const list = Array.from(files);
        const uploaded: MediaLibraryItem[] = [];

        setUploading(list.length);

        for (const file of list) {
            try {
                uploaded.push(await uploadMedia(file));
            } catch (error) {
                toast.error(
                    t('{file}: {error}', {
                        file: file.name,
                        error:
                            error instanceof Error
                                ? error.message
                                : t('Upload failed.'),
                    }),
                );
            } finally {
                setUploading((count) => Math.max(0, count - 1));
            }
        }

        if (uploaded.length > 0) {
            toast.success(
                t(
                    '{count, plural, one {File uploaded.} other {# files uploaded.}}',
                    { count: uploaded.length },
                ),
            );
        }

        return uploaded;
    };

    return { upload, uploading, isUploading: uploading > 0 };
}
