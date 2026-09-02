import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import {
    CircleDotIcon,
    DollarSignIcon,
    ImageIcon,
    NetworkIcon,
    PackageIcon,
    PercentIcon,
    RouterIcon,
    StarIcon,
    ZapIcon,
} from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { SquareImageUpload } from '../ui/square-image-upload';
import { useState } from 'react';
import {
    PACKAGE_IMAGE_WIDTH,
    PACKAGE_IMAGE_HEIGHT,
    validatePackageImageFile,
    validatePackageField,
    validatePackage,
} from '@/lib/package-validation';
import { cn } from '@/lib/utils';
import { formControlStateClass } from '@/lib/form-control';

export type PackageOption = {
    id: number;
    name: string;
    name_en: string;
    name_zh: string;
    name_my: string;
    mbps: number;
    months: number;
};

export type PackageFormValues = {
    network_id: number | '';
    speed_id: number | '';
    term_id: number | '';
    price: string;
    image_url: File | null;
    installation_fee: string;
    includes_free_iptv: boolean;
    is_active: boolean;
    sort_order: number | '';
    recommended: boolean;
};

export function emptyPackageForm(): PackageFormValues {
    return {
        network_id: '',
        speed_id: '',
        term_id: '',
        price: '0',
        image_url: null,
        installation_fee: '0',
        includes_free_iptv: false,
        is_active: true,
        sort_order: 0,
        recommended: false,
    };
}

type PackageFormProps = {
    form: InertiaFormProps<PackageFormValues>;
    networks: PackageOption[];
    speeds: PackageOption[];
    terms: PackageOption[];
    mode?: 'create' | 'edit';
    submitLabel?: string;
    existingImageUrl?: string | null;
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
};

