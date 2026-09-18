import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { BotIcon, ChevronRightIcon, HeadsetIcon, PencilIcon, PlusIcon, Trash2Icon, XIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type Translation = { language: string; message?: string; label?: string };
type Reply = { language: string; reply_text: string };
type Option = {
    id: number;
    action: 'go_to_step' | 'reply_text' | 'go_to_url' | 'transfer_agent';
    url?: string | null;
    translations: Translation[];
    replies: Reply[];
    next_step?: { id: number; name: string } | null;
};
type Step = { id: number; name: string; is_start: boolean; translations: Translation[]; options: Option[] };
const languages = [
    { key: 'en', label: 'English', flag: '🇺🇸' },
    { key: 'my', label: 'Myanmar Burmese', flag: '🇲🇲' },
    { key: 'zh', label: 'Chinese', flag: '🇨🇳' },
] as const;
const emptyMessages = { en: '', my: '', zh: '' };

function getText(items: Translation[] = [], key: 'message' | 'label', language: string) {
    return items.find((item) => item.language === language)?.[key] ?? '';
}

function FieldLabel({ children, required = false }: { children: React.ReactNode; required?: boolean }) {
    return (
        <label className="block text-[11px] font-semibold text-foreground">
            {children}
            {required ? <span className="ml-0.5 text-danger">*</span> : null}
        </label>
    );
}

function MessageFields({
    messages,
    setMessages,
    label = 'Message (shown to user)',
}: {
    messages: typeof emptyMessages;
    setMessages: (messages: typeof emptyMessages) => void;
    label?: string;
}) {
    return (
        <div className="space-y-2">
            <FieldLabel>{label}</FieldLabel>
            <div className="grid gap-2 md:grid-cols-3">
                {languages.map((language) => (
                    <div key={language.key} className="space-y-1">
                        <span className="flex items-center gap-1 text-[10px] font-medium text-muted-foreground">
                            {language.flag} {language.label}
                        </span>
                        <textarea
                            value={messages[language.key]}
                            onChange={(event) => setMessages({ ...messages, [language.key]: event.target.value })}
                            className="min-h-20 w-full resize-y rounded-md border border-border bg-background px-2.5 py-2 text-xs outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15"
                            maxLength={1000}
                            required
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function ChatFlowsIndex() {
    const { t, locale } = useTranslation();
    const { steps } = usePage<{ steps: Step[] }>().props;
    const [selectedId, setSelectedId] = useState<number | null>(steps[0]?.id ?? null);
    const [dialog, setDialog] = useState<'step' | 'option' | null>(null);
    const [editingStep, setEditingStep] = useState(false);
    const [editingOptionId, setEditingOptionId] = useState<number | null>(null);
    const selectedStep = steps.find((step) => step.id === selectedId) ?? null;
    const [stepName, setStepName] = useState('');
    const [stepMessages, setStepMessages] = useState(emptyMessages);
    const [isStart, setIsStart] = useState(false);
    const [optionLabels, setOptionLabels] = useState(emptyMessages);
    const [action, setAction] = useState<Option['action']>('go_to_step');
    const [nextStepId, setNextStepId] = useState('');
    const [url, setUrl] = useState('');
    const [replies, setReplies] = useState(emptyMessages);

    useEffect(() => {
        if (!selectedStep) return;
        setStepName(selectedStep.name);
        setIsStart(selectedStep.is_start);
        setStepMessages({
            en: getText(selectedStep.translations, 'message', 'en'),
            my: getText(selectedStep.translations, 'message', 'my'),
            zh: getText(selectedStep.translations, 'message', 'zh'),
        });
    }, [selectedStep]);
    const resetOption = () => {
        setEditingOptionId(null);
        setOptionLabels(emptyMessages);
        setAction('go_to_step');
        setNextStepId('');
        setUrl('');
        setReplies(emptyMessages);
    };
    const editOption = (option: Option) => {
        setEditingOptionId(option.id);
        setOptionLabels({
            en: getText(option.translations, 'label', 'en'),
            my: getText(option.translations, 'label', 'my'),
            zh: getText(option.translations, 'label', 'zh'),
        });
        setAction(option.action);
        setNextStepId(option.next_step?.id?.toString() ?? '');
        setUrl(option.url ?? '');
        setReplies({
            en: option.replies.find((reply) => reply.language === 'en')?.reply_text ?? '',
            my: option.replies.find((reply) => reply.language === 'my')?.reply_text ?? '',
            zh: option.replies.find((reply) => reply.language === 'zh')?.reply_text ?? '',
        });
        setDialog('option');
    };
    const openNewStep = () => {
        setEditingStep(false);
        setStepName('');
        setStepMessages(emptyMessages);
        setIsStart(false);
        setDialog('step');
    };
    const submitStep = (event: React.FormEvent) => {
        event.preventDefault();
        const payload = {
            name: stepName,
            is_start: isStart,
            translations: {
                en: { message: stepMessages.en },
                my: { message: stepMessages.my },
                zh: { message: stepMessages.zh },
            },
        };
        const url = editingStep ? `/support/chatbot-flows/steps/${selectedStep?.id}` : '/support/chatbot-flows/steps';
        if (editingStep)
            router.put(url, payload, {
                preserveScroll: true,
                onSuccess: () => {
                    setDialog(null);
                    setEditingStep(false);
                },
            });
        else router.post(url, payload, { preserveScroll: true, onSuccess: () => setDialog(null) });
    };
    const submitOption = (event: React.FormEvent) => {
        event.preventDefault();
        if (!selectedStep) return;
        const payload = {
            action,
            next_step_id: action === 'go_to_step' ? nextStepId : null,
            url: action === 'go_to_url' ? url : null,
            translations: {
                en: { label: optionLabels.en },
                my: { label: optionLabels.my },
                zh: { label: optionLabels.zh },
            },
            replies: { en: { reply_text: replies.en }, my: { reply_text: replies.my }, zh: { reply_text: replies.zh } },
        };
        const optionUrl = editingOptionId
            ? `/support/chatbot-flows/steps/${selectedStep.id}/options/${editingOptionId}`
            : `/support/chatbot-flows/steps/${selectedStep.id}/options`;
        if (editingOptionId)
            router.put(optionUrl, payload, {
                preserveScroll: true,
                onSuccess: () => {
                    setDialog(null);
                    resetOption();
                },
            });
        else
            router.post(optionUrl, payload, {
                preserveScroll: true,
                onSuccess: () => {
                    setDialog(null);
                    resetOption();
                },
            });
    };

    return (
        <>
            <Head title={t('menu.chatbot_flows')} />
            <PageContent className="min-h-full">
                <PageHeader
                    title="Chatbot Flow Management"
                    description="Manage your chatbot conversation flow and steps."
                    actions={
                        steps.length > 0 ? (
                            <Button size="sm" onClick={openNewStep}>
                                <PlusIcon /> New Step
                            </Button>
                        ) : undefined
                    }
                />
                {steps.length === 0 ? (
                    <Card className="flex min-h-[420px] items-center justify-center border-dashed">
                        <CardContent className="flex max-w-sm flex-col items-center gap-3 text-center">
                            <div className="flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <BotIcon className="size-7" />
                            </div>
                            <div>
                                <h2 className="font-heading text-base font-semibold">No steps yet</h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Start building your chatbot flow by adding your first step.
                                </p>
                            </div>
                            <Button size="sm" onClick={openNewStep}>
                                <PlusIcon /> Add First Step
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid min-h-[620px] gap-4 lg:grid-cols-[225px_minmax(0,1fr)]">
                        <Card className="min-h-0 gap-3">
                            <CardHeader className="border-b border-border/70 pb-3">
                                <CardTitle>Step List</CardTitle>
                                <p className="text-[11px] text-muted-foreground">Total {steps.length} steps</p>
                            </CardHeader>
                            <CardContent className="min-h-0 overflow-y-auto px-3">
                                <div className="space-y-1">
                                    {steps.map((step, index) => (
                                        <button
                                            key={step.id}
                                            onClick={() => setSelectedId(step.id)}
                                            className={cn(
                                                'flex w-full items-start gap-2 rounded-md border border-transparent p-2.5 text-left transition hover:bg-muted/60',
                                                selectedId === step.id && 'border-primary/15 bg-primary/5',
                                            )}
                                        >
                                            <span className="mt-0.5 text-[11px] font-semibold text-muted-foreground">
                                                {index + 1}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="flex items-center gap-1 text-xs font-semibold">
                                                    <span className="truncate">{step.name}</span>
                                                    {step.is_start ? (
                                                        <span className="rounded bg-emerald-100 px-1 py-0.5 text-[8px] font-bold text-emerald-700">
                                                            START
                                                        </span>
                                                    ) : null}
                                                </span>
                                                <span className="mt-1 block text-[10px] text-muted-foreground">
                                                    {step.options.length}{' '}
                                                    {step.options.length === 1 ? 'option' : 'options'}
                                                </span>
                                            </span>
                                            <ChevronRightIcon className="mt-1 size-3.5 text-muted-foreground" />
                                        </button>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                        {selectedStep ? (
                            <Card className="min-w-0 gap-4">
                                <CardHeader className="flex flex-row items-center justify-between border-b border-border/70 pb-3">
                                    <div>
                                        <CardTitle>Create / Edit Step</CardTitle>
                                        <p className="mt-1 text-[11px] text-muted-foreground">
                                            Configure the message and choices for this step.
                                        </p>
                                    </div>
                                    <div className="flex gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Edit step"
                                            onClick={() => {
                                                setEditingStep(true);
                                                setDialog('step');
                                            }}
                                        >
                                            <PencilIcon />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="text-danger hover:text-danger"
                                            title="Delete step"
                                            onClick={() => {
                                                if (window.confirm('Delete this step?'))
                                                    router.delete(`/support/chatbot-flows/steps/${selectedStep.id}`, {
                                                        preserveScroll: true,
                                                    });
                                            }}
                                        >
                                            <Trash2Icon />
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-5 overflow-y-auto">
                                    <div className="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                                        <div className="space-y-1.5">
                                            <FieldLabel required>Step Name</FieldLabel>
                                            <Input value={selectedStep.name} readOnly className="bg-muted/25" />
                                        </div>
                                        <label className="flex items-center gap-2 pb-2 text-xs">
                                            <input
                                                type="checkbox"
                                                checked={selectedStep.is_start}
                                                readOnly
                                                className="accent-primary"
                                            />{' '}
                                            Starting Step <span className="text-muted-foreground">ⓘ</span>
                                        </label>
                                    </div>
                                    <MessageFields messages={stepMessages} setMessages={() => undefined} />
                                    <div className="flex items-center justify-between border-b border-border/70 pb-2">
                                        <div>
                                            <h3 className="text-sm font-semibold">Options</h3>
                                            <p className="text-[11px] text-muted-foreground">
                                                Choose what the customer can do next.
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            onClick={() => {
                                                resetOption();
                                                setDialog('option');
                                            }}
                                        >
                                            <PlusIcon /> Add Option
                                        </Button>
                                    </div>
                                    <div className="divide-y rounded-md border">
                                        {selectedStep.options.length ? (
                                            selectedStep.options.map((option, index) => (
                                                <div key={option.id} className="flex flex-wrap items-center gap-3 p-3">
                                                    <span className="text-xs font-semibold text-muted-foreground">
                                                        {index + 1}
                                                    </span>
                                                    <div className="min-w-[130px] flex-1">
                                                        <p className="text-xs font-semibold">
                                                            {getText(option.translations, 'label', 'en') ||
                                                                'Untitled option'}
                                                        </p>
                                                        <p className="mt-1 text-[10px] text-muted-foreground">
                                                            EN: {getText(option.translations, 'label', 'en')} · MY:{' '}
                                                            {getText(option.translations, 'label', 'my')} · ZH:{' '}
                                                            {getText(option.translations, 'label', 'zh')}
                                                        </p>
                                                    </div>
                                                    <span className="rounded bg-primary/8 px-2 py-1 text-[10px] font-medium text-primary">
                                                        {option.action === 'go_to_step'
                                                            ? `Next: ${option.next_step?.name ?? 'Step'}`
                                                            : option.action === 'reply_text'
                                                              ? 'Reply text'
                                                              : option.action === 'go_to_url'
                                                                ? 'Open URL'
                                                                : 'Transfer to agent'}
                                                    </span>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Edit option"
                                                        onClick={() => editOption(option)}
                                                    >
                                                        <PencilIcon />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        title="Delete option"
                                                        onClick={() => {
                                                            if (window.confirm('Delete this option?'))
                                                                router.delete(
                                                                    `/support/chatbot-flows/steps/${selectedStep.id}/options/${option.id}`,
                                                                    { preserveScroll: true },
                                                                );
                                                        }}
                                                    >
                                                        <Trash2Icon className="text-danger" />
                                                    </Button>
                                                </div>
                                            ))
                                        ) : (
                                            <p className="p-6 text-center text-xs text-muted-foreground">
                                                No options configured for this step.
                                            </p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>
                )}
            </PageContent>
            {dialog ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-foreground/25 p-4">
                    <Card className="max-h-[90vh] w-full max-w-2xl overflow-y-auto">
                        <CardHeader className="flex flex-row items-start justify-between border-b border-border/70 pb-3">
                            <div>
                                <CardTitle>
                                    {dialog === 'step' ? (editingStep ? 'Edit Step' : 'Create New Step') : 'Add Option'}
                                </CardTitle>
                                <p className="mt-1 text-[11px] text-muted-foreground">
                                    All languages are required for a consistent customer experience.
                                </p>
                            </div>
                            <Button variant="ghost" size="icon" onClick={() => setDialog(null)}>
                                <XIcon />
                            </Button>
                        </CardHeader>
                        <CardContent className="pt-1">
                            {dialog === 'step' ? (
                                <form className="space-y-4" onSubmit={submitStep}>
                                    <div className="space-y-1.5">
                                        <FieldLabel required>Step Name</FieldLabel>
                                        <Input
                                            value={stepName}
                                            onChange={(event) => setStepName(event.target.value)}
                                            required
                                        />
                                    </div>
                                    <label className="flex items-center gap-2 text-xs">
                                        <input
                                            type="checkbox"
                                            checked={isStart}
                                            onChange={(event) => setIsStart(event.target.checked)}
                                            className="accent-primary"
                                        />{' '}
                                        Starting Step
                                    </label>
                                    <MessageFields messages={stepMessages} setMessages={setStepMessages} />
                                    <div className="flex justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => setDialog(null)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button size="sm">{editingStep ? 'Save Changes' : 'Create Step'}</Button>
                                    </div>
                                </form>
                            ) : (
                                <form className="space-y-4" onSubmit={submitOption}>
                                    <MessageFields
                                        label="Option label"
                                        messages={optionLabels}
                                        setMessages={setOptionLabels}
                                    />
                                    <div className="space-y-1.5">
                                        <FieldLabel required>Action</FieldLabel>
                                        <select
                                            value={action}
                                            onChange={(event) => setAction(event.target.value as Option['action'])}
                                            className="h-9 w-full rounded-md border border-border bg-background px-2 text-xs"
                                        >
                                            <option value="go_to_step">Go to Step</option>
                                            <option value="reply_text">Reply Text</option>
                                            <option value="go_to_url">Open URL</option>
                                            <option value="transfer_agent">Transfer Agent</option>
                                        </select>
                                    </div>
                                    {action === 'go_to_step' ? (
                                        <div className="space-y-1.5">
                                            <FieldLabel required>Next Step</FieldLabel>
                                            <select
                                                value={nextStepId}
                                                onChange={(event) => setNextStepId(event.target.value)}
                                                required
                                                className="h-9 w-full rounded-md border border-border bg-background px-2 text-xs"
                                            >
                                                <option value="">Select a step</option>
                                                {steps
                                                    .filter((step) => step.id !== selectedStep?.id)
                                                    .map((step) => (
                                                        <option key={step.id} value={step.id}>
                                                            {step.name}
                                                        </option>
                                                    ))}
                                            </select>
                                        </div>
                                    ) : null}
                                    {action === 'go_to_url' ? (
                                        <div className="space-y-1.5">
                                            <FieldLabel required>URL</FieldLabel>
                                            <Input
                                                value={url}
                                                onChange={(event) => setUrl(event.target.value)}
                                                type="url"
                                                required
                                            />
                                        </div>
                                    ) : null}
                                    {action === 'reply_text' ? (
                                        <MessageFields label="Reply text" messages={replies} setMessages={setReplies} />
                                    ) : null}
                                    {action === 'transfer_agent' ? (
                                        <div className="flex items-start gap-3 rounded-md border border-sky-200 bg-sky-50 p-3 text-sky-900">
                                            <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700">
                                                <HeadsetIcon className="size-4" />
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold">Transfer to a live agent</p>
                                                <p className="mt-1 text-[11px] leading-4 text-sky-800/80">
                                                    The conversation will be handed over to the support team in real
                                                    time.
                                                </p>
                                            </div>
                                        </div>
                                    ) : null}
                                    <div className="flex justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => setDialog(null)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button size="sm">Add Option</Button>
                                    </div>
                                </form>
                            )}
                        </CardContent>
                    </Card>
                </div>
            ) : null}
        </>
    );
}
