import { FormEvent, useState } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { TagIcon } from 'lucide-react';

import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { SquareImageUpload } from '@/components/ui/square-image-upload';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import {
    validateGallery,
    validateGalleryField,
} from '@/lib/gallery-validation';

export type GalleryFormValues = {
    label_en: string | null;
    label_my: string | null;
    label_zh: string | null;
    image: File | null;
};

type GalleryFormProps = {
    form: InertiaFormProps<GalleryFormValues>;
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    mode?: 'create' | 'edit';
    imageUrl?: string | null;
};

export function GalleryForm({
    form,
    onSubmit,
    onCancel,
    mode = 'create',
    imageUrl,
}: GalleryFormProps) {
    const { t } = useTranslation();

    const [dashedImage, setDashedImage] = useState<File | null>(null);
    const [imageTouched, setImageTouched] = useState(false);
    const [submitted, setSubmitted] = useState(false);

    const hasExistingImage = Boolean(imageUrl);

    const imageError = (): string | undefined => {
        if (!imageTouched && !submitted) {
            return undefined;
        }

        // Backend error has priority.
        if (form.errors.image) {
            return form.errors.image;
        }

        // On edit, an existing image satisfies the required rule.
        if (mode === 'edit' && hasExistingImage && !form.data.image) {
            return undefined;
        }

        return validateGalleryField(
            'image',
            form.data,
            t,
            mode,
            hasExistingImage,
        );
    };

    const handleImageChange = (file: File | null) => {
        setImageTouched(true);
        setDashedImage(file);

        form.setData('image', file);

        // Remove previous backend error immediately.
        form.clearErrors('image');

        // On edit, if the existing image is still present and
        // the user hasn't selected a new file, image is valid.
        if (mode === 'edit' && hasExistingImage && !file) {
            return;
        }

        const error = validateGalleryField(
            'image',
            {
                ...form.data,
                image: file,
            },
            t,
            mode,
            hasExistingImage,
        );

        if (error) {
            form.setError('image', error);
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        setSubmitted(true);
        setImageTouched(true);

        const errors = validateGallery(
            form.data,
            t,
            mode,
            hasExistingImage,
        );

        if (Object.keys(errors).length > 0) {
            form.setError(errors);
            return;
        }

        form.clearErrors();

        onSubmit(event);
    };

    return (
        <CmsFormShell
            onSubmit={submit}
            onCancel={onCancel}
            processing={form.processing}
            mode={mode}
        >
            <FormField
                label={t('cms.gallery.label_en')}
                htmlFor="label"
                error={form.errors.label_en}
                icon={TagIcon}
                className="sm:col-span-2"
            >
                <Input
                    id="label"
                    value={form.data.label_en ?? ''}
                    onChange={(event) =>
                        form.setData('label_en', event.target.value)
                    }
                />
            </FormField>

            <FormField
                label={t('cms.gallery.label_my')}
                htmlFor="label_my"
                error={form.errors.label_my}
                icon={TagIcon}
                className="sm:col-span-2"
            >
                <Input
                    id="label_my"
                    value={form.data.label_my ?? ''}
                    onChange={(event) =>
                        form.setData('label_my', event.target.value)
                    }
                />
            </FormField>

            <FormField
                label={t('cms.gallery.label_zh')}
                htmlFor="label_zh"
                error={form.errors.label_zh}
                icon={TagIcon}
                className="sm:col-span-2"
            >
                <Input
                    id="label_zh"
                    value={form.data.label_zh ?? ''}
                    onChange={(event) =>
                        form.setData('label_zh', event.target.value)
                    }
                />
            </FormField>

            <FormField
                label={t('cms.image')}
                htmlFor="dashboard-image-dashed"
                error={imageError()}
                className="sm:col-span-2"
                required
            >
                <SquareImageUpload
                    id="dashboard-image-dashed"
                    width={520}
                    height={150}
                    value={dashedImage}
                    existingUrl={imageUrl}
                    onChange={handleImageChange}
                />
            </FormField>
        </CmsFormShell>
    );
}
