import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    CalendarClockIcon,
    CalendarIcon,
    CircleDotIcon,
    EyeIcon,
    FileTextIcon,
    MegaphoneIcon,
    PlusIcon,
    SquarePenIcon,
    Trash2Icon,
    XIcon,
} from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { FormDialog } from '@/components/FormDialog';
import { formActionBarClass, formActionButtonClass, formActionSubmitClass } from '@/components/FormActionBar';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { SearchInput } from '@/components/SearchInput';
import { StatusBadge } from '@/components/StatusBadge';
import { StaffStatusSwitch } from '@/components/staff/StaffStatusSwitch';
import { TableActionButton } from '@/components/TableActionButton';
import { Card } from '@/components/ui/card';
import { DateTimePicker } from '@/components/ui/date-time-picker';
import { Button } from '@/components/ui/button';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { ToolbarIconButton } from '@/components/data-table/toolbar';
import { EDGE_PAD } from '@/components/data-table/styles';
import {
    AnnouncementFormDialog,
    type AnnouncementItem,
} from '@/components/notification/announcement/AnnouncementFormDialog';
import type { Paginated } from '@/components/Pagination';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { cn, formatDateTime } from '@/lib/utils';

type Filters = {
    search: string;
};

type Props = {
    announcement: Paginated<AnnouncementItem>;
    filters: Filters;
};

function visitIndex(search: string) {
    router.get(
        '/notifications/announcement',
        { search: search || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function getStatus(item: AnnouncementItem) {
    if (!item.is_active) {
        return 'inactive';
    }

    // Active but the end date time has already passed → expired
    if (item.end_date) {
        const end = new Date(item.end_date);
        if (!Number.isNaN(end.getTime()) && end < new Date()) {
            return 'expired';
        }
    }

    return 'active';
}

export default function AnnouncementsIndex({ announcement, filters }: Props) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const [formOpen, setFormOpen] = useState(false);
    const [detailOpen, setDetailOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<AnnouncementItem | null>(null);
    const [viewingItem, setViewingItem] = useState<AnnouncementItem | null>(null);
    const debounce = useRef<number>(0);

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const getContent = (row: AnnouncementItem): string => {
        switch (locale) {
            case 'my':
                return row.content_my ?? row.content_en ?? '';

            case 'zh':
                return row.content_zh ?? row.content_en ?? '';

            default:
                return row.content_en ?? '';
        }
    };

    return (
        <>
            <Head title={t('menu.announcement')} />
            <PageContent>
                <PageHeader />
                <Card className="flex min-h-0 flex-col gap-0 overflow-hidden border-0 py-0 shadow-[0_4px_16px_rgb(23_50_54/0.06)] dark:shadow-[0_4px_16px_rgb(0_0_0/0.22)]">
                    <div className={cn('flex flex-col gap-2.5 py-3 sm:flex-row sm:items-center', EDGE_PAD)}>
                        <SearchInput
                            value={search}
                            onChange={(value) => {
                                setSearch(value);
                                window.clearTimeout(debounce.current);
                                debounce.current = window.setTimeout(() => visitIndex(value), 300);
                            }}
                            placeholder={t('common.search')}
                            size="sm"
                            className="w-full sm:max-w-64"
                        />
                        <div className="flex shrink-0 items-center justify-end sm:ms-auto">
                            <ToolbarIconButton
                                label={t('notification.announcement.create')}
                                icon={PlusIcon}
                                prominent
                                onClick={() => {
                                    setEditingItem(null);
                                    setFormOpen(true);
                                }}
                            />
                        </div>
                    </div>

                    <div className={cn(EDGE_PAD, 'grid gap-3 pb-4 grid-cols-1')}>
                        {announcement.data.length === 0 ? (
                            <p className="col-span-full py-12 text-center text-[13px] text-muted-foreground">
                                {t('common.no_results')}
                            </p>
                        ) : null}

                        {announcement.data.map((item) => (
                            <article
                                key={item.id}
                                className="flex items-start gap-2.5 rounded-[12px] border border-border/70 bg-white p-3 shadow-[0_2px_8px_rgb(23_50_54/0.06)] dark:bg-card dark:shadow-[0_2px_8px_rgb(0_0_0/0.22)] sm:p-4"
                            >
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-primary/12 text-primary">
                                    <MegaphoneIcon className="size-5" strokeWidth={1.8} />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-3">
                                        <StatusBadge status={getStatus(item)} />
                                        <h2 className="text-[15px] font-semibold text-primary">
                                            {item.start_date || item.end_date
                                                ? `${formatDateTime(item.start_date)} ~ ${formatDateTime(item.end_date)}`
                                                : '—'}
                                        </h2>
                                    </div>
                                    <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-foreground">
                                        {getContent(item)}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <TableActionButton
                                        label={t('common.view')}
                                        icon={EyeIcon}
                                        tone="primary"
                                        onClick={() => {
                                            setViewingItem(item);
                                            setDetailOpen(true);
                                        }}
                                    />
                                    <TableActionButton
                                        label={t('common.edit')}
                                        icon={SquarePenIcon}
                                        tone="edit"
                                        onClick={() => {
                                            setEditingItem(item);
                                            setFormOpen(true);
                                        }}
                                    />
                                    <TableActionButton
                                        label={t('common.delete')}
                                        icon={Trash2Icon}
                                        tone="danger"
                                        onClick={() => setPendingIds([item.id])}
                                    />
                                </div>
                            </article>
                        ))}
                    </div>
                </Card>
            </PageContent>
            <AnnouncementDetailDialog
                open={detailOpen}
                onOpenChange={(open) => {
                    setDetailOpen(open);

                    if (!open) {
                        setViewingItem(null);
                    }
                }}
                item={viewingItem}
                onEdit={(item) => {
                    setDetailOpen(false);
                    setViewingItem(null);
                    setEditingItem(item);
                    setFormOpen(true);
                }}
            />
            <AnnouncementFormDialog
                open={formOpen}
                onOpenChange={(open) => {
                    setFormOpen(open);

                    if (!open) {
                        setEditingItem(null);
                    }
                }}
                item={editingItem}
            />
            <ConfirmDialog
                open={pendingIds.length === 1}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingIds([]);
                    }
                }}
                title={t('notification.announcement.delete_title')}
                description={t('notification.announcement.delete_description')}
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (pendingIds.length !== 1) {
                        return;
                    }

                    router.delete(`/notifications/announcement/${pendingIds[0]}`, {
                        onFinish: () => setPendingIds([]),
                    });
                }}
            />
        </>
    );
}

