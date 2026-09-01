import { type FormEvent, useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import {
    CableIcon,
    GaugeIcon,
    Layers3Icon,
    PlusIcon,
    TagIcon,
} from 'lucide-react';

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

// type ReferenceFormValues = Record<string, string | number>;
type ReferenceFormValues = Record<
    string,
    string | number | File | null
>;

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
        label: string;
        description: string;
        icon: typeof CableIcon;
    }
> = {
    network: {
        route: '/networks',
        label: 'Network',
        description: 'Create or update a network',
        icon: CableIcon,
    },

    speed: {
        route: '/speeds',
        label: 'Speed',
        description: 'Create or update a speed tier',
        icon: GaugeIcon,
    },

    term: {
        route: '/terms',
        label: 'Term',
        description: 'Create or update a contract term',
        icon: Layers3Icon,
    },

    addon: {
        route: '/addons',
        label: 'Addon',
        description: 'Create or update an addon',
        icon: TagIcon,
    },
};

const emptyValues = (
    kind: ReferenceFormKind,
): ReferenceFormValues => {
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
                image_url: '',
            };
    }
};

const getFormValues = (
    kind: ReferenceFormKind,
    item: ReferenceFormRow | null,
): ReferenceFormValues => {
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
                image_url: item.image_url ?? '',
            };
    }
};

export function ReferenceFormDialog({
    open,
    onOpenChange,
    kind,
    item,
}: ReferenceFormDialogProps) {
    const { t } = useTranslation();

    const isEdit = item !== null;
    const config = kindConfig[kind];

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={
                isEdit
                    ? `${t('common.edit')} ${config.label}`
                    : `${t('common.create')} ${config.label}`
            }
            description={config.description}
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

    const form = useForm<ReferenceFormValues>(
        getFormValues(kind, item),
    );

    useEffect(() => {
        if (!item) {
            form.clearErrors();
        }
    }, [item]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isEdit && item) {
            form.put(`${config.route}/${item.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    onClose();
                },
            });

            return;
        }

        form.post(config.route, {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
            },
        });
    };

    return (
        <form
            onSubmit={submit}
            className="flex min-h-0 flex-1 flex-col"
        >
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                {/* NETWORK */}
                {kind === 'network' ? (
                    <div className="space-y-4">
                        <FormField
                            label="Name (EN)"
                            htmlFor="network-name-en"
                            error={form.errors.name_en}
                        >
                            <Input
                                id="network-name-en"
                                value={String(
                                    form.data.name_en ?? '',
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'name_en',
                                        event.target.value,
                                    )
                                }
                                placeholder="English name"
                            />
                        </FormField>

                        <FormField
                            label="Name (ZH)"
                            htmlFor="network-name-zh"
                            error={form.errors.name_zh}
                        >
                            <Input
                                id="network-name-zh"
                                value={String(
                                    form.data.name_zh ?? '',
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'name_zh',
                                        event.target.value,
                                    )
                                }
                                placeholder="Chinese name"
                            />
                        </FormField>

                        <FormField
                            label="Name (MY)"
                            htmlFor="network-name-my"
                            error={form.errors.name_my}
                        >
                            <Input
                                id="network-name-my"
                                value={String(
                                    form.data.name_my ?? '',
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'name_my',
                                        event.target.value,
                                    )
                                }
                                placeholder="Myanmar name"
                            />
                        </FormField>
                    </div>
                ) : null}

                {/* SPEED */}
                {kind === 'speed' ? (
                    <FormField
                        label="Mbps"
                        htmlFor="speed-mbps"
                        error={form.errors.mbps}
                    >
                        <Input
                            id="speed-mbps"
                            type="number"
                            min="1"
                            value={String(
                                form.data.mbps ?? '',
                            )}
                            onChange={(event) =>
                                form.setData(
                                    'mbps',
                                    event.target.value,
                                )
                            }
                            placeholder="Mbps"
                        />
                    </FormField>
                ) : null}

                {/* TERM */}
                {kind === 'term' ? (
                    <FormField
                        label="Months"
                        htmlFor="term-months"
                        error={form.errors.months}
                    >
                        <Input
                            id="term-months"
                            type="number"
                            min="1"
                            value={String(
                                form.data.months ?? '',
                            )}
                            onChange={(event) =>
                                form.setData(
                                    'months',
                                    event.target.value,
                                )
                            }
                            placeholder="Months"
                        />
                    </FormField>
                ) : null}

                {/* ADDON */}
                {kind === 'addon' ? (
                    <div className="space-y-4">
                        <FormField
                            label="Name (EN)"
                            htmlFor="addon-name-en"
                            error={form.errors.name_en}
                        >
                            <Input
                                id="addon-name-en"
                                value={String(
                                    form.data.name_en ?? '',
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'name_en',
                                        event.target.value,
                                    )
                                }
                                placeholder="English name"
                            />
                        </FormField>

                        <FormField
                            label="Name (ZH)"
                            htmlFor="addon-name-zh"
                            error={form.errors.name_zh}
                        >
                            <Input
                                id="addon-name-zh"
                                value={String(
                                    form.data.name_zh ?? '',
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'name_zh',
                                        event.target.value,
                                    )
                                }
                                placeholder="Chinese name"
                            />
                        </FormField>

                        <FormField
                            label="Name (MY)"
                            htmlFor="addon-name-my"
                            error={form.errors.name_my}
                        >
                            <Input
                                id="addon-name-my"
                                value={String(
                                    form.data.name_my ?? '',
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'name_my',
                                        event.target.value,
                                    )
                                }
                                placeholder="Myanmar name"
                            />
                        </FormField>

                        <FormField
                            label="Price"
                            htmlFor="addon-price"
                            error={form.errors.price}
                        >
                            <Input
                                id="addon-price"
                                type="number"
                                min="0"
                                step="0.01"
                                value={String(
                                    form.data.price ?? '',
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'price',
                                        event.target.value,
                                    )
                                }
                                placeholder="0.00"
                            />
                        </FormField>
                          <FormField
            label="Image"
            htmlFor="addon-image"
            error={form.errors.image_url}
        >
            {/* <SquareImageUpload
                id="addon-image"
                width={520}
                height={150}
                value={image}
                existingUrl={
                    typeof form.data.image_url === 'string'
                        ? form.data.image_url
                        : null
                }
                onChange={(file) => {
                    setImage(file);
                    form.setData(
                        'image_url',
                        file ?? '',
                    );
                    form.clearErrors('image_url');
                }}
            /> */}
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
                submitLabel={
                    isEdit
                        ? t('common.update')
                        : t('common.submit')
                }
                processing={form.processing}
            />
        </form>
    );
}