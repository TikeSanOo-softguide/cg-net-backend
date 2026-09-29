import { useState, type FormEvent } from 'react';
import { router } from '@inertiajs/react';
import { HeadsetIcon, ListPlusIcon, SquarePenIcon, StepForward, ListTodo } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormDialog } from '@/components/FormDialog';
import { FieldLabel, MessageFields } from '@/components/support/chat-flow-step/ChatFlowStepFields';
import {
    emptyMessages,
    type ChatFlowActionOption,
    type LanguageMessages,
    type Option,
    type Step,
} from '@/components/support/chat-flow-step/ChatFlowStepTypes';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';
import { hasValidationErrors, validateOptionForm } from '@/lib/chat-flow-validation';
import { formControlStateClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';

type MessageLanguage = keyof LanguageMessages;
type OptionField = 'action' | 'nextStepId' | 'url' | 'sortOrder' | 'isActive';

export function OptionFormDialog({
    open,
    onOpenChange,
    step,
    steps,
    actionOptions,
    option,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    step: Step | null;
    steps: Step[];
    actionOptions: ChatFlowActionOption[];
    option: Option | null;
}) {
    const isEdit = option !== null;
    const { t } = useTranslation();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('support.chatbot_flows.edit_option') : t('support.chatbot_flows.create_option')}
            description={
                isEdit
                    ? t('support.chatbot_flows.edit_option_description')
                    : t('support.chatbot_flows.create_option_description')
            }
            icon={isEdit ? SquarePenIcon : ListPlusIcon}
            size="lg"
        >
            {open && step ? (
                <OptionFormDialogBody
                    key={option ? `edit-${option.id}` : 'create'}
                    step={step}
                    steps={steps}
                    actionOptions={actionOptions}
                    option={option}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function OptionFormDialogBody({
    step,
    steps,
    actionOptions,
    option,
    onClose,
}: {
    step: Step;
    steps: Step[];
    actionOptions: ChatFlowActionOption[];
    option: Option | null;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const isEdit = option !== null;
    const [labels, setLabels] = useState<LanguageMessages>({
        en: option?.option_en ?? emptyMessages.en,
        my: option?.option_my ?? emptyMessages.my,
        zh: option?.option_zh ?? emptyMessages.zh,
    });
    const [action, setAction] = useState(option?.action ?? '');
    const [nextStepId, setNextStepId] = useState(option?.next_step_id?.toString() ?? '');
    const [url, setUrl] = useState(option?.url ?? '');
    const [replies, setReplies] = useState<LanguageMessages>({
        en: option?.reply_text_en ?? emptyMessages.en,
        my: option?.reply_text_my ?? emptyMessages.my,
        zh: option?.reply_text_zh ?? emptyMessages.zh,
    });
    const [sortOrder, setSortOrder] = useState(
        String(
            option?.sort_order ?? Math.max(0, ...step.options.map((availableOption) => availableOption.sort_order)) + 1,
        ),
    );
    const [isActive, setIsActive] = useState(option?.is_active ?? true);
    const [touched, setTouched] = useState({
        labels: { en: false, my: false, zh: false },
        action: false,
        nextStepId: false,
        url: false,
        replies: { en: false, my: false, zh: false },
        sortOrder: false,
        isActive: false,
    });
    const [submitted, setSubmitted] = useState(false);
    const validationErrors = validateOptionForm(
        { labels, action, nextStepId, url, replies, sortOrder, isActive },
        actionOptions,
        step.id,
        t,
        step.options,
        option?.id,
    );

    const fieldState = (field: OptionField): 'idle' | 'error' | 'success' => {
        if (!touched[field] && !submitted) return 'idle';
        return validationErrors[field] ? 'error' : 'success';
    };
    const fieldError = (field: OptionField) => (touched[field] || submitted ? validationErrors[field] : undefined);
    const messageState = (group: 'labels' | 'replies', language: MessageLanguage) => {
        if (!touched[group][language] && !submitted) return 'idle';
        return validationErrors[group][language] ? 'error' : 'success';
    };
    const messageErrors = (group: 'labels' | 'replies') => ({
        en: touched[group].en || submitted ? validationErrors[group].en : undefined,
        my: touched[group].my || submitted ? validationErrors[group].my : undefined,
        zh: touched[group].zh || submitted ? validationErrors[group].zh : undefined,
    });
    const markMessageTouched = (group: 'labels' | 'replies', language: MessageLanguage) => {
        setTouched((current) => ({
            ...current,
            [group]: { ...current[group], [language]: true },
        }));
    };
    const markFieldTouched = (field: OptionField) => {
        setTouched((current) => ({ ...current, [field]: true }));
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSubmitted(true);
        const validationErrors = validateOptionForm(
            { labels, action, nextStepId, url, replies, sortOrder, isActive },
            actionOptions,
            step.id,
            t,
            step.options,
            option?.id,
        );

        if (hasValidationErrors(validationErrors)) {
            return;
        }

        const payload = {
            action,
            option_en: labels.en,
            option_my: labels.my,
            option_zh: labels.zh,
            next_step_id: action === 'go_to_step' ? nextStepId || null : null,
            url: action === 'go_to_url' ? url || null : null,
            reply_text_en: action === 'reply_text' ? replies.en || null : null,
            reply_text_my: action === 'reply_text' ? replies.my || null : null,
            reply_text_zh: action === 'reply_text' ? replies.zh || null : null,
            sort_order: Number(sortOrder),
            is_active: isActive,
        };
        const optionUrl = isEdit
            ? `/support/chatbot-flows/steps/${step.id}/options/${option.id}`
            : `/support/chatbot-flows/steps/${step.id}/options`;
        const options = { preserveScroll: true, onSuccess: onClose };

        if (isEdit) {
            router.put(optionUrl, payload, options);
        } else {
            router.post(optionUrl, payload, options);
        }
    };

    return (
        <form className="flex min-h-0 flex-1 flex-col" onSubmit={submit}>
            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                <MessageFields
                    label={t('support.chatbot_flows.option_labels')}
                    messages={labels}
                    setMessages={(nextLabels, language) => {
                        setLabels(nextLabels);
                        if (language) markMessageTouched('labels', language);
                    }}
                    errors={messageErrors('labels')}
                    fieldStates={{
                        en: messageState('labels', 'en'),
                        my: messageState('labels', 'my'),
                        zh: messageState('labels', 'zh'),
                    }}
                    onBlur={(language) => markMessageTouched('labels', language)}
                    multiline={false}
                />

                <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_220px]">
                    <FormField
                        label={t('cms.sort_order')}
                        htmlFor="sort_order"
                        error={fieldError('sortOrder')}
                        required
                    >
                        <Input
                            id="sort_order"
                            name="sort_order"
                            type="number"
                            min="0"
                            step="1"
                            value={sortOrder}
                            aria-invalid={fieldState('sortOrder') === 'error'}
                            className={formControlStateClass(fieldState('sortOrder'))}
                            onBlur={() => markFieldTouched('sortOrder')}
                            onChange={(event) => {
                                setSortOrder(event.target.value);
                                markFieldTouched('sortOrder');
                            }}
                        />
                    </FormField>

                    <div className="space-y-1.5">
                        <FieldLabel required>{t('support.chatbot_flows.option_status')}</FieldLabel>

                        <label
                            className={[
                                'flex min-h-10 cursor-pointer items-center justify-between',
                                'rounded-md border px-3',
                                'transition-colors',
                                isActive ? 'border-primary/30 bg-primary/5' : 'border-border bg-muted/20',
                            ].join(' ')}
                        >
                            <div className="flex items-center gap-2.5">
                                <span
                                    className={[
                                        'flex h-7 w-7 items-center justify-center rounded-full',
                                        isActive ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground',
                                    ].join(' ')}
                                >
                                    <span
                                        className={[
                                            'h-2.5 w-2.5 rounded-full',
                                            isActive ? 'bg-primary' : 'bg-muted-foreground/40',
                                        ].join(' ')}
                                    />
                                </span>

                                <span className="text-xs font-medium">
                                    {isActive ? t('status.active') : t('status.inactive')}
                                </span>
                            </div>

                            <input
                                type="checkbox"
                                checked={isActive}
                                aria-invalid={fieldState('isActive') === 'error'}
                                onBlur={() => markFieldTouched('isActive')}
                                onChange={(event) => {
                                    setIsActive(event.target.checked);
                                    markFieldTouched('isActive');
                                }}
                                className="peer sr-only"
                            />

                            <span
                                className={[
                                    'relative h-5 w-9 rounded-full',
                                    'transition-colors',
                                    isActive ? 'bg-primary' : 'bg-muted-foreground/30',
                                    'after:absolute after:top-0.5 after:h-4 after:w-4',
                                    'after:rounded-full after:bg-white after:shadow-sm',
                                    'after:transition-transform',
                                    isActive ? 'after:left-[18px]' : 'after:left-0.5',
                                ].join(' ')}
                            />
                        </label>

                        {fieldError('isActive') ? (
                            <p className="mt-1.5 text-[12px] font-medium leading-4 text-danger">
                                {fieldError('isActive')}
                            </p>
                        ) : null}
                    </div>
                </div>

                <FormField
                    label={t('support.chatbot_flows.actions')}
                    htmlFor="option_action"
                    error={fieldError('action')}
                    icon={ListTodo}
                    required
                >
                    <Select
                        value={action}
                        onValueChange={(value) => {
                            setAction(value);
                            markFieldTouched('action');
                        }}
                    >
                        <SelectTrigger
                            id="option_action"
                            className={cn('w-full', formControlStateClass(fieldState('action')))}
                            aria-invalid={fieldState('action') === 'error'}
                            onBlur={() => markFieldTouched('action')}
                        >
                            <SelectValue placeholder={t('support.chatbot_flows.select_action')} />
                        </SelectTrigger>
                        <SelectContent>
                            {actionOptions.map((actionOption) => (
                                <SelectItem key={actionOption.value} value={actionOption.value}>
                                    {actionOption.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>
                {action === 'go_to_step' ? (
                    <FormField
                        label={t('support.chatbot_flows.next_step')}
                        htmlFor="next_step_id"
                        error={fieldError('nextStepId')}
                        icon={StepForward}
                        required
                    >
                        <Select
                            value={nextStepId || undefined}
                            onValueChange={(value) => {
                                setNextStepId(value);
                                markFieldTouched('nextStepId');
                            }}
                        >
                            <SelectTrigger
                                id="next_step_id"
                                className={cn('w-full', formControlStateClass(fieldState('nextStepId')))}
                                aria-invalid={fieldState('nextStepId') === 'error'}
                                onBlur={() => markFieldTouched('nextStepId')}
                            >
                                <SelectValue placeholder={t('support.chatbot_flows.select_step')} />
                            </SelectTrigger>
                            <SelectContent>
                                {steps
                                    .filter((availableStep) => availableStep.id !== step.id)
                                    .map((availableStep) => (
                                        <SelectItem key={availableStep.id} value={String(availableStep.id)}>
                                            {availableStep.name}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                ) : null}
                {action === 'go_to_url' ? (
                    <FormField
                        label={t('support.chatbot_flows.url')}
                        htmlFor="option_url"
                        error={fieldError('url')}
                        required
                    >
                        <Input
                            id="option_url"
                            value={url}
                            aria-invalid={fieldState('url') === 'error'}
                            className={formControlStateClass(fieldState('url'))}
                            onBlur={() => markFieldTouched('url')}
                            onChange={(event) => {
                                setUrl(event.target.value);
                                markFieldTouched('url');
                            }}
                            type="url"
                            placeholder="https://example.com"
                        />
                    </FormField>
                ) : null}
                {action === 'reply_text' ? (
                    <MessageFields
                        label={t('support.chatbot_flows.reply_text')}
                        messages={replies}
                        setMessages={(nextReplies, language) => {
                            setReplies(nextReplies);
                            if (language) markMessageTouched('replies', language);
                        }}
                        errors={messageErrors('replies')}
                        fieldStates={{
                            en: messageState('replies', 'en'),
                            my: messageState('replies', 'my'),
                            zh: messageState('replies', 'zh'),
                        }}
                        onBlur={(language) => markMessageTouched('replies', language)}
                    />
                ) : null}
                {action === 'transfer_agent' ? (
                    <div className="flex items-start gap-3 rounded-md border border-sky-200 bg-sky-50 p-3 text-sky-900">
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700">
                            <HeadsetIcon className="size-4" />
                        </div>
                        <div>
                            <p className="text-xs font-semibold">{t('support.chatbot_flows.transfer_agent')}</p>
                            <p className="mt-1 text-[11px] leading-4 text-sky-800/80">
                                {t('support.chatbot_flows.transfer_agent_des')}
                            </p>
                        </div>
                    </div>
                ) : null}
                {action === 'close_chat' ? (
                    <div className="rounded-md border bg-muted/30 p-3 text-xs text-muted-foreground">
                        {t('support.chatbot_flows.close_chat')}
                    </div>
                ) : null}
            </div>
            <FormActionBar
                onCancel={onClose}
                submitLabel={isEdit ? t('support.chatbot_flows.edit_option') : t('support.chatbot_flows.create_option')}
                mode={isEdit ? 'edit' : 'create'}
            />
        </form>
    );
}