function AnnouncementDetailDialog({
    open,
    onOpenChange,
    item,
    onEdit,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: AnnouncementItem | null;
    onEdit: (item: AnnouncementItem) => void;
}) {
    const { t } = useTranslation();
    const can = useCan();

    if (!item) {
        return null;
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={
                item.start_date || item.end_date
                    ? `${formatDateTime(item.start_date)} ~ ${formatDateTime(item.end_date)}`
                    : t('notification.announcement.content')
            }
            description={t('notification.announcement.edit_description')}
            icon={MegaphoneIcon}
            size="3xl"
        >
            {open ? (
                <div className="flex min-h-0 flex-1 flex-col">
                    <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                            <div>
                                <FormField
                                    label={t('notification.announcement.content_en')}
                                    htmlFor="view-content-en"
                                    icon={FileTextIcon}
                                >
                                    <Textarea
                                        id="view-content-en"
                                        className="min-h-36"
                                        value={item.content_en || '—'}
                                        rows={5}
                                        readOnly
                                    />
                                </FormField>
                            </div>

                            <div className="sm:pl-1.5">
                                <FormField
                                    label={t('notification.announcement.content_zh')}
                                    htmlFor="view-content-zh"
                                    icon={FileTextIcon}
                                >
                                    <Textarea
                                        id="view-content-zh"
                                        className="min-h-36"
                                        value={item.content_zh || '—'}
                                        rows={5}
                                        readOnly
                                    />
                                </FormField>
                            </div>

                            <div>
                                <FormField
                                    label={t('notification.announcement.content_my')}
                                    htmlFor="view-content-my"
                                    icon={FileTextIcon}
                                >
                                    <Textarea
                                        id="view-content-my"
                                        className="min-h-36"
                                        value={item.content_my || '—'}
                                        rows={5}
                                        readOnly
                                    />
                                </FormField>
                            </div>

                            <div className="sm:pl-1.5">
                                <FormField label={t('common.status')} htmlFor="view-status">
                                    <div className="flex h-10 w-full items-center rounded-md bg-muted/40 px-3 text-sm text-foreground">
                                        <StaffStatusSwitch
                                            id="view-status"
                                            value={item.is_active ? 'active' : 'inactive'}
                                            readOnly
                                        />
                                    </div>
                                </FormField>

                                <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <FormField
                                        label={t('common.start_date_time')}
                                        htmlFor="view-start-date"
                                        icon={CalendarIcon}
                                    >
                                        <Input
                                            id="view-start-date"
                                            type="datetime-local"
                                            value={item.start_date ?? ''}
                                            readOnly
                                            className="border-0 bg-transparent shadow-none px-0"
                                        />
                                    </FormField>

                                    <FormField
                                        label={t('common.end_date_time')}
                                        htmlFor="view-end-date"
                                        icon={CalendarClockIcon}
                                    >
                                        <Input
                                            id="view-end-date"
                                            type="datetime-local"
                                            value={item.end_date ?? ''}
                                            readOnly
                                            className="border-0 bg-transparent shadow-none px-0"
                                        />
                                    </FormField>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className={formActionBarClass}>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className={formActionButtonClass}
                            onClick={() => onOpenChange(false)}
                        >
                            <XIcon className="size-3.5" strokeWidth={1.85} />
                            {t('common.close')}
                        </Button>
                        {can('notifications.update') && onEdit ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className={formActionSubmitClass}
                                onClick={() => onEdit(item)}
                            >
                                <SquarePenIcon className="size-3.5" strokeWidth={1.85} />
                                {t('common.edit')}
                            </Button>
                        ) : null}
                    </div>
                </div>
            ) : null}
        </FormDialog>
    );
}
