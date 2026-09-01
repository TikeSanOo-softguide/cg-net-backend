import { FormEvent, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import {
    MapIcon,
    MapPinIcon,
    TagIcon,
} from 'lucide-react';

import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
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
import {
    type StateRow,
    type RegionRow,
    type AreaRow,
    type RegionType,
} from '@/components/region/RegionFormDialog';

const STATE_TITLE_MAX_LENGTH = 255;

export type RegionFormValues = {
    name_en: string;
    name_my: string;
    name_zh: string;
    state_id: number | null;
    region_id: number | null;
};

type RegionFormProps = {
    type: RegionType;

    item:
        | StateRow
        | RegionRow
        | AreaRow
        | null;

    states: StateRow[];
    regions: RegionRow[];

    initialValues: RegionFormValues;

    onClose: () => void;
};

export function RegionForm({
    type,
    item,
    states,
    regions,
    initialValues,
    onClose,
}: RegionFormProps) {
    const { t, locale } = useTranslation();

    const [submitted, setSubmitted] = useState(false);

    const form = useForm<RegionFormValues>(
        initialValues,
    );

    const filteredRegions = useMemo(() => {
        if (!form.data.state_id) {
            return [];
        }

        return regions.filter(
            (region) =>
                region.state_id ===
                Number(form.data.state_id),
        );
    }, [form.data.state_id, regions]);

    const getName = (
        row: StateRow | RegionRow,
    ) => {
        switch (locale) {
            case 'my':
                return (
                    row.name_my ??
                    row.name_en
                );

            case 'zh':
                return (
                    row.name_zh ??
                    row.name_en
                );

            default:
                return row.name_en;
        }
    };

    /*
     * ============================================================
     * VALIDATION
     * ============================================================
     */

    const getRequiredError = (
        field:
            | 'name_en'
            | 'name_my'
            | 'name_zh',
    ) => {
        if (!submitted) {
            return form.errors[field];
        }

        if (!form.data[field].trim()) {
            return t(
                `regions.validation.${field}_required`,
            );
        }

        return form.errors[field];
    };

    const stateError =
        submitted && type !== 'state' && !form.data.state_id
            ? t('regions.validation.state_required')
            : form.errors.state_id;

    const regionError =
        submitted &&
        type === 'area' &&
        !form.data.region_id
            ? t('regions.validation.region_required')
            : form.errors.region_id;

    const validate = () => {
        let valid = true;

        if (!form.data.name_en.trim()) {
            valid = false;
        }

        if (!form.data.name_my.trim()) {
            valid = false;
        }

        if (!form.data.name_zh.trim()) {
            valid = false;
        }

        if (type !== 'state' && !form.data.state_id) {
            valid = false;
        }

        if (type === 'area' && !form.data.region_id) {
            valid = false;
        }

        return valid;
    };

    /*
     * ============================================================
     * SUBMIT
     * ============================================================
     */

    const submit = (event: FormEvent) => {
        event.preventDefault();

        setSubmitted(true);

        if (!validate()) {
            return;
        }

        form.clearErrors(
            'name_en',
            'name_my',
            'name_zh',
            'state_id',
            'region_id',
        );

        if (type === 'state') {
            if (item) {
                form.transform((data) => ({
                    ...data,
                    _method: 'put',
                }));

                form.post(
                    `/regions/states/${item.id}`,
                    {
                        preserveScroll: true,
                        onSuccess: onClose,
                    },
                );

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

                form.post(
                    `/regions/regions/${item.id}`,
                    {
                        preserveScroll: true,
                        onSuccess: onClose,
                    },
                );

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

            form.post(
                `/regions/areas/${item.id}`,
                {
                    preserveScroll: true,
                    onSuccess: onClose,
                },
            );

            return;
        }

        form.post('/regions/areas', {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    /*
     * ============================================================
     * FIELD CHANGES
     * ============================================================
     */

    const handleNameChange = (
        field:
            | 'name_en'
            | 'name_my'
            | 'name_zh',
        value: string,
    ) => {
        form.setData(field, value);

        if (value.trim()) {
            form.clearErrors(field);
        }
    };

    const handleStateChange = (
        value: string,
    ) => {
        const stateId = Number(value);

        form.setData((data) => ({
            ...data,
            state_id: stateId,
            region_id:
                type === 'area'
                    ? null
                    : data.region_id,
        }));

        form.clearErrors('state_id');

        if (type === 'area') {
            form.clearErrors('region_id');
        }
    };

    const handleRegionChange = (
        value: string,
    ) => {
        form.setData(
            'region_id',
            Number(value),
        );

        form.clearErrors('region_id');
    };

    return (
        <CmsFormShell
            onSubmit={submit}
            onCancel={onClose}
            processing={form.processing}
            mode={item ? 'edit' : 'create'}
        >
            <FormField
                label={t('regions.name_en')}
                htmlFor="name_en"
                error={getRequiredError('name_en')}
                icon={TagIcon}
                required
                className="sm:col-span-2"
            >
                <Input
                    id="name_en"
                    name="name_en"
                    value={form.data.name_en}
                    onChange={(event) =>
                        handleNameChange(
                            'name_en',
                            event.target.value,
                        )
                    }
                    maxLength={
                        STATE_TITLE_MAX_LENGTH
                    }
                    disabled={form.processing}
                />
            </FormField>

            <FormField
                label={t('regions.name_my')}
                htmlFor="name_my"
                error={getRequiredError('name_my')}
                icon={TagIcon}
                required
                className="sm:col-span-2"
            >
                <Input
                    id="name_my"
                    name="name_my"
                    value={form.data.name_my}
                    onChange={(event) =>
                        handleNameChange(
                            'name_my',
                            event.target.value,
                        )
                    }
                    maxLength={
                        STATE_TITLE_MAX_LENGTH
                    }
                    disabled={form.processing}
                />
            </FormField>

            <FormField
                label={t('regions.name_zh')}
                htmlFor="name_zh"
                error={getRequiredError('name_zh')}
                icon={TagIcon}
                required
                className="sm:col-span-2"
            >
                <Input
                    id="name_zh"
                    name="name_zh"
                    value={form.data.name_zh}
                    onChange={(event) =>
                        handleNameChange(
                            'name_zh',
                            event.target.value,
                        )
                    }
                    maxLength={
                        STATE_TITLE_MAX_LENGTH
                    }
                    disabled={form.processing}
                />
            </FormField>

            {type !== 'state' && (
                <FormField
                    label={t('regions.state_name')}
                    htmlFor="state_id"
                    error={stateError}
                    icon={MapPinIcon}
                    required
                    className="sm:col-span-2"
                >
                    <Select
                        value={
                            form.data.state_id
                                ? String(
                                      form.data.state_id,
                                  )
                                : ''
                        }
                        onValueChange={
                            handleStateChange
                        }
                        disabled={
                            form.processing
                        }
                    >
                        <SelectTrigger id="state_id">
                            <SelectValue
                                placeholder={t(
                                    'regions.select_state',
                                )}
                            />
                        </SelectTrigger>

                        <SelectContent>
                            {states.map(
                                (state) => (
                                    <SelectItem
                                        key={state.id}
                                        value={String(
                                            state.id,
                                        )}
                                    >
                                        {getName(
                                            state,
                                        )}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </FormField>
            )}

            {type === 'area' && (
                <FormField
                    label={t('regions.region_name')}
                    htmlFor="region_id"
                    error={regionError}
                    icon={MapIcon}
                    required
                    className="sm:col-span-2"
                >
                    <Select
                        value={
                            form.data.region_id
                                ? String(
                                      form.data.region_id,
                                  )
                                : ''
                        }
                        onValueChange={
                            handleRegionChange
                        }
                        disabled={
                            form.processing ||
                            !form.data.state_id
                        }
                    >
                        <SelectTrigger id="region_id">
                            <SelectValue
                                placeholder={t(
                                    'regions.select_region',
                                )}
                            />
                        </SelectTrigger>

                        <SelectContent>
                            {filteredRegions.map(
                                (region) => (
                                    <SelectItem
                                        key={region.id}
                                        value={String(
                                            region.id,
                                        )}
                                    >
                                        {getName(
                                            region,
                                        )}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </FormField>
            )}
        </CmsFormShell>
    );
}