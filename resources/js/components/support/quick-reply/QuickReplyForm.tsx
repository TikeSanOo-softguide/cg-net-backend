import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';

import { CmsFormShell } from '@/components/cms/shared/CmsFormShell';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';

export type QuickReplyFormValues = {
    keyword: string;
    response_en: string;
    response_my: string;
    response_zh: string;
};

type QuickReplyFormProps = {
    form: InertiaFormProps<QuickReplyFormValues>;
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    mode?: 'create' | 'edit';
};

export function QuickReplyForm({ form, onSubmit, onCancel, mode = 'create' }: QuickReplyFormProps) {
    return (
        <CmsFormShell onSubmit={onSubmit} onCancel={onCancel} processing={form.processing} mode={mode}>
            <FormField label="Keyword" htmlFor="keyword" error={form.errors.keyword} required>
                <Input
                    id="keyword"
                    value={form.data.keyword}
                    onChange={(event) => {
                        form.setData('keyword', event.target.value);
                        form.clearErrors('keyword');
                    }}
                    placeholder="Keyword"
                    maxLength={50}
                />
            </FormField>

            {(['en', 'my', 'zh'] as const).map((language) => {
                const field = `response_${language}` as keyof QuickReplyFormValues;
                return (
                    <FormField
                        key={language}
                        label={`Response (${language.toUpperCase()})`}
                        htmlFor={field}
                        error={form.errors[field]}
                        required
                    >
                        <textarea
                            id={field}
                            value={form.data[field]}
                            onChange={(event) => {
                                form.setData(field, event.target.value);
                                form.clearErrors(field);
                            }}
                            placeholder={`Response (${language.toUpperCase()})`}
                            rows={3}
                            className="flex w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm outline-none transition-shadow placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                        />
                    </FormField>
                );
            })}
        </CmsFormShell>
    );
}
