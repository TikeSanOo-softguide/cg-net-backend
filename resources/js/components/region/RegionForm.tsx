import { FormEvent, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { MapIcon, MapPinIcon, TagIcon } from 'lucide-react';

import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

import { useTranslation } from '@/hooks/useTranslation';
import { formControlStateClass } from '@/lib/form-control';
import { validateRegion, validateRegionField, type ExistingRegion } from '@/lib/region-validation';

import { type StateRow, type RegionRow, type AreaRow, type RegionType } from '@/components/region/RegionFormDialog';

export type RegionFormValues = {
    name_en: string;
    name_my: string;
    name_zh: string;
    latitude: string | null;
    longitude: string | null;
    state_id: number | null;
    region_id: number | null;
};

type RegionFormProps = {
    type: RegionType;
    item: StateRow | RegionRow | AreaRow | null;
    states: StateRow[];
    regions: RegionRow[];
    areas?: AreaRow[];
    initialValues: RegionFormValues;
    onClose: () => void;
};

export function RegionForm({ type, item, states, regions, areas = [], initialValues, onClose }: RegionFormProps) {
    const { t, locale } = useTranslation();

    const [submitted, setSubmitted] = useState(false);
    const [touched, setTouched] = useState<Record<keyof RegionFormValues, boolean>>({
        name_en: false,
        name_my: false,
        name_zh: false,
        latitude: false,
        longitude: false,
        state_id: false,
        region_id: false,
    });

    const form = useForm<RegionFormValues>(initialValues);

    const existingRecords = useMemo<ExistingRegion[]>(() => {
        switch (type) {
            case 'state':
                return states;

            case 'region':
                return regions;

            case 'area':
                return areas;

            default:
                return [];
        }
    }, [type, states, regions, areas]);

    const filteredRegions = useMemo(() => {
        if (!form.data.state_id) {
            return [];
        }

        return regions.filter((region) => region.state_id === Number(form.data.state_id));
    }, [form.data.state_id, regions]);

    const getName = (row: StateRow | RegionRow) => {
        switch (locale) {
            case 'my':
                return row.name_my ?? row.name_en;

            case 'zh':
                return row.name_zh ?? row.name_en;

            default:
                return row.name_en;
        }
    };

    const markTouched = (field: keyof RegionFormValues) => {
        setTouched((current) => ({
            ...current,
            [field]: true,
        }));
    };

    const setField = <K extends keyof RegionFormValues>(field: K, value: RegionFormValues[K]) => {
        form.setData(field, value as never);
        form.clearErrors(field);
    };

    const getValidationError = (field: keyof RegionFormValues) => {
        return validateRegionField(field, form.data, t, type, existingRecords, item?.id);
    };

    const fieldState = (field: keyof RegionFormValues): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) {
            return 'idle';
        }

        return form.errors[field] || getValidationError(field) ? 'error' : 'success';
    };

    const fieldError = (field: keyof RegionFormValues): string | undefined => {
        if (!touched[field] && !submitted) {
            return undefined;
        }

        return form.errors[field] || getValidationError(field);
    };

    const validate = () => {
        const errors = validateRegion(form.data, t, type, existingRecords, item?.id);

        if (Object.keys(errors).length > 0) {
            form.setError(errors);
            return false;
        }

        return true;
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSubmitted(true);
        setTouched({
            name_en: true,
            name_my: true,
            name_zh: true,
            latitude: true,
            longitude: true,
            state_id: true,
            region_id: true,
        });

        if (!validate()) {
            return;
        }

        form.clearErrors('name_en', 'name_my', 'name_zh', 'latitude', 'longitude', 'state_id', 'region_id');

        if (type === 'state') {
            if (item) {
                form.transform((data) => ({
                    ...data,
                    _method: 'put',
                }));

                form.post(`/regions/states/${item.id}`, {
                    preserveScroll: true,
                    onSuccess: onClose,
                });

                return;
            }

            form.post('/regions/states', {
                preserveScroll: true,
                onSuccess: onClose,
            });

            return;
        }

        if (type === 'region') {
            if (item) {
                form.transform((data) => ({
                    ...data,
                    _method: 'put',
                }));

                form.post(`/regions/regions/${item.id}`, {
                    preserveScroll: true,
                    onSuccess: onClose,
                });

                return;
            }

            form.post('/regions/regions', {
                preserveScroll: true,
                onSuccess: onClose,
            });

            return;
        }

        if (item) {
            form.transform((data) => ({
                ...data,
                _method: 'put',
            }));

            form.post(`/regions/areas/${item.id}`, {
                preserveScroll: true,
                onSuccess: onClose,
            });

            return;
        }

        form.post('/regions/areas', {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const handleNameChange = (field: 'name_en' | 'name_my' | 'name_zh', value: string) => {
        form.setData(field, value);

        if (value.trim()) {
            form.clearErrors(field);
        }
    };

    const handleStateChange = (value: string) => {
        const stateId = Number(value);

        form.setData((data) => ({
            ...data,
            state_id: stateId,
            region_id: type === 'area' ? null : data.region_id,
        }));

        form.clearErrors('state_id');

        markTouched('state_id');

        if (type === 'area') {
            form.clearErrors('region_id');
            markTouched('region_id');
        }
    };

    const handleCoordinateChange = (field: 'latitude' | 'longitude', value: string) => {
        setField(field, value === '' ? null : value);
        markTouched(field);
    };

    const handleRegionChange = (value: string) => {
        setField('region_id', Number(value));
        markTouched('region_id');
    };

    return (
        <CmsFormShell onSubmit={submit} onCancel={onClose} processing={form.processing} mode={item ? 'edit' : 'create'}>
            <FormField
                label={t('regions.name_en')}
                htmlFor="name_en"
                error={fieldError('name_en')}
                icon={TagIcon}
                required
                className="sm:col-span-2"
            >
                <Input
                    id="name_en"
                    name="name_en"
                    value={form.data.name_en}
                    aria-invalid={fieldState('name_en') === 'error'}
                    className={formControlStateClass(fieldState('name_en'))}
                    onBlur={() => markTouched('name_en')}
                    onChange={(event) => handleNameChange('name_en', event.target.value)}
                    disabled={form.processing}
                />
            </FormField>

            <FormField
                label={t('regions.name_my')}
                htmlFor="name_my"
                error={fieldError('name_my')}
                icon={TagIcon}
                required
                className="sm:col-span-2"
            >
                <Input
                    id="name_my"
                    name="name_my"
                    value={form.data.name_my}
                    aria-invalid={fieldState('name_my') === 'error'}
                    className={formControlStateClass(fieldState('name_my'))}
                    onBlur={() => markTouched('name_my')}
                    onChange={(event) => handleNameChange('name_my', event.target.value)}
                    disabled={form.processing}
                />
            </FormField>

            <FormField
                label={t('regions.name_zh')}
                htmlFor="name_zh"
                error={fieldError('name_zh')}
                icon={TagIcon}
                required
                className="sm:col-span-2"
            >
                <Input
                    id="name_zh"
                    name="name_zh"
                    value={form.data.name_zh}
                    aria-invalid={fieldState('name_zh') === 'error'}
                    className={formControlStateClass(fieldState('name_zh'))}
                    onBlur={() => markTouched('name_zh')}
                    onChange={(event) => handleNameChange('name_zh', event.target.value)}
                    disabled={form.processing}
                />
            </FormField>

            <FormField
                label={t('regions.latitude')}
                htmlFor="latitude"
                error={fieldError('latitude')}
                icon={MapPinIcon}
                className="sm:col-span-1"
            >
                <Input
                    id="latitude"
                    name="latitude"
                    type="number"
                    step="any"
                    min="-90"
                    max="90"
                    value={form.data.latitude ?? ''}
                    aria-invalid={fieldState('latitude') === 'error'}
                    className={formControlStateClass(fieldState('latitude'))}
                    onBlur={() => markTouched('latitude')}
                    onChange={(event) => handleCoordinateChange('latitude', event.target.value)}
                    disabled={form.processing}
                />
            </FormField>

            <FormField
                label={t('regions.longitude')}
                htmlFor="longitude"
                error={fieldError('longitude')}
                icon={MapPinIcon}
                className="sm:col-span-1"
            >
                <Input
                    id="longitude"
                    name="longitude"
                    type="number"
                    step="any"
                    min="-180"
                    max="180"
                    value={form.data.longitude ?? ''}
                    aria-invalid={fieldState('longitude') === 'error'}
                    className={formControlStateClass(fieldState('longitude'))}
                    onBlur={() => markTouched('longitude')}
                    onChange={(event) => handleCoordinateChange('longitude', event.target.value)}
                    disabled={form.processing}
                />
            </FormField>

            {type !== 'state' && (
                <FormField
                    label={t('regions.state_name')}
                    htmlFor="state_id"
                    error={fieldError('state_id')}
                    icon={MapPinIcon}
                    required
                    className="sm:col-span-2"
                >
                    <Select
                        value={form.data.state_id ? String(form.data.state_id) : ''}
                        onValueChange={handleStateChange}
                        disabled={form.processing}
                    >
                        <SelectTrigger
                            id="state_id"
                            aria-invalid={fieldState('state_id') === 'error'}
                            className={formControlStateClass(fieldState('state_id'))}
                        >
                            <SelectValue placeholder={t('regions.select_state')} />
                        </SelectTrigger>

                        <SelectContent>
                            {states.map((state) => (
                                <SelectItem key={state.id} value={String(state.id)}>
                                    {getName(state)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
            )}

            {type === 'area' && (
                <FormField
                    label={t('regions.region_name')}
                    htmlFor="region_id"
                    error={fieldError('region_id')}
                    icon={MapIcon}
                    required
                    className="sm:col-span-2"
                >
                    <Select
                        value={form.data.region_id ? String(form.data.region_id) : ''}
                        onValueChange={handleRegionChange}
                        disabled={form.processing || !form.data.state_id}
                    >
                        <SelectTrigger
                            id="region_id"
                            aria-invalid={fieldState('region_id') === 'error'}
                            className={formControlStateClass(fieldState('region_id'))}
                        >
                            <SelectValue placeholder={t('regions.select_region')} />
                        </SelectTrigger>

                        <SelectContent>
                            {filteredRegions.map((region) => (
                                <SelectItem key={region.id} value={String(region.id)}>
                                    {getName(region)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
            )}
        </CmsFormShell>
    );
}