export function PackageForm({
    form,
    networks,
    speeds,
    terms,
    onSubmit,
    mode = 'create',
    submitLabel,
    onCancel,
    existingImageUrl,
}: PackageFormProps) {
    const { t, locale } = useTranslation();
    const [image, setImage] = useState<File | null>(null);
    const [touched, setTouched] = useState<Record<keyof PackageFormValues, boolean>>({
        network_id: false,
        speed_id: false,
        term_id: false,
        price: false,
        image_url: false,
        installation_fee: false,
        includes_free_iptv: false,
        is_active: false,
        sort_order: false,
        recommended: false,
    });
    const [submitted, setSubmitted] = useState(false);

    const markTouched = (field: keyof PackageFormValues) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const setField = <K extends keyof PackageFormValues>(field: K, value: PackageFormValues[K]) => {
        form.setData(field, value as never);
        form.clearErrors(field);
    };

    const fieldState = (field: keyof PackageFormValues): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) {
            return 'idle';
        }

        if (field === 'image_url' && mode === 'edit' && existingImageUrl && !form.data.image_url) {
            return form.errors.image_url ? 'error' : 'success';
        }

        return form.errors[field] || validatePackageField(field, form.data, t) ? 'error' : 'success';
    };

    const fieldError = (field: keyof PackageFormValues): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }

        if (field === 'image_url' && mode === 'edit' && existingImageUrl && !form.data.image_url) {
            return form.errors.image_url;
        }

        return form.errors[field] || validatePackageField(field, form.data, t);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        setSubmitted(true);

        setTouched({
            network_id: true,
            speed_id: true,
            term_id: true,
            price: true,
            image_url: true,
            installation_fee: true,
            includes_free_iptv: true,
            is_active: true,
            sort_order: true,
            recommended: true,
        });

        const errors = validatePackage(form.data, t);

        if (Object.keys(errors).length > 0) {
            form.setError(errors);
            return;
        }

        form.clearErrors();
        onSubmit(event);
    };
    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <FormField
                        label={t('packages.network')}
                        htmlFor="network_id"
                        error={fieldError('network_id')}
                        required
                        icon={NetworkIcon}
                    >
                        <Select
                            value={form.data.network_id === '' ? '' : String(form.data.network_id)}
                            onValueChange={(value) => {
                                setField('network_id', Number(value));
                                markTouched('network_id');
                            }}
                        >
                            <SelectTrigger
                                id="network_id"
                                className={cn('w-full', formControlStateClass(fieldState('network_id')))}
                                aria-invalid={Boolean(form.errors.network_id)}
                            >
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                {networks.map((network) => (
                                    <SelectItem key={network.id} value={String(network.id)}>
                                        {network[`name_${locale}`] ?? '—'}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        label={t('packages.speed')}
                        htmlFor="speed_id"
                        error={fieldError('speed_id')}
                        required
                        icon={ZapIcon}
                    >
                        <Select
                            value={form.data.speed_id === '' ? '' : String(form.data.speed_id)}
                            onValueChange={(value) => {
                                setField('speed_id', Number(value));
                                markTouched('speed_id');
                            }}
                        >
                            <SelectTrigger
                                id="speed_id"
                                className="w-full"
                                aria-invalid={Boolean(form.errors.speed_id)}
                            >
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                {speeds.map((speed) => (
                                    <SelectItem key={speed.id} value={String(speed.id)}>
                                        {speed.mbps} Mbps
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        label={t('packages.term')}
                        htmlFor="term_id"
                        error={fieldError('term_id')}
                        required
                        icon={RouterIcon}
                    >
                        <Select
                            value={form.data.term_id === '' ? '' : String(form.data.term_id)}
                            onValueChange={(value) => {
                                setField('term_id', Number(value));
                                markTouched('term_id');
                            }}
                        >
                            <SelectTrigger id="term_id" className="w-full" aria-invalid={Boolean(form.errors.term_id)}>
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                {terms.map((term) => (
                                    <SelectItem key={term.id} value={String(term.id)}>
                                        {term.months} {t('packages.months')}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        label={t('packages.price')}
                        htmlFor="price"
                        error={fieldError('price')}
                        required
                        icon={DollarSignIcon}
                    >
                        <Input
                            id="price"
                            type="number"
                            min="0"
                            step="0"
                            value={form.data.price}
                            onChange={(event) => form.setData('price', event.target.value)}
                            required
                            aria-invalid={Boolean(form.errors.price)}
                        />
                    </FormField>

                    <FormField
                        label={t('packages.installation_fee')}
                        htmlFor="installation_fee"
                        error={fieldError('installation_fee')}
                        required
                        icon={DollarSignIcon}
                    >
                        <Input
                            id="installation_fee"
                            type="number"
                            min="0"
                            step="0"
                            value={form.data.installation_fee}
                            onChange={(event) => form.setData('installation_fee', event.target.value)}
                            required
                            aria-invalid={Boolean(form.errors.installation_fee)}
                        />
                    </FormField>

                    <FormField
                        label={t('cms.sort_order')}
                        htmlFor="sort_order"
                        error={fieldError('sort_order')}
                        required
                        icon={PercentIcon}
                    >
                        <Input
                            id="sort_order"
                            type="number"
                            min="0"
                            value={form.data.sort_order}
                            onChange={(event) =>
                                form.setData('sort_order', event.target.value === '' ? '' : Number(event.target.value))
                            }
                            required
                            aria-invalid={Boolean(form.errors.sort_order)}
                        />
                    </FormField>

                    <FormField
                        label={t('packages.free_iptv')}
                        htmlFor="includes_free_iptv"
                        error={form.errors.includes_free_iptv}
                        icon={PackageIcon}
                    >
                        <Select
                            value={form.data.includes_free_iptv ? 'yes' : 'no'}
                            onValueChange={(value) => form.setData('includes_free_iptv', value === 'yes')}
                        >
                            <SelectTrigger id="includes_free_iptv" className="w-full">
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="yes">{t('packages.free')}</SelectItem>
                                <SelectItem value="no">{t('packages.paid')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        label={t('packages.recommended')}
                        htmlFor="recommended"
                        error={form.errors.recommended}
                        icon={StarIcon}
                    >
                        <Select
                            value={form.data.recommended ? 'yes' : 'no'}
                            onValueChange={(value) => form.setData('recommended', value === 'yes')}
                        >
                            <SelectTrigger id="recommended" className="w-full">
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="yes">{t('packages.yes')}</SelectItem>
                                <SelectItem value="no">{t('packages.no')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        label={t('common.status')}
                        htmlFor="is_active"
                        error={form.errors.is_active}
                        icon={CircleDotIcon}
                        className="sm:col-span-2"
                    >
                        <Select
                            value={form.data.is_active ? 'active' : 'inactive'}
                            onValueChange={(value) => form.setData('is_active', value === 'active')}
                        >
                            <SelectTrigger id="is_active" className="w-full">
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="active">{t('status.active')}</SelectItem>
                                <SelectItem value="inactive">{t('status.inactive')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        label={t('cms.image')}
                        htmlFor="package-image"
                        error={fieldError('image_url')}
                        className="sm:col-span-2"
                    >
                        <SquareImageUpload
                            id="package-image"
                            width={PACKAGE_IMAGE_WIDTH}
                            height={PACKAGE_IMAGE_HEIGHT}
                            value={image}
                            existingUrl={existingImageUrl}
                            onChange={(file) => {
                                setImage(file);
                                setField('image_url', file);
                                markTouched('image_url');
                            }}
                        />
                    </FormField>
                </div>
            </div>

            <FormActionBar mode={mode} onCancel={onCancel} processing={form.processing} submitLabel={submitLabel} />
        </form>
    );
}
