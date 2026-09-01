import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    BanIcon,
    CalendarIcon,
    CircleDotIcon,
    DatabaseIcon,
    GaugeIcon,
    PackageIcon,
    SquarePenIcon,
    UserCheckIcon,
} from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { DetailPanel } from '@/components/DetailPanel';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

type Package = {
    id: number;
    name: string;
    speed: {
        id: number;
        name: string;
        mbps: number;
    } | null;
    data_gb: number | null;
    price: string;
    status: string;
    created_at: string | null;
};

type CustomerRow = {
    id: number;
    name: string;
    phone: string;
    account_number: string | null;
    status: string;
};

type PackageShowProps = {
    package: Package;
    customers: CustomerRow[];
};

function formatMmk(value: string): string {
    return `${Number(value).toLocaleString()} MMK`;
}

export default function PackageShow({
    package: packageData,
    customers,
}: PackageShowProps) {
    const { t } = useTranslation();

    const [statusOpen, setStatusOpen] = useState(false);
    const [statusProcessing, setStatusProcessing] = useState(false);

    const nextStatus =
        packageData.status === 'active' ? 'inactive' : 'active';

    return (
        <>
            <Head title={packageData.name} />

            <PageContent className="pb-24 sm:pb-8">
                <PageHeader
                    title={packageData.name}
                    description={t('package.details')}
                />

                <div className="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,560px)_minmax(0,1fr)]">
                    <DetailPanel
                        title={t('package.details')}
                        description={t('package.details_description')}
                        icon={PackageIcon}
                        actions={
                            <div className="hidden items-center gap-2 sm:flex">
                                <StatusBadge status={packageData.status} />

                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.get(
                                            `/packages/${packageData.id}/edit`,
                                        )
                                    }
                                >
                                    <SquarePenIcon />
                                    {t('common.edit')}
                                </Button>
                            </div>
                        }
                        footer={
                            <>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="h-8 w-[120px] rounded-[4px] sm:hidden"
                                    onClick={() =>
                                        router.get(
                                            `/packages/${packageData.id}/edit`,
                                        )
                                    }
                                >
                                    <SquarePenIcon
                                        className="size-3.5"
                                        strokeWidth={1.85}
                                    />
                                    {t('common.edit')}
                                </Button>

                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        packageData.status === 'active'
                                            ? 'destructive'
                                            : 'primary'
                                    }
                                    className="h-8 min-w-[120px] rounded-[4px]"
                                    onClick={() => setStatusOpen(true)}
                                >
                                    {packageData.status === 'active' ? (
                                        <BanIcon
                                            className="size-3.5"
                                            strokeWidth={1.85}
                                        />
                                    ) : (
                                        <UserCheckIcon
                                            className="size-3.5"
                                            strokeWidth={1.85}
                                        />
                                    )}

                                    {packageData.status === 'active'
                                        ? t('package.deactivate')
                                        : t('package.activate')}
                                </Button>
                            </>
                        }
                    >
                        <FormField
                            label={t('package.name')}
                            htmlFor="detail-name"
                            icon={PackageIcon}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="detail-name"
                                value={packageData.name}
                                readOnly
                            />
                        </FormField>

                        <FormField
                            label={t('package.speed')}
                            htmlFor="detail-speed"
                            icon={GaugeIcon}
                        >
                            <Input
                                id="detail-speed"
                                value={
                                    packageData.speed
                                        ? `${packageData.speed.mbps} Mbps`
                                        : '—'
                                }
                                readOnly
                            />
                        </FormField>

                        <FormField
                            label={t('package.data')}
                            htmlFor="detail-data"
                            icon={DatabaseIcon}
                        >
                            <Input
                                id="detail-data"
                                value={
                                    packageData.data_gb !== null
                                        ? `${packageData.data_gb} GB`
                                        : '—'
                                }
                                readOnly
                            />
                        </FormField>

                        <FormField
                            label={t('package.price')}
                            htmlFor="detail-price"
                            icon={PackageIcon}
                        >
                            <Input
                                id="detail-price"
                                value={formatMmk(packageData.price)}
                                readOnly
                            />
                        </FormField>

                        <FormField
                            label={t('common.status')}
                            htmlFor="detail-status"
                            icon={CircleDotIcon}
                        >
                            <div
                                id="detail-status"
                                className="flex h-10 items-center"
                            >
                                <StatusBadge
                                    status={packageData.status}
                                />
                            </div>
                        </FormField>

                        <FormField
                            label={t('package.created_at')}
                            htmlFor="detail-created"
                            icon={CalendarIcon}
                        >
                            <Input
                                id="detail-created"
                                value={packageData.created_at ?? '—'}
                                readOnly
                            />
                        </FormField>
                    </DetailPanel>

                    <DetailPanel
                        title={t('package.customers')}
                        description={t('package.customers_description')}
                        icon={UserCheckIcon}
                    >
                        <DataTable
                            title={t('package.customers')}
                            data={customers}
                            getRowId={(row) => String(row.id)}
                            searchPlaceholder={t(
                                'package.search_customers',
                            )}
                            emptyLabel={t('package.no_customers')}
                            columns={[
                                {
                                    id: 'name',
                                    header: t('customers.name'),
                                    mobile: 'title',
                                    searchValue: (row) => row.name,
                                    cell: (row) => row.name,
                                },
                                {
                                    id: 'phone',
                                    header: t('customers.phone'),
                                    mobile: 'subtitle',
                                    searchValue: (row) => row.phone,
                                    cell: (row) => row.phone,
                                },
                                {
                                    id: 'account_number',
                                    header: t(
                                        'customers.account_number',
                                    ),
                                    className: 'font-mono text-[12px]',
                                    mobile: 'meta',
                                    searchValue: (row) =>
                                        row.account_number ?? '',
                                    cell: (row) =>
                                        row.account_number ?? '—',
                                },
                                {
                                    id: 'status',
                                    header: t('common.status'),
                                    mobile: 'badge',
                                    searchValue: (row) =>
                                        t(`status.${row.status}`),
                                    cell: (row) => (
                                        <StatusBadge
                                            status={row.status}
                                        />
                                    ),
                                },
                            ]}
                        />
                    </DetailPanel>
                </div>
            </PageContent>

            <ConfirmDialog
                open={statusOpen}
                onOpenChange={setStatusOpen}
                title={
                    packageData.status === 'active'
                        ? t('package.deactivate_title')
                        : t('package.activate_title')
                }
                description={
                    packageData.status === 'active'
                        ? t('package.deactivate_description')
                        : t('package.activate_description')
                }
                confirmLabel={
                    packageData.status === 'active'
                        ? t('package.deactivate')
                        : t('package.activate')
                }
                destructive={packageData.status === 'active'}
                processing={statusProcessing}
                onConfirm={() => {
                    router.patch(
                        `/packages/${packageData.id}/status`,
                        { status: nextStatus },
                        {
                            preserveScroll: true,
                            onStart: () =>
                                setStatusProcessing(true),
                            onFinish: () =>
                                setStatusProcessing(false),
                            onSuccess: () =>
                                setStatusOpen(false),
                        },
                    );
                }}
            />
        </>
    );
}
