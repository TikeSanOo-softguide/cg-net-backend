import { useState, type FormEvent } from 'react';
import { router } from '@inertiajs/react';
import { BotIcon, SquarePenIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { FormDialog } from '@/components/FormDialog';
import { MessageFields, FieldLabel } from '@/components/support/chat-flow-step/ChatFlowStepFields';
import { emptyMessages, type LanguageMessages, type Step } from '@/components/support/chat-flow-step/ChatFlowStepTypes';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import { hasValidationErrors, validateStepForm } from '@/lib/chat-flow-validation';
import { formControlStateClass } from '@/lib/form-control';

type MessageLanguage = keyof LanguageMessages;

export function StepFormDialog({
    open,
    onOpenChange,
    step,
    existingStepIds,
    onCreated,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    step: Step | null;
    existingStepIds: number[];
    onCreated: (id: number) => void;
}) {
    const isEdit = step !== null;
    const { t } = useTranslation();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={isEdit ? t('support.chatbot_flows.edit_step') : t('support.chatbot_flows.create_step')}
            description={
                isEdit
                    ? t('support.chatbot_flows.edit_step_description')
                    : t('support.chatbot_flows.create_step_description')
            }
            icon={isEdit ? SquarePenIcon : BotIcon}
            size="2xl"
        >
            {open ? (
                <StepFormDialogBody
                    key={step ? `edit-${step.id}` : 'create'}
                    step={step}
                    existingStepIds={existingStepIds}
                    onCreated={onCreated}
                    onClose={() => onOpenChange(false)}
                />
            ) : null}
        </FormDialog>
    );
}

function StepFormDialogBody({
    step,
    existingStepIds,
    onCreated,
    onClose,
}: {
    step: Step | null;
    existingStepIds: number[];
    onCreated: (id: number) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const isEdit = step !== null;
    const [name, setName] = useState(step?.name ?? '');
    const [messages, setMessages] = useState<LanguageMessages>({
        en: step?.message_en ?? emptyMessages.en,
        my: step?.message_my ?? emptyMessages.my,
        zh: step?.message_zh ?? emptyMessages.zh,
    });
    const [isStart, setIsStart] = useState(step?.is_start ?? false);
    const [touched, setTouched] = useState({
        name: false,
        messages: { en: false, my: false, zh: false },
    });
    const [submitted, setSubmitted] = useState(false);
    const validationErrors = validateStepForm({ name, messages }, t);

    const nameState = !touched.name && !submitted ? 'idle' : validationErrors.name ? 'error' : 'success';
    const nameError = touched.name || submitted ? validationErrors.name : undefined;
    const messageState = (language: MessageLanguage) =>
        !touched.messages[language] && !submitted ? 'idle' : validationErrors.messages[language] ? 'error' : 'success';
    const messageErrors = {
        en: touched.messages.en || submitted ? validationErrors.messages.en : undefined,
        my: touched.messages.my || submitted ? validationErrors.messages.my : undefined,
        zh: touched.messages.zh || submitted ? validationErrors.messages.zh : undefined,
    };

    const markMessageTouched = (language: MessageLanguage) => {
        setTouched((current) => ({
            ...current,
            messages: { ...current.messages, [language]: true },
        }));
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSubmitted(true);
        const validationErrors = validateStepForm({ name, messages }, t);

        if (hasValidationErrors(validationErrors)) {
            return;
        }

        const payload = {
            name,
            is_start: isStart,
            message_en: messages.en,
            message_my: messages.my,
            message_zh: messages.zh,
        };
        const url = isEdit ? `/support/chatbot-flows/steps/${step.id}` : '/support/chatbot-flows/steps';
        const options = {
            preserveScroll: true,
            onSuccess: (page: { props: Record<string, unknown> }) => {
                if (!isEdit && Array.isArray(page.props.steps)) {
                    const existingIds = new Set(existingStepIds);
                    const createdStep = (page.props.steps as Step[]).find(
                        (availableStep) => !existingIds.has(availableStep.id),
                    );

                    if (createdStep) onCreated(createdStep.id);
                }

                onClose();
            },
        };

        if (isEdit) {
            router.put(url, payload, options);
        } else {
            router.post(url, payload, options);
        }
    };

    return (
        <form className="flex min-h-0 flex-1 flex-col" onSubmit={submit}>
            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px]">
                    {/* Step Name */}
                    <FormField
                        label={t('support.chatbot_flows.step_name')}
                        htmlFor="step_name"
                        error={nameError}
                        required
                    >
                        <Input
                            id="step_name"
                            value={name}
                            aria-invalid={nameState === 'error'}
                            className={formControlStateClass(nameState)}
                            onBlur={() =>
                                setTouched((current) => ({
                                    ...current,
                                    name: true,
                                }))
                            }
                            onChange={(event) => {
                                setName(event.target.value);
                                setTouched((current) => ({
                                    ...current,
                                    name: true,
                                }));
                            }}
                        />
                    </FormField>

                    <div className="space-y-1.5">
                        <FieldLabel>{t('support.chatbot_flows.start_step')}</FieldLabel>

                        <div className="flex min-h-10 items-center justify-start rounded-md border border-primary/30 bg-primary/5 px-3">
                            <label className="flex cursor-pointer items-center gap-2 text-xs">
                                <input
                                    type="checkbox"
                                    checked={isStart}
                                    onChange={(event) => setIsStart(event.target.checked)}
                                    className="accent-primary"
                                />

                                <span>{t('support.chatbot_flows.start_step')}</span>
                            </label>
                        </div>
                    </div>
                </div>
                <MessageFields
                    label={t('support.chatbot_flows.messages')}
                    messages={messages}
                    setMessages={(nextMessages, language) => {
                        setMessages(nextMessages);
                        if (language) markMessageTouched(language);
                    }}
                    errors={messageErrors}
                    fieldStates={{ en: messageState('en'), my: messageState('my'), zh: messageState('zh') }}
                    onBlur={markMessageTouched}
                />
            </div>
            <FormActionBar
                onCancel={onClose}
                submitLabel={isEdit ? t('support.chatbot_flows.edit_step') : t('support.chatbot_flows.create_step')}
                mode={isEdit ? 'edit' : 'create'}
            />
        </form>
    );
}
