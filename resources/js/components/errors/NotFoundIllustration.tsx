import { cn } from '@/lib/utils';

import rawSvg from '@/assets/404-error-pana.svg?raw';

type NotFoundIllustrationProps = {
    className?: string;
};

const themedSvg = rawSvg
    .replaceAll('#FF725E', 'hsl(var(--primary))')
    .replaceAll('#ff9a6c', 'hsl(var(--primary) / 0.72)');

export function NotFoundIllustration({ className }: NotFoundIllustrationProps) {
    return (
        <div
            aria-hidden
            className={cn('[&>svg]:mx-auto [&>svg]:h-auto [&>svg]:max-h-full [&>svg]:w-full', className)}
            dangerouslySetInnerHTML={{ __html: themedSvg }}
        />
    );
}
