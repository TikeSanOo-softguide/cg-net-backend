import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { MessageSquareTextIcon, SquarePenIcon } from 'lucide-react';

import { FormDialog } from '@/components/FormDialog';
import {
    QuickReplyForm,
    type QuickReplyCategoryOption,
    type QuickReplyFormValues,
} from '@/components/support/quick-reply/QuickReplyForm';
import { useTranslation } from '@/hooks/useTranslation';

export type QuickReplyItem = {
    id: number;
    keyword: string;
    category: string;
    response_en: string;
    response_my: string;
    response_zh: string;
    created_at: string | null;
    updated_at: string | null;
};

type QuickReplyFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: QuickReplyItem | null;
    categories: QuickReplyCategoryOption[];
    existingKeywords: string[];
};

function emptyQuickReplyForm(): QuickReplyFormValues {
    return {
        keyword: '',
        category: '',
        response_en: '',
        response_my: '',
        response_zh: '',
    };
}

export function QuickReplyFormDialog({
    open,
    onOpenChange,
    item,
    categories,
    existingKeywords,
}: QuickReplyFormDialogProps) {
    const { t } = useTranslation();
    const isEdit = item !== null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('support.quick_replies.edit') : t('support.quick_replies.create')}
            description={
                isEdit ? t('support.quick_replies.edit_description') : t('support.quick_replies.create_description')
            }
            icon={isEdit ? SquarePenIcon : MessageSquareTextIcon}
            size="xl"
        >
            {open ? (
                <QuickReplyFormDialogBody
                    key={item ? `edit-${item.id}` : 'create'}
                    item={item}
                    categories={categories}
                    existingKeywords={existingKeywords.filter((keyword) => keyword !== item?.keyword)}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function QuickReplyFormDialogBody({
    item,
    categories,
    existingKeywords,
    onClose,
}: {
    item: QuickReplyItem | null;
    categories: QuickReplyCategoryOption[];
    existingKeywords: string[];
    onClose: () => void;
}) {
    const isEdit = item !== null;
    const form = useForm<QuickReplyFormValues>(
        item
            ? {
                  keyword: item.keyword,
                  category: item.category,
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

    return (
        <QuickReplyForm
            form={form}
            categories={categories}
            existingKeywords={existingKeywords}
            onSubmit={submit}
            onCancel={onClose}
            mode={isEdit ? 'edit' : 'create'}
        />
    );
}
