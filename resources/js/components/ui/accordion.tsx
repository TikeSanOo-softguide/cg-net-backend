import { ChevronDownIcon } from 'lucide-react';
import { useId, useState } from 'react';
import type { ReactNode } from 'react';

type AccordionProps = {
    title: ReactNode;
    description?: ReactNode;
    children: ReactNode;
    defaultOpen?: boolean;
};

export function Accordion({ title, description, children, defaultOpen = false }: AccordionProps) {
    const [isExpanded, setIsExpanded] = useState(defaultOpen);
    const contentId = useId();

    return (
        <div>
            <button
                type="button"
                aria-expanded={isExpanded}
                aria-controls={contentId}
                onClick={() => setIsExpanded((expanded) => !expanded)}
                className="group flex w-full cursor-pointer items-center justify-between gap-4 border-b border-border/70 px-4 py-3.5 text-left transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring sm:px-5"
            >
                <span className="min-w-0">
                    <span className="block text-[14px] font-semibold text-foreground">{title}</span>
                    {description ? (
                        <span className="mt-0.5 block text-[12px] text-muted-foreground">{description}</span>
                    ) : null}
                </span>
                <ChevronDownIcon
                    aria-hidden="true"
                    className={`size-5 shrink-0 text-muted-foreground transition-[color,transform] duration-300 ease-in-out group-hover:text-primary group-focus-visible:text-primary motion-reduce:transition-none ${
                        isExpanded ? 'rotate-180' : ''
                    }`}
                />
            </button>
            <div
                id={contentId}
                aria-hidden={!isExpanded}
                inert={!isExpanded}
                data-open={isExpanded}
                className="grid grid-rows-[0fr] transition-[grid-template-rows] duration-300 ease-in-out data-[open=true]:grid-rows-[1fr] motion-reduce:transition-none"
            >
                <div className="min-h-0 overflow-hidden">
                    <div className="space-y-5 px-4 py-4 sm:px-5 sm:py-5">{children}</div>
                </div>
            </div>
        </div>
    );
}
