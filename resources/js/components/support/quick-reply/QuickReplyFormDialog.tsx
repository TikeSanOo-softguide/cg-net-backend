import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { MessageSquareTextIcon, PencilIcon } from 'lucide-react';

import { QuickReplyForm, type QuickReplyFormValues } from '@/components/support/quick-reply/QuickReplyForm';
import { FormDialog } from '@/components/FormDialog';

export type QuickReplyItem = {
    id: number;
    keyword: string;
    response_en: string;
    response_my: string;
    response_zh: string;
};

type QuickReplyFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: QuickReplyItem | null;
};

function emptyQuickReplyForm(): QuickReplyFormValues {
    return {
        keyword: '',
        response_en: '',
        response_my: '',
        response_zh: '',
    };
}

export function QuickReplyFormDialog({ open, onOpenChange, item }: QuickReplyFormDialogProps) {
    const isEdit = item !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? 'Edit quick reply' : 'Add quick reply'}
            description={isEdit ? 'Update this quick reply.' : 'Create a response your support team can reuse.'}
            icon={isEdit ? PencilIcon : MessageSquareTextIcon}
            size="lg"
        >
            {open ? (
                <QuickReplyFormDialogBody
                    key={item ? `edit-${item.id}` : 'create'}
                    item={item}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function QuickReplyFormDialogBody({ item, onClose }: { item: QuickReplyItem | null; onClose: () => void }) {
    const isEdit = item !== null;

    const form = useForm<QuickReplyFormValues>(
        item
            ? {
                  keyword: item.keyword,
                  response_en: item.response_en,
                  response_my: item.response_my,
                  response_zh: item.response_zh,
              }
            : emptyQuickReplyForm(),
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: onClose,
        };

        if (isEdit && item) {
            form.put(`/support/quick-replies/replies/${item.id}`, options);
            return;
        }

        form.post('/support/quick-replies/replies', options);
    };

    return <QuickReplyForm form={form} onSubmit={submit} onCancel={onClose} mode={isEdit ? 'edit' : 'create'} />;
}
