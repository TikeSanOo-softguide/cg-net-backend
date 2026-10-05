import { EyeIcon } from 'lucide-react';

import { formActionBarClass, formActionButtonClass, formActionSubmitClass } from '@/components/FormActionBar';
import { FormDialog } from '@/components/FormDialog';
import type { QuickReplyItem } from '@/components/support/quick-reply/QuickReplyFormDialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/useTranslation';
import { formatDateTime } from '@/lib/utils';

type QuickReplyDetailDialogProps = {
    item: QuickReplyItem | null;
    onOpenChange: (open: boolean) => void;
    onEdit?: (item: QuickReplyItem) => void;
};

const languages = [
    { key: 'response_en', flag: '🇬🇧', labelKey: 'support.quick_replies.english' },
    { key: 'response_my', flag: '🇲🇲', labelKey: 'support.quick_replies.myanmar' },
    { key: 'response_zh', flag: '🇨🇳', labelKey: 'support.quick_replies.chinese' },
] as const;

export function QuickReplyDetailDialog({ item, onOpenChange, onEdit }: QuickReplyDetailDialogProps) {
    const { t } = useTranslation();

    return (
        <FormDialog
            open={item !== null}
            onOpenChange={onOpenChange}
            title={t('support.quick_replies.details')}
            description={t('support.quick_replies.details_description')}
            icon={EyeIcon}
            size="lg"
        >
            {item ? (
                <>
                    <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-5">
                        <section className="rounded-[8px] border border-border/80 bg-background p-4">
                            <h3 className="text-[13px] font-semibold text-foreground">
                                {t('support.quick_replies.basic_information')}
                            </h3>
                            <dl className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <DetailItem label={t('support.quick_replies.keyword')} value={item.keyword} />
                                <DetailItem
                                    label={t('support.quick_replies.category')}
                                    value={t(`support.quick_replies.categories.${item.category}`)}
                                />
                                <DetailItem label={t('common.created_at')} value={formatDateTime(item.created_at)} />
                                <DetailItem label={t('common.updated_at')} value={formatDateTime(item.updated_at)} />
                            </dl>
                        </section>
                        <section className="space-y-3">
                            <h3 className="text-[13px] font-semibold text-foreground">
                                {t('support.quick_replies.translated_responses')}
                            </h3>
                            {languages.map((language) => (
                                <article
                                    key={language.key}
                                    className="rounded-[8px] border border-border/80 bg-background p-4"
                                >
                                    <h4 className="text-[13px] font-semibold">
                                        {language.flag} {t(language.labelKey)}
                                    </h4>
                                    <p className="mt-2 text-[13px] leading-6 whitespace-pre-wrap text-foreground">
                                        {item[language.key]}
                                    </p>
                                </article>
                            ))}
                        </section>
                    </div>
                    <div className={formActionBarClass}>
                        <Button type="button" className={formActionButtonClass} onClick={() => onOpenChange(false)}>
                            {t('common.close')}
                        </Button>
                        {onEdit ? (
                            <Button type="button" className={formActionSubmitClass} onClick={() => onEdit(item)}>
                                {t('common.edit')}
                            </Button>
                        ) : null}
                    </div>
                </>
            ) : null}
        </FormDialog>
    );
}

function DetailItem({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <dt className="text-[12px] text-muted-foreground">{label}</dt>
            <dd className="mt-1 text-[13px] font-medium break-words text-foreground">{value}</dd>
        </div>
    );
}
