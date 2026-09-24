import { type FormEvent, useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { CableIcon, GaugeIcon, Layers3Icon, PlusIcon, TagIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormDialog } from '@/components/FormDialog';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import { SquareImageUpload } from '@/components/ui/square-image-upload';
import { PACKAGE_IMAGE_WIDTH, PACKAGE_IMAGE_HEIGHT } from '@/lib/package-validation';
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
                mbps: 0,
            };

        case 'term':
            return {
                months: 0,
            };

        case 'addon':
            return {
                name_en: '',
                name_zh: '',
                name_my: '',
                price: 0,
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

    if (error.includes('unique') || error.toLowerCase().includes('already been taken')) {
        return validationKey('unique');
    }

    if (error.includes('string')) {
        return validationKey('string');
    }

    if (error.includes('integer')) {
        return validationKey('required');
    }

    if (error.includes('numeric') || error.includes('number')) {
        return validationKey('required');
    }

    if (
        error.includes('max:50') ||
        ((field.startsWith('name_') || field.startsWith('network_name_')) &&
            (error.includes('50') || error.includes('255')))
    ) {
        return validationKey('max');
    }

    if (error.includes('50')) {
        return t(`packages.validation.network_${field}_max`);
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

    if (field === 'price') {
        if (error.includes('9999999999') || error.includes('kilobytes')) {
            return validationKey('max');
        }
    }

    if (field === 'month' || field === 'mbps') {
        if (error.includes('999') || error.includes('kilobytes')) {
            return validationKey('max');
        }
    }

    return error;
};

const validateDigitNumber = (
    value: string,
    field: 'months' | 'mbps' | 'price',
    t: (key: string) => string,
): string | undefined => {
    if (!value.trim()) {
        return t(`packages.validation.${field}_required`);
    }

    if (!/^\d+$/.test(value)) {
        return t(`packages.validation.${field}_integer`);
    }

    if (Number(value) < 0) {
        return t(`packages.validation.${field}_min`);
    }

    if ((field === 'months' || field === 'mbps') && value.length > 3) {
        return t(`packages.validation.${field}_max`);
    }

    if (field === 'price' && value.length > 10) {
        return t(`packages.validation.${field}_max`);
    }

    return undefined;
};

const validateName = (
    value: string,
    field: 'name_en' | 'name_my' | 'name_zh',
    t: (key: string) => string,
): string | undefined => {
    if (!value.trim()) {
        return t(`packages.validation.${field}_required`);
    }

    if (value.length > 255) {
        return t(`packages.validation.${field}_max`);
    }

    return undefined;
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
    const [touched, setTouched] = useState({
        name_en: false,
        name_zh: false,
        name_my: false,
        months: false,
        mbps: false,
        price: false,
    });
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
                            error={
                                getValidationError(form.errors.name_en, 'name_en', t) ??
                                (touched.name_en
                                    ? validateName(String(form.data.name_en ?? ''), 'name_en', t)
                                    : undefined)
                            }
                        >
                            <Input
                                id="network-name-en"
                                value={String(form.data.name_en ?? '')}
                                onChange={(event) => {
                                    setTouched((prev) => ({
                                        ...prev,
                                        name_en: true,
                                    }));
                                    form.setData('name_en', event.target.value);
                                    form.clearErrors('name_en');
                                }}
                                placeholder={t('packages.name_en_placeholder')}
                            />
                        </FormField>

                        <FormField
                            label={t('common.name_zh')}
                            htmlFor="network-name-zh"
                            error={
                                getValidationError(form.errors.name_zh, 'name_zh', t) ??
                                (touched.name_zh
                                    ? validateName(String(form.data.name_zh ?? ''), 'name_zh', t)
                                    : undefined)
                            }
                        >
                            <Input
                                id="network-name-zh"
                                value={String(form.data.name_zh ?? '')}
                                onChange={(event) => {
                                    setTouched((prev) => ({
                                        ...prev,
                                        name_zh: true,
                                    }));
                                    form.setData('name_zh', event.target.value);
                                    form.clearErrors('name_zh');
                                }}
                                placeholder={t('packages.name_zh_placeholder')}
                            />
                        </FormField>

                        <FormField
                            label={t('common.name_my')}
                            htmlFor="network-name-my"
                            error={
                                getValidationError(form.errors.name_my, 'name_my', t) ??
                                (touched.name_my
                                    ? validateName(String(form.data.name_my ?? ''), 'name_my', t)
                                    : undefined)
                            }
                        >
                            <Input
                                id="network-name-my"
                                value={String(form.data.name_my ?? '')}
                                onChange={(event) => {
                                    setTouched((prev) => ({
                                        ...prev,
                                        name_my: true,
                                    }));
                                    form.setData('name_my', event.target.value);
                                    form.clearErrors('name_my');
                                }}
                                placeholder={t('packages.name_my_placeholder')}
                            />
                        </FormField>
                    </div>
                ) : null}

                {kind === 'speed' ? (
                    <FormField
                        label="Mbps"
                        htmlFor="speed-mbps"
                        error={
                            getValidationError(form.errors.mbps, 'mbps', t) ??
                            (touched.mbps ? validateDigitNumber(String(form.data.mbps ?? ''), 'mbps', t) : undefined)
                        }
                    >
                        <Input
                            id="speed-mbps"
                            type="number"
                            value={String(form.data.mbps ?? '')}
                            onKeyDown={(event) => {
                                if (['e', 'E', '+', '-'].includes(event.key)) {
                                    event.preventDefault();
                                }
                            }}
                            onChange={(event) => {
                                const value = event.target.value;
                                if (value === '' || Number(value) >= 0) {
                                    setTouched((prev) => ({
                                        ...prev,
                                        mbps: true,
                                    }));
                                    form.setData('mbps', value);
                                    form.clearErrors('mbps');
                                }
                            }}
                            placeholder="Mbps"
                        />
                    </FormField>
                ) : null}

                {kind === 'term' ? (
                    <FormField
                        label={t('packages.months')}
                        htmlFor="term-months"
                        error={
                            getValidationError(form.errors.months, 'months', t) ??
                            (touched.months
                                ? validateDigitNumber(String(form.data.months ?? ''), 'months', t)
                                : undefined)
                        }
                    >
                        <Input
                            id="term-months"
                            type="number"
                            value={String(form.data.months ?? '')}
                            onKeyDown={(event) => {
                                if (['e', 'E', '+', '-'].includes(event.key)) {
                                    event.preventDefault();
                                }
                            }}
                            onChange={(event) => {
                                const value = event.target.value;
                                if (value === '' || Number(value) >= 0) {
                                    setTouched((prev) => ({
                                        ...prev,
                                        months: true,
                                    }));
                                    form.setData('months', value);
                                    form.clearErrors('months');
                                }
                            }}
                            placeholder={t('packages.months_placeholder')}
                        />
                    </FormField>
                ) : null}

                {kind === 'addon' ? (
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <FormField
                                    label={t('common.name_en')}
                                    htmlFor="addon-name-en"
                                    className="py-2"
                                    error={
                                        getValidationError(form.errors.name_en, 'name_en', t) ??
                                        (touched.name_en
                                            ? validateName(String(form.data.name_en ?? ''), 'name_en', t)
                                            : undefined)
                                    }
                                >
                                    <Input
                                        id="addon-name-en"
                                        value={String(form.data.name_en ?? '')}
                                        onChange={(event) => {
                                            setTouched((prev) => ({
                                                ...prev,
                                                name_en: true,
                                            }));
                                            form.setData('name_en', event.target.value);
                                            form.clearErrors('name_en');
                                        }}
                                        placeholder={t('packages.name_en_placeholder')}
                                    />
                                </FormField>

                                <FormField
                                    label={t('common.name_zh')}
                                    htmlFor="addon-name-zh"
                                    className="py-2"
                                    error={
                                        getValidationError(form.errors.name_zh, 'name_zh', t) ??
                                        (touched.name_zh
                                            ? validateName(String(form.data.name_zh ?? ''), 'name_zh', t)
                                            : undefined)
                                    }
                                >
                                    <Input
                                        id="addon-name-zh"
                                        value={String(form.data.name_zh ?? '')}
                                        onChange={(event) => {
                                            setTouched((prev) => ({
                                                ...prev,
                                                name_zh: true,
                                            }));
                                            form.setData('name_zh', event.target.value);
                                            form.clearErrors('name_zh');
                                        }}
                                        placeholder={t('packages.name_zh_placeholder')}
                                    />
                                </FormField>
                                <FormField
                                    label={t('common.name_my')}
                                    htmlFor="addon-name-my"
                                    className="py-2"
                                    error={
                                        getValidationError(form.errors.name_my, 'name_my', t) ??
                                        (touched.name_my
                                            ? validateName(String(form.data.name_my ?? ''), 'name_my', t)
                                            : undefined)
                                    }
                                >
                                    <Input
                                        id="addon-name-my"
                                        value={String(form.data.name_my ?? '')}
                                        className="py-2"
                                        onChange={(event) => {
                                            setTouched((prev) => ({
                                                ...prev,
                                                name_my: true,
                                            }));
                                            form.setData('name_my', event.target.value);
                                            form.clearErrors('name_my');
                                        }}
                                        placeholder={t('packages.name_my_placeholder')}
                                    />
                                </FormField>

                                <FormField
                                    label={t('packages.price')}
                                    htmlFor="addon-price"
                                    className="py-2"
                                    error={
                                        getValidationError(form.errors.price, 'price', t) ??
                                        (touched.price
                                            ? validateDigitNumber(String(form.data.price ?? ''), 'price', t)
                                            : undefined)
                                    }
                                >
                                    <Input
                                        id="addon-price"
                                        type="number"
                                        value={String(form.data.price ?? '')}
                                        onKeyDown={(event) => {
                                            if (['e', 'E', '+', '-'].includes(event.key)) {
                                                event.preventDefault();
                                            }
                                        }}
                                        onChange={(event) => {
                                            const value = event.target.value;

                                            if (value === '' || Number(value) >= 0) {
                                                setTouched((prev) => ({
                                                    ...prev,
                                                    price: true,
                                                }));
                                                form.setData('price', Number(value));
                                                form.clearErrors('price');
                                            }
                                        }}
                                        placeholder={t('packages.price_placeholder')}
                                    />
                                </FormField>
                            </div>
                            <div>
                                <FormField
                                    label={t('cms.image')}
                                    htmlFor="addon-image"
                                    error={getValidationError(form.errors.image_url, 'image_url', t)}
                                >
                                    <SquareImageUpload
                                        id="addon-image"
                                        width={PACKAGE_IMAGE_WIDTH}
                                        height={PACKAGE_IMAGE_HEIGHT}
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
                        </div>
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
