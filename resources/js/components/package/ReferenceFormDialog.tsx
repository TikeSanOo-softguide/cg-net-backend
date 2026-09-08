import { type FormEvent, useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { CableIcon, GaugeIcon, Layers3Icon, PlusIcon, TagIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormDialog } from '@/components/FormDialog';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import { SquareImageUpload } from '@/components/ui/square-image-upload';

export type ReferenceFormKind = 'network' | 'speed' | 'term' | 'addon';

export type ReferenceFormRow = {
    id: number;
    name?: string | null;
    name_en?: string | null;
    name_zh?: string | null;
    name_my?: string | null;
    mbps?: number | null;
    months?: number | null;
    price?: number | string | null;
    image_url?: string | null;
};

type ReferenceFormValues = Record<string, string | number | File | null>;

type ReferenceFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    kind: ReferenceFormKind;
    item: ReferenceFormRow | null;
};

const kindConfig: Record<
    ReferenceFormKind,
    {
        route: string;
        icon: typeof CableIcon;
    }
> = {
    network: {
        route: '/networks',
        icon: CableIcon,
    },

    speed: {
        route: '/speeds',
        icon: GaugeIcon,
    },

    term: {
        route: '/terms',
        icon: Layers3Icon,
    },

    addon: {
        route: '/addons',
        icon: TagIcon,
    },
};

const descriptionKeys: Record<ReferenceFormKind, string> = {
    network: 'packages.networks.description',
    speed: 'packages.speeds.description',
    term: 'packages.terms.description',
    addon: 'packages.addons.description',
};

const labelKeys: Record<ReferenceFormKind, string> = {
    network: 'packages.networks.title',
    speed: 'packages.speeds.title',
    term: 'packages.terms.title',
    addon: 'packages.addons.title',
};

const emptyValues = (kind: ReferenceFormKind): ReferenceFormValues => {
    switch (kind) {
        case 'network':
            return {
                name_en: '',
                name_zh: '',
                name_my: '',
            };

        case 'speed':
            return {
                mbps: '',
            };

        case 'term':
            return {
                months: '',
            };

        case 'addon':
            return {
                name_en: '',
                name_zh: '',
                name_my: '',
                price: '',
                image_url: null,
            };
    }
};

const getFormValues = (kind: ReferenceFormKind, item: ReferenceFormRow | null): ReferenceFormValues => {
    if (!item) {
        return emptyValues(kind);
    }

    switch (kind) {
        case 'network':
            return {
                name_en: item.name_en ?? '',
                name_zh: item.name_zh ?? '',
                name_my: item.name_my ?? '',
            };

        case 'speed':
            return {
                mbps: item.mbps ?? '',
            };

        case 'term':
            return {
                months: item.months ?? '',
            };

        case 'addon':
            return {
                name_en: item.name_en ?? '',
                name_zh: item.name_zh ?? '',
                name_my: item.name_my ?? '',
                price: item.price ?? '',
                image_url: null,
            };
    }
};

const getValidationError = (
    error: string | undefined,
    field: string,
    t: (key: string) => string,
): string | undefined => {
    if (!error) {
        return undefined;
    }

    const validationKey = (rule: string) => {
        const keyField = field === 'image_url' ? 'image' : field;
        return t(`packages.validation.${keyField}_${rule}`);
    };

    if (error.includes('required')) {
        return validationKey('required');
    }

    if (error.includes('string')) {
        return validationKey('string');
    }

    if (error.includes('integer')) {
        return validationKey('integer');
    }

    if (error.includes('numeric') || error.includes('number')) {
        return validationKey('numeric');
    }

    if (
        error.includes('max:50') ||
        ((field.startsWith('name_') || field.startsWith('network_name_')) &&
            (error.includes('50') || error.includes('255')))
    ) {
        return validationKey('max');
    }

    if (error.includes('100')) {
        return validationKey('max');
    }

    if (error.includes('min:0') || error.includes('at least 0')) {
        return validationKey('min');
    }

    if (field === 'image_url') {
        if (error.includes('5120') || error.includes('kilobytes')) {
            return validationKey('max_size');
        }

        if (error.includes('image') || error.includes('file')) {
            return validationKey('invalid_type');
        }
    }

    return error;
};

