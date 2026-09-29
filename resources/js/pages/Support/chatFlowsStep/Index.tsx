import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    BotIcon,
    ChevronRightIcon,
    ExternalLinkIcon,
    EyeIcon,
    PlusIcon,
    SearchIcon,
    SquarePenIcon,
    Trash2Icon,
    XIcon,
} from 'lucide-react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { FormDialog } from '@/components/FormDialog';
import { formActionBarClass, formActionButtonClass, formActionSubmitClass } from '@/components/FormActionBar';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { StatusBadge } from '@/components/StatusBadge';
import { TableActionButton } from '@/components/TableActionButton';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { FieldLabel, MessageFields } from '@/components/support/chat-flow-step/ChatFlowStepFields';
import { OptionFormDialog } from '@/components/support/chat-flow-step/OptionFormDialog';
import { StepFormDialog } from '@/components/support/chat-flow-step/StepFormDialog';
import type { ChatFlowActionOption, Option, Step } from '@/components/support/chat-flow-step/ChatFlowStepTypes';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import { useCan } from '@/hooks/useCan';

export default function ChatFlowsIndex() {
    const { t } = useTranslation();
    const { steps, actionOptions } = usePage<{
        steps: Step[];
        actionOptions: ChatFlowActionOption[];
    }>().props;

    const [selectedId, setSelectedId] = useState<number | null>(steps[0]?.id ?? null);
    const [stepSearch, setStepSearch] = useState('');
    const [stepDialogOpen, setStepDialogOpen] = useState(false);
    const [optionDialogOpen, setOptionDialogOpen] = useState(false);
    const [editingStep, setEditingStep] = useState<Step | null>(null);
    const [editingOption, setEditingOption] = useState<Option | null>(null);
    const [viewingOption, setViewingOption] = useState<Option | null>(null);
    const [deleting, setDeleting] = useState<{ type: 'step' | 'option'; id: number } | null>(null);
    const selectedStep = steps.find((step) => step.id === selectedId) ?? null;
    const filteredSteps = steps.filter((step) => step.name.toLowerCase().includes(stepSearch.trim().toLowerCase()));

    const searchSteps = (value: string) => {
        setStepSearch(value);
        const normalizedSearch = value.trim().toLowerCase();
        const matchingSteps = steps.filter((step) => step.name.toLowerCase().includes(normalizedSearch));

        if (matchingSteps.length > 0 && !matchingSteps.some((step) => step.id === selectedId)) {
            setSelectedId(matchingSteps[0].id);
        }
    };

    const editOption = (option: Option) => {
        setEditingOption(option);
        setOptionDialogOpen(true);
    };

    const openNewStep = () => {
        setEditingStep(null);
        setStepDialogOpen(true);
    };

    return (
        <>
            <Head title={t('menu.chatbot_flows')} />
            <PageContent className="min-h-0 flex-1 overflow-hidden">
                <PageHeader
                    title={t('menu.chatbot_flows')}
                    description={t('menu.chatbot_flows_description')}
                    actions={
                        steps.length > 0 ? (
                            <Button size="sm" onClick={openNewStep}>
                                <PlusIcon />
                                {t('support.chatbot_flows.create_step')}
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
                                <h2 className="font-heading text-base font-semibold">
                                    {t('support.chatbot_flows.no_step')}
                                </h2>

                                <p className="mt-1 text-xs text-muted-foreground">
                                    {t('support.chatbot_flows.no_step_description')}
                                </p>
                            </div>

                            <Button size="sm" onClick={openNewStep}>
                                <PlusIcon />
                                {t('support.chatbot_flows.add_first_step')}
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid h-[calc(100vh-14rem)] min-h-0 gap-4 lg:grid-cols-[225px_minmax(0,1fr)]">
                        {/* Step list */}
                        <Card className="flex h-full min-h-0 flex-col gap-3 overflow-hidden">
                            <CardHeader className="border-b border-border/70 pb-3">
                                <CardTitle>{t('support.chatbot_flows.step_list')}</CardTitle>

                                <p className="text-[11px] text-muted-foreground">
                                    {t('support.chatbot_flows.total_steps').replace('{count}', String(steps.length))}
                                </p>
                                <div className="relative mt-3">
                                    <SearchIcon className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={stepSearch}
                                        onChange={(event) => searchSteps(event.target.value)}
                                        placeholder={t('support.chatbot_flows.search_steps')}
                                        aria-label={t('support.chatbot_flows.search_steps')}
                                        className="h-9 pl-9 text-xs"
                                    />
                                </div>
                            </CardHeader>

                            <CardContent className="min-h-0 flex-1 overflow-y-auto px-3">
                                <div className="space-y-1">
                                    {filteredSteps.length > 0 ? (
                                        filteredSteps.map((step, index) => (
                                            <button
                                                key={step.id}
                                                type="button"
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
                                                                {t('support.chatbot_flows.start')}
                                                            </span>
                                                        ) : null}
                                                    </span>

                                                    <span className="mt-1 block text-[10px] text-muted-foreground">
                                                        {step.options.length}{' '}
                                                        {step.options.length <= 1 ? 'option' : 'options'}
                                                    </span>
                                                </span>

                                                <ChevronRightIcon className="mt-1 size-3.5 text-muted-foreground" />
                                            </button>
                                        ))
                                    ) : (
                                        <p className="px-2 py-5 text-center text-xs text-muted-foreground">
                                            {t('support.chatbot_flows.no_matching_steps')}
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {selectedStep ? (
                            <Card className="flex h-full min-h-0 flex-col gap-4 overflow-hidden">
                                <CardHeader className="flex flex-row items-center justify-between border-b border-border/70 pb-3">
                                    <div>
                                        <CardTitle>{t('support.chatbot_flows.step_overview')}</CardTitle>

                                        <p className="mt-1 text-[11px] text-muted-foreground">
                                            {t('support.chatbot_flows.step_description')}
                                        </p>
                                    </div>

                                    <div className="flex gap-1">
                                        <TableActionButton
                                            label={t('common.edit')}
                                            icon={SquarePenIcon}
                                            tone="edit"
                                            onClick={() => {
                                                setEditingStep(selectedStep);
                                                setStepDialogOpen(true);
                                            }}
                                        />
                                        <TableActionButton
                                            label={t('common.delete')}
                                            icon={Trash2Icon}
                                            tone="danger"
                                            onClick={() => {
                                                setDeleting({ type: 'step', id: selectedStep.id });
                                            }}
                                        />
                                    </div>
                                </CardHeader>

                                <CardContent className="space-y-5 overflow-y-auto">
                                    <div className="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                                        <div className="space-y-1.5">
                                            <FieldLabel required>{t('support.chatbot_flows.step_name')}</FieldLabel>

                                            <Input value={selectedStep.name} readOnly className="bg-muted/25" />
                                        </div>

                                        <label className="flex items-center gap-2 pb-2 text-xs">
                                            <input
                                                type="checkbox"
                                                checked={selectedStep.is_start}
                                                readOnly
                                                className="accent-primary"
                                            />
                                            {t('support.chatbot_flows.start_step')}
                                            <span className="text-muted-foreground">ⓘ</span>
                                        </label>
                                    </div>

                                    <MessageFields
                                        label={t('support.chatbot_flows.messages')}
                                        messages={{
                                            en: selectedStep.message_en,
                                            my: selectedStep.message_my,
                                            zh: selectedStep.message_zh,
                                        }}
                                        setMessages={() => undefined}
                                    />

                                    <div className="flex items-center justify-between border-b border-border/70 pb-2">
                                        <div>
                                            <h3 className="text-sm font-semibold">
                                                {t('support.chatbot_flows.options')}
                                            </h3>

                                            <p className="text-[11px] text-muted-foreground">
                                                {t('support.chatbot_flows.options_description')}
                                            </p>
                                        </div>

                                        <Button
                                            size="sm"
                                            onClick={() => {
                                                setEditingOption(null);
                                                setOptionDialogOpen(true);
                                            }}
                                        >
                                            <PlusIcon />
                                            {t('support.chatbot_flows.create_option')}
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
                                                        <p className="text-xs font-semibold">{option.option_en}</p>

                                                        <p className="mt-1 text-[10px] text-muted-foreground">
                                                            EN: {option.option_en} · MY: {option.option_my} · ZH:{' '}
                                                            {option.option_zh}
                                                        </p>
                                                    </div>

                                                    <span className="rounded bg-primary/8 px-2 py-1 text-[10px] font-medium text-primary">
                                                        {option.action === 'go_to_step'
                                                            ? `Next: ${option.next_step?.name ?? 'Step'}`
                                                            : (actionOptions.find(
                                                                  (actionOption) =>
                                                                      actionOption.value === option.action,
                                                              )?.label ?? option.action)}
                                                    </span>

                                                    <TableActionButton
                                                        label={t('common.view')}
                                                        icon={EyeIcon}
                                                        tone="primary"
                                                        onClick={() => setViewingOption(option)}
                                                    />
                                                    <TableActionButton
                                                        label={t('common.edit')}
                                                        icon={SquarePenIcon}
                                                        tone="edit"
                                                        onClick={() => editOption(option)}
                                                    />
                                                    <TableActionButton
                                                        label={t('common.delete')}
                                                        icon={Trash2Icon}
                                                        tone="danger"
                                                        onClick={() => {
                                                            setDeleting({ type: 'option', id: option.id });
                                                        }}
                                                    />
                                                </div>
                                            ))
                                        ) : (
                                            <p className="p-6 text-center text-xs text-muted-foreground">
                                                {t('support.chatbot_flows.no_option')}
                                            </p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>
                )}
            </PageContent>

            <StepFormDialog
                open={stepDialogOpen}
                onOpenChange={(open) => {
                    setStepDialogOpen(open);
                    if (!open) setEditingStep(null);
                }}
                step={editingStep}
                existingStepIds={steps.map((step) => step.id)}
                onCreated={setSelectedId}
            />
            <OptionFormDialog
                open={optionDialogOpen}
                onOpenChange={(open) => {
                    setOptionDialogOpen(open);
                    if (!open) setEditingOption(null);
                }}
                step={selectedStep}
                steps={steps}
                actionOptions={actionOptions}
                option={editingOption}
            />
            <OptionDetailDialog
                open={viewingOption !== null}
                onOpenChange={(open) => {
                    if (!open) setViewingOption(null);
                }}
                option={viewingOption}
                step={selectedStep}
                steps={steps}
                actionOptions={actionOptions}
                onEdit={(option) => {
                    setViewingOption(null);
                    editOption(option);
                }}
            />
            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleting(null);
                }}
                title={
                    deleting?.type === 'step'
                        ? t('support.chatbot_flows.delete_step')
                        : t('support.chatbot_flows.delete_option')
                }
                description={
                    deleting?.type === 'step'
                        ? t('support.chatbot_flows.delete_step_description')
                        : t('support.chatbot_flows.delete_option_description')
                }
                destructive
                confirmLabel={t('common.delete')}
                onConfirm={() => {
                    if (!deleting || !selectedStep) return;
                    const url =
                        deleting.type === 'step'
                            ? `/support/chatbot-flows/steps/${deleting.id}`
                            : `/support/chatbot-flows/steps/${selectedStep.id}/options/${deleting.id}`;
                    router.delete(url, {
                        preserveScroll: true,
                        onSuccess: (page) => {
                            if (deleting.type === 'step') {
                                const remainingSteps = Array.isArray(page.props.steps)
                                    ? (page.props.steps as Step[])
                                    : steps.filter((step) => step.id !== deleting.id);
                                const deletedIndex = steps.findIndex((step) => step.id === deleting.id);
                                const previousStepId = steps[deletedIndex - 1]?.id;
                                const nextSelectedStep =
                                    remainingSteps.find((step) => step.id === previousStepId) ?? remainingSteps[0];

                                setSelectedId(nextSelectedStep?.id ?? null);
                            }
                        },
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </>
    );
}

function OptionDetailDialog({
    open,
    onOpenChange,
    option,
    step,
    steps,
    actionOptions,
    onEdit,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    option: Option | null;
    step: Step | null;
    steps: Step[];
    actionOptions: ChatFlowActionOption[];
    onEdit: (option: Option) => void;
}) {
    const { t } = useTranslation();
    const can = useCan();

    if (!open || !option) return null;

    const actionLabel =
        actionOptions.find((actionOption) => actionOption.value === option.action)?.label ?? option.action;
    const nextStepName =
        option.next_step?.name ?? steps.find((availableStep) => availableStep.id === option.next_step_id)?.name ?? '—';

    const languageFields = [
        { label: 'language.en', value: option.option_en || '—' },
        { label: 'language.my', value: option.option_my || '—' },
        { label: 'language.zh', value: option.option_zh || '—' },
    ];

    const replyFields = [
        { label: 'language.en', value: option.reply_text_en || '—' },
        { label: 'language.my', value: option.reply_text_my || '—' },
        { label: 'language.zh', value: option.reply_text_zh || '—' },
    ];

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('support.chatbot_flows.option_details')}
            description={t('support.chatbot_flows.option_description')}
            icon={EyeIcon}
            size="lg"
        >
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 sm:py-5">
                <div className="space-y-4">
                    <div className="rounded-xl border border-border/80 bg-white/70 p-4 shadow-sm dark:bg-slate-900/20">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-1">
                                <p className="text-[11px] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                                    {t('support.chatbot_flows.general_information')}
                                </p>
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <span className="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-medium text-primary">
                                    {actionLabel}
                                </span>
                                <StatusBadge
                                    status={option.is_active ? 'active' : 'inactive'}
                                    className="text-[11px]"
                                />
                            </div>
                        </div>

                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            <div className="rounded-lg border border-border/70 bg-muted/20 p-3">
                                <p className="text-[10px] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                                    {t('support.chatbot_flows.position')}
                                </p>
                                <p className="mt-2 text-sm font-semibold">#{option.sort_order}</p>
                            </div>
                            <div className="rounded-lg border border-border/70 bg-muted/20 p-3">
                                <p className="text-[10px] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                                    {t('support.chatbot_flows.parent_step')}
                                </p>
                                <p className="mt-2 text-sm font-semibold">{step?.name ?? '—'}</p>
                            </div>
                            <div className="rounded-lg border border-border/70 bg-muted/20 p-3">
                                <p className="text-[10px] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                                    {t('support.chatbot_flows.target')}
                                </p>
                                <p className="mt-2 text-sm font-semibold">
                                    {option.action === 'go_to_step' ? nextStepName : actionLabel}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        {languageFields.map((field) => (
                            <div
                                key={t(`${field.label}`)}
                                className="rounded-lg border border-border/70 bg-muted/20 p-3"
                            >
                                <p className="text-[10px] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                                    {t(`${field.label}`)}
                                </p>
                                <p className="mt-2 text-sm leading-6 text-foreground">{field.value}</p>
                            </div>
                        ))}
                    </div>

                    {option.action === 'go_to_step' ? (
                        <div className="rounded-xl border border-border/80 bg-emerald-50/60 p-4 dark:bg-emerald-950/20">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-[10px] font-medium tracking-[0.12em] text-emerald-700 uppercase dark:text-emerald-300">
                                        {t('support.chatbot_flows.next_step')}
                                    </p>
                                    <p className="mt-2 text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                                        {nextStepName}
                                    </p>
                                </div>
                                <div className="rounded-full bg-emerald-100 p-2 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200">
                                    <ChevronRightIcon className="size-4" strokeWidth={2} />
                                </div>
                            </div>
                        </div>
                    ) : null}

                    {option.action === 'go_to_url' && option.url ? (
                        <div className="rounded-xl border border-border/80 bg-sky-50/60 p-4 dark:bg-sky-950/20">
                            <p className="text-[10px] font-medium tracking-[0.12em] text-sky-700 uppercase dark:text-sky-300">
                                {t('support.chatbot_flows.url')}
                            </p>
                            <a
                                href={option.url}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-2 inline-flex items-center gap-2 break-all text-sm font-medium text-sky-700 underline underline-offset-4 dark:text-sky-200"
                            >
                                {option.url}
                                <ExternalLinkIcon className="size-3.5 shrink-0" strokeWidth={1.9} />
                            </a>
                        </div>
                    ) : null}

                    {option.action === 'reply_text' ? (
                        <div className="space-y-3 rounded-xl border border-border/80 bg-muted/20 p-4">
                            <p className="text-[10px] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                                {t('support.chatbot_flows.reply_text')}
                            </p>
                            <div className="grid gap-3 sm:grid-cols-3">
                                {replyFields.map((field) => (
                                    <div
                                        key={t(`${field.label}`)}
                                        className="rounded-lg border border-border/70 bg-background/70 p-3"
                                    >
                                        <p className="text-[10px] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                                            {t(`${field.label}`)}
                                        </p>
                                        <p className="mt-2 text-sm leading-6 text-foreground">{field.value}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : null}
                </div>
            </div>

            {/* <FormActionBar onCancel={() => onOpenChange(false)}>
                <Button type="button" size="sm" variant="primary" onClick={() => onEdit(option)}>
                    <SquarePenIcon className="size-3.5" strokeWidth={1.85} />
                    {t('common.edit')}
                </Button>
            </FormActionBar> */}
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
                {can('chatbot-flows.update') && onEdit ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className={formActionSubmitClass}
                        onClick={() => onEdit(option)}
                    >
                        <SquarePenIcon className="size-3.5" strokeWidth={1.85} />
                        {t('common.edit')}
                    </Button>
                ) : null}
            </div>
        </FormDialog>
    );
}
