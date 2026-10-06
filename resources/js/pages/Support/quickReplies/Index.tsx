import { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { FolderTreeIcon, SquarePenIcon, Trash2Icon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { QuickReplyDetailDialog } from '@/components/support/quick-reply/QuickReplyDetailDialog';
import { QuickReplyFormDialog, type QuickReplyItem } from '@/components/support/quick-reply/QuickReplyFormDialog';
import type { QuickReplyCategoryOption } from '@/components/support/quick-reply/QuickReplyForm';
import { FormControl } from '@/components/ui/form-control';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';
import { visitBulkDelete } from '@/lib/bulk-delete';
import { formatDateTime, truncateText } from '@/lib/utils';

type Filters = {
    search: string;
    category: string;
};

type Props = {
    items: Paginated<QuickReplyItem>;
    existingKeywords: string[];
    filters: Filters;
    categories: QuickReplyCategoryOption[];
};

function responseForLocale(row: QuickReplyItem, locale: string): string {
    if (locale === 'my') {
        return row.response_my;
    }

    if (locale === 'zh') {
        return row.response_zh;
    }

    return row.response_en;
}

export default function QuickRepliesIndex({ items, existingKeywords, filters, categories }: Props) {
    const { t, locale } = useTranslation();
    const can = useCan();
    const [search, setSearch] = useState(filters.search);
    const [formOpen, setFormOpen] = useState(false);
    const [editingReply, setEditingReply] = useState<QuickReplyItem | null>(null);
    const [viewingReply, setViewingReply] = useState<QuickReplyItem | null>(null);
    const [pendingIds, setPendingIds] = useState<number[]>([]);
    const [processing, setProcessing] = useState(false);
    const debounce = useRef<number>(0);
    const canUpdate = can('support.update');
    const canDelete = can('support.delete');

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => () => window.clearTimeout(debounce.current), []);

    const visit = (next: Partial<Filters>) => {
        router.get(
            '/support/quick-replies',
            {
                search: (next.search ?? filters.search) || undefined,
                category: (next.category ?? filters.category) || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const openCreate = () => {
        setViewingReply(null);
        setEditingReply(null);
        setFormOpen(true);
    };

    const openEdit = (reply: QuickReplyItem) => {
        setViewingReply(null);
        setEditingReply(reply);
        setFormOpen(true);
    };

    return (
        <>
            <Head title={t('menu.quick_reply_templates')} />
            <PageContent>
                <PageHeader />
                <DataTable
                    data={items.data}
                    getRowId={(row) => String(row.id)}
                    search={search}
                    onSearchChange={(value) => {
                        setSearch(value);
                        window.clearTimeout(debounce.current);
                        debounce.current = window.setTimeout(() => visit({ search: value }), 300);
                    }}
                    searchPlaceholder={t('support.quick_replies.search_placeholder')}
                    emptyLabel={t('support.quick_replies.empty')}
                    pagination={items}
                    filters={
                        <>
                            <FormControl icon={FolderTreeIcon} compact className="w-full shrink-0 sm:w-48">
                                <Select
                                    value={filters.category || 'all'}
                                    onValueChange={(value) => visit({ category: value === 'all' ? '' : value })}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder={t('support.quick_replies.category')} />
                                    </SelectTrigger>
                                    <SelectContent className="[&_[data-slot=select-item]]:text-[11px]">
                                        <SelectItem value="all">{t('support.quick_replies.all_categories')}</SelectItem>
                                        {categories.map((category) => (
                                            <SelectItem key={category.value} value={category.value}>
                                                {t(category.label_key)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormControl>
                        </>
                    }
                    onCreate={can('support.create') ? openCreate : undefined}
                    createLabel={t('support.quick_replies.create')}
                    onView={setViewingReply}
                    onBulkDelete={
                        canDelete
                            ? (ids) => visitBulkDelete('/support/quick-replies/bulk-destroy', ids.map(Number))
                            : undefined
                    }
                    bulkDeleteTitle={t('support.quick_replies.bulk_delete_title')}
                    bulkDeleteDescription={t('support.quick_replies.bulk_delete_description')}
                    columns={[
                        {
                            id: 'keyword',
                            header: t('support.quick_replies.keyword'),
                            mobile: 'title',
                            className: 'font-medium',
                            cell: (row) => (
                                <span className="block max-w-[180px] truncate" title={row.keyword}>
                                    {row.keyword}
                                </span>
                            ),
                        },
                        {
                            id: 'category',
                            header: t('support.quick_replies.category'),
                            mobile: 'subtitle',
                            cell: (row) => t(`support.quick_replies.categories.${row.category}`),
                        },
                        {
                            id: 'response',
                            header: t('support.quick_replies.response'),
                            cell: (row) => {
                                const response = responseForLocale(row, locale);

                                return (
                                    <span
                                        className="block max-w-[280px] truncate text-muted-foreground"
                                        title={response}
                                    >
                                        {truncateText(response, 80)}
                                    </span>
                                );
                            },
                        },
                        {
                            id: 'created_at',
                            header: t('common.created_at'),
                            mobile: 'meta',
                            className: 'text-muted-foreground',
                            cell: (row) => formatDateTime(row.created_at),
                        },
                        {
                            id: 'updated_at',
                            header: t('common.updated_at'),
                            className: 'text-muted-foreground',
                            cell: (row) => formatDateTime(row.updated_at),
                        },
                    ]}
                    actions={(row) => (
                        <>
                            {canUpdate ? (
                                <TableActionButton
                                    label={t('common.edit')}
                                    icon={SquarePenIcon}
                                    tone="edit"
                                    onClick={() => openEdit(row)}
                                />
                            ) : null}
                            {canDelete ? (
                                <TableActionButton
                                    label={t('common.delete')}
                                    icon={Trash2Icon}
                                    tone="danger"
                                    onClick={() => setPendingIds([row.id])}
                                />
                            ) : null}
                        </>
                    )}
                />
            </PageContent>
            <QuickReplyFormDialog
                open={formOpen}
                onOpenChange={(open) => {
                    setFormOpen(open);
                    if (!open) {
                        setEditingReply(null);
                    }
                }}
                item={editingReply}
                categories={categories}
                existingKeywords={existingKeywords}
            />
            <QuickReplyDetailDialog
                item={viewingReply}
                onOpenChange={(open) => {
                    if (!open) {
                        setViewingReply(null);
                    }
                }}
                onEdit={canUpdate ? openEdit : undefined}
            />
            <ConfirmDialog
                open={pendingIds.length === 1}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingIds([]);
                    }
                }}
                title={t('support.quick_replies.delete_title')}
                description={t('support.quick_replies.delete_description')}
                confirmLabel={t('common.delete')}
                destructive
                processing={processing}
                onConfirm={() => {
                    if (pendingIds.length !== 1) {
                        return;
                    }

                    router.delete(`/support/quick-replies/replies/${pendingIds[0]}`, {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => {
                            setProcessing(false);
                            setPendingIds([]);
                        },
                    });
                }}
            />
        </>
    );
}