export function ReferenceFormDialog({ open, onOpenChange, kind, item }: ReferenceFormDialogProps) {
    const { t } = useTranslation();

    const isEdit = item !== null;
    const config = kindConfig[kind];

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? `${t('common.edit')} ${t(labelKeys[kind])}` : `${t('common.create')} ${t(labelKeys[kind])}`}
            description={t(descriptionKeys[kind])}
            icon={isEdit ? config.icon : PlusIcon}
        >
            {open ? (
                <ReferenceFormDialogBody
                    key={`${kind}-${item?.id ?? 'create'}`}
                    kind={kind}
                    item={item}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function ReferenceFormDialogBody({
    kind,
    item,
    onClose,
}: {
    kind: ReferenceFormKind;
    item: ReferenceFormRow | null;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const [image, setImage] = useState<File | null>(null);
    const isEdit = item !== null;
    const config = kindConfig[kind];
    const form = useForm<ReferenceFormValues>(getFormValues(kind, item));

    useEffect(() => {
        if (!item) {
            form.clearErrors();
        }
    }, [item]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isEdit && item) {
            form.transform((data) => ({
                ...data,
                _method: 'PUT',
            }));
            form.post(`${config.route}/${item.id}`, {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => {
                    onClose();
                },
            });

            return;
        }

        form.post(config.route, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                onClose();
            },
        });
    };

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                {kind === 'network' ? (
                    <div className="space-y-4">
                        <FormField
                            label={t('common.name_en')}
                            htmlFor="network-name-en"
                            error={getValidationError(form.errors.name_en, 'network_name_en', t)}
                        >
                            <Input
                                id="network-name-en"
                                value={String(form.data.name_en ?? '')}
                                onChange={(event) => form.setData('name_en', event.target.value)}
                                placeholder={t('packages.name_en_placeholder')}
                            />
                        </FormField>

                        <FormField
                            label={t('common.name_zh')}
                            htmlFor="network-name-zh"
                            error={getValidationError(form.errors.name_zh, 'network_name_zh', t)}
                        >
                            <Input
                                id="network-name-zh"
                                value={String(form.data.name_zh ?? '')}
                                onChange={(event) => form.setData('name_zh', event.target.value)}
                                placeholder={t('packages.name_zh_placeholder')}
                            />
                        </FormField>

                        <FormField
                            label={t('common.name_my')}
                            htmlFor="network-name-my"
                            error={getValidationError(form.errors.name_my, 'network_name_my', t)}
                        >
                            <Input
                                id="network-name-my"
                                value={String(form.data.name_my ?? '')}
                                onChange={(event) => form.setData('name_my', event.target.value)}
                                placeholder={t('packages.name_my_placeholder')}
                            />
                        </FormField>
                    </div>
                ) : null}

                {kind === 'speed' ? (
                    <FormField
                        label="Mbps"
                        htmlFor="speed-mbps"
                        error={getValidationError(form.errors.mbps, 'mbps', t)}
                    >
                        <Input
                            id="speed-mbps"
                            type="number"
                            min="1"
                            value={String(form.data.mbps ?? '')}
                            onChange={(event) => form.setData('mbps', event.target.value)}
                            placeholder="Mbps"
                        />
                    </FormField>
                ) : null}

                {kind === 'term' ? (
                    <FormField
                        label={t('packages.months')}
                        htmlFor="term-months"
                        error={getValidationError(form.errors.months, 'months', t)}
                    >
                        <Input
                            id="term-months"
                            type="number"
                            min="1"
                            value={String(form.data.months ?? '')}
                            onChange={(event) => form.setData('months', event.target.value)}
                            placeholder={t('packages.months_placeholder')}
                        />
                    </FormField>
                ) : null}

                {kind === 'addon' ? (
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField
                                label={t('common.name_en')}
                                htmlFor="addon-name-en"
                                error={getValidationError(form.errors.name_en, 'name_en', t)}
                            >
                                <Input
                                    id="addon-name-en"
                                    value={String(form.data.name_en ?? '')}
                                    onChange={(event) => form.setData('name_en', event.target.value)}
                                    placeholder={t('packages.name_en_placeholder')}
                                />
                            </FormField>

                            <FormField
                                label={t('common.name_zh')}
                                htmlFor="addon-name-zh"
                                error={getValidationError(form.errors.name_zh, 'name_zh', t)}
                            >
                                <Input
                                    id="addon-name-zh"
                                    value={String(form.data.name_zh ?? '')}
                                    onChange={(event) => form.setData('name_zh', event.target.value)}
                                    placeholder={t('packages.name_zh_placeholder')}
                                />
                            </FormField>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField
                                label={t('common.name_my')}
                                htmlFor="addon-name-my"
                                error={getValidationError(form.errors.name_my, 'name_my', t)}
                            >
                                <Input
                                    id="addon-name-my"
                                    value={String(form.data.name_my ?? '')}
                                    onChange={(event) => form.setData('name_my', event.target.value)}
                                    placeholder={t('packages.name_my_placeholder')}
                                />
                            </FormField>

                            <FormField
                                label={t('packages.price')}
                                htmlFor="addon-price"
                                error={getValidationError(form.errors.price, 'price', t)}
                            >
                                <Input
                                    id="addon-price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={String(form.data.price ?? '')}
                                    onChange={(event) => form.setData('price', event.target.value)}
                                    placeholder={t('packages.price_placeholder')}
                                />
                            </FormField>
                        </div>
                        <FormField
                            label={t('cms.image')}
                            htmlFor="addon-image"
                            error={getValidationError(form.errors.image_url, 'image_url', t)}
                        >
                            <SquareImageUpload
                                id="addon-image"
                                width={520}
                                height={150}
                                value={image}
                                existingUrl={item?.image_url ?? null}
                                onChange={(file) => {
                                    setImage(file);

                                    if (file) {
                                        form.setData('image_url', file);
                                    } else {
                                        form.setData('image_url', null);
                                    }
                                    form.clearErrors('image_url');
                                }}
                            />
                        </FormField>
                    </div>
                ) : null}
            </div>

            <FormActionBar
                mode={isEdit ? 'edit' : 'create'}
                onCancel={onClose}
                submitLabel={isEdit ? t('common.update') : t('common.submit')}
                processing={form.processing}
            />
        </form>
    );
}
