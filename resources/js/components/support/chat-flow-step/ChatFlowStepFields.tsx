import type { ReactNode } from 'react';

import type { LanguageMessages } from '@/components/support/chat-flow-step/ChatFlowStepTypes';
import { FileTextIcon } from 'lucide-react';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import { formControlStateClass } from '@/lib/form-control';
import { cn } from '@/lib/utils';

type LanguageField = keyof LanguageMessages;

export const languages = [
    { key: 'en', label: 'language.en' },
    { key: 'my', label: 'language.my' },
    { key: 'zh', label: 'language.zh' },
] as const;

export function FieldLabel({ children, required = false }: { children: ReactNode; required?: boolean }) {
    return (
        <label className="block text-[12px] font-semibold text-foreground">
            {children}
            {required ? <span className="ml-0.5 text-danger">*</span> : null}
        </label>
    );
}

type MessageFieldsProps = {
    messages: LanguageMessages;
    setMessages: (messages: LanguageMessages, changedLanguage?: LanguageField) => void;
    errors?: Partial<Record<LanguageField, string>>;
    fieldStates?: Partial<Record<LanguageField, 'idle' | 'error' | 'success'>>;
    onBlur?: (language: LanguageField) => void;
    multiline?: boolean;
    label?: string;
    required?: boolean;
};

export function MessageFields({
    messages,
    setMessages,
    errors = {},
    fieldStates = {},
    onBlur,
    multiline = true,
    label = '',
    required = true,
}: MessageFieldsProps) {
    const { t } = useTranslation();
    return (
        <div className="space-y-2">
            <FieldLabel required={required}>{label}</FieldLabel>
            <div className="grid gap-2 md:grid-cols-3">
                {languages.map((language) => (
                    <div key={language.key} className="space-y-1">
                        {(() => {
                            const fieldId = `${label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}-${language.key}`;
                            const fieldState = fieldStates[language.key];
                            const stateClass =
                                fieldState && fieldState !== 'idle' ? formControlStateClass(fieldState) : undefined;
                            const isInvalid = fieldState === 'error' || Boolean(errors[language.key]);

                            return (
                                <FormField
                                    label={t(`${language.label}`)}
                                    htmlFor={fieldId}
                                    error={errors[language.key]}
                                    required={required}
                                    icon={multiline ? FileTextIcon : undefined}
                                >
                                    {multiline ? (
                                        <Textarea
                                            id={fieldId}
                                            className={cn(
                                                'h-40 w-full resize-y rounded-md border border-border bg-background px-2.5 py-2 text-xs outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15',
                                                stateClass,
                                            )}
                                            aria-invalid={isInvalid}
                                            value={messages[language.key]}
                                            onBlur={() => onBlur?.(language.key)}
                                            onChange={(event) =>
                                                setMessages(
                                                    {
                                                        ...messages,
                                                        [language.key]: event.target.value,
                                                    },
                                                    language.key,
                                                )
                                            }
                                        />
                                    ) : (
                                        <Input
                                            id={fieldId}
                                            className={stateClass}
                                            aria-invalid={isInvalid}
                                            value={messages[language.key]}
                                            onBlur={() => onBlur?.(language.key)}
                                            onChange={(event) =>
                                                setMessages(
                                                    {
                                                        ...messages,
                                                        [language.key]: event.target.value,
                                                    },
                                                    language.key,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                            );
                        })()}
                    </div>
                ))}
            </div>
        </div>
    );
}
