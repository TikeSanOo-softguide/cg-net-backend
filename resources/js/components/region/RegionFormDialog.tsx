import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { MapIcon, MapPinIcon, SquareStackIcon, SquarePenIcon } from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import { RegionForm, type RegionFormValues } from '@/components/region/RegionForm';
import { cmsModalVisit } from '@/lib/cms-modal';
import { useTranslation } from '@/hooks/useTranslation';

export type StateRow = {
    id: number;
    name_en: string;
    name_zh: string;
    name_my: string;
    latitude: string | null;
    longitude: string | null;
    created_at: string | null;
};

export type RegionRow = {
    id: number;
    name_en: string;
    name_zh: string;
    name_my: string;
    state_id: number;
    latitude: string | null;
    longitude: string | null;
    state_name_en: string | null;
    state_name_zh: string | null;
    state_name_my: string | null;
    created_at: string | null;
};

export type AreaRow = {
    id: number;
    name_en: string;
    name_zh: string;
    name_my: string;
    region_id: number;
    latitude: string | null;
    longitude: string | null;
    region_name_en: string | null;
    region_name_zh: string | null;
    region_name_my: string | null;
    state_name_en: string | null;
    state_name_zh: string | null;
    state_name_my: string | null;
    created_at: string | null;
};

export type RegionType = 'state' | 'region' | 'area';

type RegionFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;

    type: RegionType;

    item: StateRow | RegionRow | AreaRow | null;

    states: StateRow[];
    regions: RegionRow[];
    areas: AreaRow[];
};

function getTitleKey(type: RegionType, edit: boolean): string {
    if (type === 'state') {
        return edit ? 'regions.edit_state' : 'regions.create_state';
    }

    if (type === 'region') {
        return edit ? 'regions.edit_region' : 'regions.create_region';
    }

    return edit ? 'regions.edit_area' : 'regions.create_area';
}

function getDescriptionKey(type: RegionType, edit: boolean): string {
    if (type === 'state') {
        return edit ? 'regions.edit_state_description' : 'regions.create_state_description';
    }

    if (type === 'region') {
        return edit ? 'regions.edit_region_description' : 'regions.create_region_description';
    }

    return edit ? 'regions.edit_area_description' : 'regions.create_area_description';
}

function getIcon(type: RegionType, edit: boolean) {
    if (edit) {
        return SquarePenIcon;
    }

    switch (type) {
        case 'state':
            return MapPinIcon;

        case 'region':
            return MapIcon;

        case 'area':
            return SquareStackIcon;
    }
}

export function RegionFormDialog({ open, onOpenChange, type, item, states, regions, areas }: RegionFormDialogProps) {
    const { t } = useTranslation();

    const isEdit = item !== null;

    const initialValues: RegionFormValues = {
        name_en: item?.name_en ?? '',
        name_my: item?.name_my ?? '',
        name_zh: item?.name_zh ?? '',
        latitude: item?.latitude ?? null,
        longitude: item?.longitude ?? null,
        state_id: null,
        region_id: null,
    };

    if (type === 'region' && item) {
        const region = item as RegionRow;

        initialValues.state_id = region.state_id;
    }

    if (type === 'area' && item) {
        const area = item as AreaRow;

        initialValues.region_id = area.region_id;

        const parentRegion = regions.find((region) => region.id === area.region_id);

        initialValues.state_id = parentRegion?.state_id ?? null;
    }

    const Icon = getIcon(type, isEdit);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t(getTitleKey(type, isEdit))}
            description={t(getDescriptionKey(type, isEdit))}
            icon={Icon}
        >
            {open ? (
                <RegionFormDialogBody
                    key={item ? `${type}-edit-${item.id}` : `${type}-create`}
                    type={type}
                    item={item}
                    states={states}
                    regions={regions}
                    areas={areas}
                    initialValues={initialValues}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

type RegionFormDialogBodyProps = {
    type: RegionType;
    item: StateRow | RegionRow | AreaRow | null;
    states: StateRow[];
    regions: RegionRow[];
    areas: AreaRow[];
    initialValues: RegionFormValues;
    onClose: () => void;
};

function RegionFormDialogBody({
    type,
    item,
    states,
    regions,
    areas,
    initialValues,
    onClose,
}: RegionFormDialogBodyProps) {
    const form = useForm<RegionFormValues>(initialValues);

    const isEdit = item !== null;
    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            ...cmsModalVisit,
            onSuccess: onClose,
        };

        if (type === 'state') {
            if (isEdit && item) {
                form.put(`/regions/states/${item.id}`, options);

                return;
            }

            form.post('/regions/states', options);

            return;
        }

        if (type === 'region') {
            if (isEdit && item) {
                form.put(`/regions/regions/${item.id}`, options);

                return;
            }

            form.post('/regions/regions', options);

            return;
        }

        if (isEdit && item) {
            form.put(`/regions/areas/${item.id}`, options);

            return;
        }

        form.post('/regions/areas', options);
    };

    return (
        <RegionForm
            form={form}
            onSubmit={submit}
            type={type}
            item={item}
            states={states}
            regions={regions}
            areas={areas}
            onClose={onClose}
            mode={isEdit ? 'edit' : 'create'}
        />
    );
}
