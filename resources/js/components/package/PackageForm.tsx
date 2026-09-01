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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { SquareImageUpload } from '../ui/square-image-upload';
import { useState } from 'react';

export type PackageOption = {
    id: number;
    name: string;
    name_en: string;
    name_zh: string;
    name_my: string;
    mbps: number;
    months:number;
};

export type PackageFormValues = {
    network_id: number | '';
    speed_id: number | '';
    term_id: number | '';
    price: string;
    image_url: File | string;
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
        price: '',
        image_url: '',
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
    onSubmit: (event: FormEvent) => void;
    mode?: 'create' | 'edit';
    submitLabel?: string;
    onCancel?: () => void;
    existingImageUrl?: string | null;
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
    const { t,locale } = useTranslation();
    const [image, setImage] = useState<File | null>(null);

    return (
        <form
            onSubmit={onSubmit}
            className="flex min-h-0 flex-1 flex-col"
        >
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <FormField
                        label={t('package.network')}
                        htmlFor="network_id"
                        error={form.errors.network_id}
                        icon={NetworkIcon}
                    >
                        <Select
                            value={
                                form.data.network_id
                                    ? String(form.data.network_id)
                                    : ''
                            }
                            onValueChange={(value) =>
                                form.setData('network_id', Number(value))
                            }
                        >
                            <SelectTrigger
                                id="network_id"
                                className="w-full"
                                aria-invalid={Boolean(
                                    form.errors.network_id,
                                )}
                            >
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                {networks.map((network) => (
                                    <SelectItem
                                        key={network.id}
                                        value={String(network.id)}
                                    >
                                      {network[`name_${locale}`] ?? '—'}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        label={t('package.speed')}
                        htmlFor="speed_id"
                        error={form.errors.speed_id}
                        icon={ZapIcon}
                    >
                        <Select
                            value={
                                form.data.speed_id
                                    ? String(form.data.speed_id)
                                    : ''
                            }
                            onValueChange={(value) =>
                                form.setData('speed_id', Number(value))
                            }
                        >
                            <SelectTrigger
                                id="speed_id"
                                className="w-full"
                                aria-invalid={Boolean(
                                    form.errors.speed_id,
                                )}
                            >
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                {speeds.map((speed) => (
                                    <SelectItem
                                        key={speed.id}
                                        value={String(speed.id)}
                                    >
                                        {speed.mbps}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        label={t('package.term')}
                        htmlFor="term_id"
                        error={form.errors.term_id}
                        icon={RouterIcon}
                    >
                        <Select
                            value={
                                form.data.term_id
                                    ? String(form.data.term_id)
                                    : ''
                            }
                            onValueChange={(value) =>
                                form.setData('term_id', Number(value))
                            }
                        >
                            <SelectTrigger
                                id="term_id"
                                className="w-full"
                                aria-invalid={Boolean(
                                    form.errors.term_id,
                                )}
                            >
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                {terms.map((term) => (
                                    <SelectItem
                                        key={term.id}
                                        value={String(term.id)}
                                    >
                                        {term.months}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        label={t('package.price')}
                        htmlFor="price"
                        error={form.errors.price}
                        icon={DollarSignIcon}
                    >
                        <Input
                            id="price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.price}
                            onChange={(event) =>
                                form.setData(
                                    'price',
                                    event.target.value,
                                )
                            }
                            required
                            aria-invalid={Boolean(form.errors.price)}
                        />
                    </FormField>

                    <FormField
                        label={t('package.installation_fee')}
                        htmlFor="installation_fee"
                        error={form.errors.installation_fee}
                        icon={DollarSignIcon}
                    >
                        <Input
                            id="installation_fee"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.installation_fee}
                            onChange={(event) =>
                                form.setData(
                                    'installation_fee',
                                    event.target.value,
                                )
                            }
                            required
                            aria-invalid={Boolean(
                                form.errors.installation_fee,
                            )}
                        />
                    </FormField>

                    <FormField
                        label={t('package.sort_order')}
                        htmlFor="sort_order"
                        error={form.errors.sort_order}
                        icon={PercentIcon}
                    >
                        <Input
                            id="sort_order"
                            type="number"
                            min="0"
                            value={form.data.sort_order}
                            onChange={(event) =>
                                form.setData(
                                    'sort_order',
                                    event.target.value === ''
                                        ? ''
                                        : Number(event.target.value),
                                )
                            }
                            required
                            aria-invalid={Boolean(
                                form.errors.sort_order,
                            )}
                        />
                    </FormField>

                   <FormField
                        label={t('package.image')}
                        htmlFor="package-image"
                        error={form.errors.image_url}
                        className="sm:col-span-2"
                    >
                        <SquareImageUpload
                            id="package-image"
                            width={520}
                            height={150}
                            value={image}
                            existingUrl={existingImageUrl}
                            onChange={(file) => {
                            setImage(file);
                            form.setData('image_url', file ?? '');
                            form.clearErrors('image_url');
                        }}
                        />
                    </FormField>
                    
                    <FormField
                        label={t('package.free_iptv')}
                        htmlFor="includes_free_iptv"
                        error={form.errors.includes_free_iptv}
                        icon={PackageIcon}
                    >
                        <Select
                            value={
                                form.data.includes_free_iptv
                                    ? 'yes'
                                    : 'no'
                            }
                            onValueChange={(value) =>
                                form.setData(
                                    'includes_free_iptv',
                                    value === 'yes',
                                )
                            }
                        >
                            <SelectTrigger
                                id="includes_free_iptv"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="yes">
                                    {t('common.yes')}
                                </SelectItem>
                                <SelectItem value="no">
                                    {t('common.no')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        label={t('package.recommended')}
                        htmlFor="recommended"
                        error={form.errors.recommended}
                        icon={StarIcon}
                    >
                        <Select
                            value={
                                form.data.recommended
                                    ? 'yes'
                                    : 'no'
                            }
                            onValueChange={(value) =>
                                form.setData(
                                    'recommended',
                                    value === 'yes',
                                )
                            }
                        >
                            <SelectTrigger
                                id="recommended"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="yes">
                                    {t('common.yes')}
                                </SelectItem>
                                <SelectItem value="no">
                                    {t('common.no')}
                                </SelectItem>
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
                            value={
                                form.data.is_active
                                    ? 'active'
                                    : 'inactive'
                            }
                            onValueChange={(value) =>
                                form.setData(
                                    'is_active',
                                    value === 'active',
                                )
                            }
                        >
                            <SelectTrigger
                                id="is_active"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="active">
                                    {t('status.active')}
                                </SelectItem>
                                <SelectItem value="inactive">
                                    {t('status.inactive')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                </div>
            </div>

            <FormActionBar
                mode={mode}
                onCancel={onCancel}
                processing={form.processing}
                submitLabel={submitLabel}
            />
        </form>
    );
}