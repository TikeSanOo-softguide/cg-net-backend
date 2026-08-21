import { cn } from '@/lib/utils';

import rawSvg from '@/assets/online-world-amico.svg?raw';

type LoginIllustrationProps = {
    className?: string;
};

const themedSvg = rawSvg.replaceAll('#173236', 'hsl(var(--primary))');

export function LoginIllustration({ className }: LoginIllustrationProps) {
    return (
        <div
            aria-hidden
            className={cn('[&>svg]:mx-auto [&>svg]:h-auto [&>svg]:max-h-full [&>svg]:w-full', className)}
            dangerouslySetInnerHTML={{ __html: themedSvg }}
        />
    );
}
