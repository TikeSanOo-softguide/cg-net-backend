import type { LucideIcon } from 'lucide-react';

import { CopyValueButton } from '@/components/CopyValueButton';
import { cn } from '@/lib/utils';

type ServiceRequestInfoField = {
    label: string;
    value: string | null | undefined;
    copy?: string | null;
    copyLabel?: string;
    copyFormat?: 'phone';
    isCancelled?: boolean;
};

export function ServiceRequestSectionHeading({ icon: Icon, title }: { icon: LucideIcon; title: string }) {
    return (
        <div className="flex items-center gap-2">
            <div className="flex size-7 items-center justify-center rounded-full bg-primary/10">
                <Icon className="size-3.5 text-primary" />
            </div>
            <h3 className="text-xs font-semibold text-foreground">{title}</h3>
        </div>
    );
}

export function ServiceRequestInfoCard({
    icon: Icon,
    title,
    fields,
}: {
    icon: LucideIcon;
    title: string;
    fields: ServiceRequestInfoField[];
}) {
    return (
        <section className="rounded-xl border border-border/60 bg-muted/20 p-4">
            <div className="mb-3">
                <ServiceRequestSectionHeading icon={Icon} title={title} />
            </div>
            <div className="space-y-3">
                {fields.map((field) => {
                    const shown = field.value?.trim() || '—';

                    return (
                        <div key={field.label} className="min-w-0">
                            <p className="text-[11px] text-muted-foreground">{field.label}</p>
                            <div className="mt-0.5 flex min-w-0 items-center gap-1">
                                <p
                                    className={cn(
                                        'min-w-0 break-words text-sm font-medium text-foreground',
                                        field.isCancelled && 'select-none',
                                    )}
                                >
                                    {shown}
                                </p>
                                {field.copy && field.copy !== '—' && field.copyLabel && !field.isCancelled ? (
                                    <CopyValueButton
                                        value={field.copy}
                                        label={field.copyLabel}
                                        format={field.copyFormat}
                                    />
                                ) : null}
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
