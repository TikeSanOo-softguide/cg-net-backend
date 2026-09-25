import { ImageOff } from 'lucide-react';

import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type NoImageProps = {
    width?: number | string;
    height?: number | string;
    aspectRatio?: string;
    className?: string;
};

export function NoImage({ width = 32, height = 32, aspectRatio, className }: NoImageProps) {
    const { t } = useTranslation();

    return (
        <span
            className={cn(
                'inline-flex shrink-0 flex-col items-center justify-center gap-0.5 rounded border border-border bg-muted px-1 text-center text-[12px] leading-tight text-muted-foreground',
                className,
            )}
            style={{ width, height: aspectRatio ? undefined : height, aspectRatio }}
            role="img"
            aria-label={t('cms.no_image')}
        >
            <ImageOff className="size-5" aria-hidden="true" />
            <span className="mt-3">{t('cms.no_image')}</span>
        </span>
    );
}
