import { MapPinIcon, MessageCircleIcon, PencilIcon, PhoneIcon, PlusIcon, SendIcon, Trash2Icon } from 'lucide-react';

import { DataTable } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { TableActionButton } from '@/components/TableActionButton';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';

type OfficeRow = {
    id: number;
    name_en: string;
    name_mm: string;
    name_zh: string;
    cd: string;
    address_en: string;
    address_mm: string;
    address_zh: string;
    phone_num1: string;
    phone_num2: string | null;
    phone_num3: string | null;
    telegram_link: string | null;
    wechat_link: string | null;
    viber_link: string | null;
    top_up_cards_count: number;
};

type TopUpCardOfficeTableProps = {
    offices: OfficeRow[];
    pagination?: Paginated<OfficeRow>;
    search: string;
    onSearchChange: (value: string) => void;
    onCreate: () => void;
    onEdit: (office: OfficeRow) => void;
    onDelete: (office: OfficeRow) => void;
};

function socialLink(href: string | null, label: string, Icon: typeof SendIcon) {
    if (!href) {
        return null;
    }

    const isSafeLink = /^(https?:\/\/|tg:\/\/|viber:\/\/|weixin:\/\/)/i.test(href);
    const content = (
        <>
            <Icon className="size-3.5" aria-hidden="true" />
            <span>{label}</span>
        </>
    );

    return isSafeLink ? (
        <a
            key={label}
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1.5 text-primary hover:underline"
            aria-label={`${label}: ${href}`}
        >
            {content}
        </a>
    ) : (
        <span key={label} className="inline-flex items-center gap-1.5" title={href}>
            {content}
        </span>
    );
}

export function TopUpCardOfficeTable({
    offices,
    pagination,
    search,
    onSearchChange,
    onCreate,
    onEdit,
    onDelete,
}: TopUpCardOfficeTableProps) {
    const { t, locale } = useTranslation();
    const can = useCan();
    const nameField = locale === 'my' ? 'name_mm' : locale === 'zh' ? 'name_zh' : 'name_en';
    const addressField = locale === 'my' ? 'address_mm' : locale === 'zh' ? 'address_zh' : 'address_en';

    return (
        <DataTable
            data={offices}
            pagination={pagination}
            getRowId={(row) => String(row.id)}
            search={search}
            onSearchChange={onSearchChange}
            searchPlaceholder={t('top_up_cards.office.search')}
            emptyLabel={t('top_up_cards.office.empty')}
            onCreate={can('top-up-cards.create') ? onCreate : undefined}
            createLabel={t('top_up_cards.office.add')}
            columns={[
                {
                    id: 'name',
                    header: t('top_up_cards.office.name'),
                    mobile: 'title',
                    className: 'font-medium',
                    cell: (row) => row[nameField],
                },
                { id: 'cd', header: t('top_up_cards.office.cd'), cell: (row) => row.cd ?? '-' },
                {
                    id: 'address',
                    header: t('top_up_cards.office.address'),
                    mobile: 'meta',
                    cell: (row) => (
                        <span className="inline-flex items-center gap-1.5">
                            <MapPinIcon className="size-3.5 text-muted-foreground" />
                            {row[addressField]}
                        </span>
                    ),
                },
                {
                    id: 'phone',
                    header: t('top_up_cards.office.phone'),
                    cell: (row) => [row.phone_num1, row.phone_num2, row.phone_num3].filter(Boolean).join(' / '),
                },
                {
                    id: 'social',
                    header: t('top_up_cards.office.social_links'),
                    cell: (row) => {
                        const links = [
                            socialLink(row.telegram_link, t('top_up_cards.office.telegram_link'), SendIcon),
                            socialLink(row.wechat_link, t('top_up_cards.office.wechat_link'), MessageCircleIcon),
                            socialLink(row.viber_link, t('top_up_cards.office.viber_link'), PhoneIcon),
                        ].filter((link) => link !== null);

                        return links.length > 0 ? <div className="flex flex-wrap gap-x-3 gap-y-1">{links}</div> : '-';
                    },
                },
                { id: 'cards', header: t('top_up_cards.office.assigned_cards'), cell: (row) => row.top_up_cards_count },
            ]}
            actions={(row) => (
                <>
                    {can('top-up-cards.update') ? (
                        <TableActionButton
                            label={t('common.edit')}
                            icon={PencilIcon}
                            tone="edit"
                            onClick={() => onEdit(row)}
                        />
                    ) : null}
                    {can('top-up-cards.delete') ? (
                        <TableActionButton
                            label={t('common.delete')}
                            icon={Trash2Icon}
                            tone="danger"
                            onClick={() => onDelete(row)}
                        />
                    ) : null}
                </>
            )}
        />
    );
}
