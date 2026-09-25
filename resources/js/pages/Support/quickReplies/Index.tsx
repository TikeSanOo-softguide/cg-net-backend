import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { MessageSquareTextIcon, PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { CopyValueButton } from '@/components/CopyValueButton';
import { DataTable } from '@/components/DataTable';
import type { Paginated } from '@/components/Pagination';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { TableActionButton } from '@/components/TableActionButton';
import { visitBulkDelete } from '@/lib/bulk-delete';
import { QuickReplyFormDialog, type QuickReplyItem } from '@/components/support/quick-reply/QuickReplyFormDialog';

type QuickRepliesProps = {
    quickReplies: Paginated<QuickReplyItem>;
    filters: {
        search?: string;
    };
};

export default function QuickRepliesIndex({ quickReplies, filters }: QuickRepliesProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [editingReply, setEditingReply] = useState<QuickReplyItem | null>(null);
    const [pendingDelete, setPendingDelete] = useState<QuickReplyItem | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const openCreate = () => {
        setEditingReply(null);
        setFormOpen(true);
    };

    const openEdit = (reply: QuickReplyItem) => {
        setEditingReply(reply);
        setFormOpen(true);
    };

    const visit = (search: string) => {
        router.get(
            '/support/quick-replies',
            { search: search || undefined },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title="Quick Replies" />
            <PageContent>
                <PageHeader />
                <DataTable
                    title="Saved quick replies"
                    titleIcon={MessageSquareTextIcon}
                    data={quickReplies.data}
                    getRowId={(row) => String(row.id)}
                    pagination={quickReplies}
                    search={filters.search ?? ''}
                    onSearchChange={visit}
                    searchPlaceholder="Search by keyword"
                    onCreate={openCreate}
                    createLabel="Add quick reply"
                    onBulkDelete={(ids) => visitBulkDelete('/support/quick-replies/bulk-destroy', ids.map(Number))}
                    columns={[
                        {
                            id: 'keyword',
                            header: 'Keyword',
                            className: 'font-medium',
                            mobile: 'title',
                            cell: (row) => (
                                <span className="block truncate text-[13px] font-medium">{row.keyword}</span>
                            ),
                        },
                        {
                            id: 'response_en',
                            header: 'Response EN',
                            className: 'max-w-[260px]',
                            mobile: 'subtitle',
                            cell: (row) => <ResponseCell label="EN" value={row.response_en} />,
                        },
                        {
                            id: 'response_my',
                            header: 'Response MY',
                            className: 'max-w-[260px]',
                            cell: (row) => <ResponseCell label="MY" value={row.response_my} />,
                        },
                        {
                            id: 'response_zh',
                            header: 'Response ZH',
                            className: 'max-w-[260px]',
                            cell: (row) => <ResponseCell label="ZH" value={row.response_zh} />,
                        },
                    ]}
                    actions={(row) => (
                        <>
                            <TableActionButton
                                label="Edit quick reply"
                                icon={PencilIcon}
                                tone="edit"
                                onClick={() => openEdit(row)}
                            />
                            <TableActionButton
                                label="Delete quick reply"
                                icon={Trash2Icon}
                                tone="danger"
                                onClick={() => setPendingDelete(row)}
                            />
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
            />

            <ConfirmDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingDelete(null);
                    }
                }}
                title="Delete quick reply"
                description="Are you sure you want to delete this quick reply?"
                confirmLabel="Delete"
                destructive
                processing={deleteProcessing}
                onConfirm={() => {
                    if (!pendingDelete) {
                        return;
                    }

                    router.delete(`/support/quick-replies/replies/${pendingDelete.id}`, {
                        preserveScroll: true,
                        onStart: () => setDeleteProcessing(true),
                        onFinish: () => {
                            setDeleteProcessing(false);
                            setPendingDelete(null);
                        },
                    });
                }}
            />
        </>
    );
}

function ResponseCell({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex min-w-0 items-center gap-1">
            <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground">
                {label}
            </span>
            <span className="min-w-0 flex-1 truncate text-[13px] text-muted-foreground">{value}</span>
            <CopyValueButton value={value} label={`Copy ${label} response`} />
        </div>
    );
}
