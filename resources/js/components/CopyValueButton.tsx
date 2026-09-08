import { useState } from 'react';
import { CheckIcon, CopyIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';

type CopyValueButtonProps = {
    value: string | null | undefined;
    label: string;
};

export function CopyValueButton({ value, label }: CopyValueButtonProps) {
    const [copied, setCopied] = useState(false);

    const copyValue = async () => {
        if (!value) {
            return;
        }

        await navigator.clipboard.writeText(value);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1200);
    };

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-6 min-h-6 min-w-6 rounded-[5px] text-muted-foreground hover:text-primary"
            aria-label={label}
            title={label}
            disabled={!value}
            onClick={() => void copyValue()}
        >
            {copied ? <CheckIcon className="size-3.5 text-emerald-600" /> : <CopyIcon className="size-3.5" />}
        </Button>
    );
}
