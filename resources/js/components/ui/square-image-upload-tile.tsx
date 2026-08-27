import { useId } from 'react';
import { ImageUpIcon, Trash2Icon, XIcon } from 'lucide-react';

import { RadialBubbleActions } from '@/components/data-table/RadialBubbleActions';
import { TableActionButton } from '@/components/TableActionButton';

import {
    browseButtonClass,
    imageUploadBoxStyle,
    takeImageFile,
    useSquareImagePreview,
    type SquareImageUploadProps,
} from '@/components/ui/square-image-upload';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

export function SquareImageUploadTile({
    id,
    accept = 'image/jpeg,image/png,image/webp',
    required = false,
    disabled = false,
    invalid = false,
    value,
    existingUrl,
    onChange,
    className,
    width = 160,
    height = 160,
    radialMenu = false,
}: SquareImageUploadProps) {
    const { t } = useTranslation();
    const generatedId = useId();
    const inputId = id ?? generatedId;
    const { inputRef, previewSrc, select, remove, openPicker } = useSquareImagePreview({ value, existingUrl, onChange });
    const boxStyle = imageUploadBoxStyle(width, height);

    const radialActions = (
        <RadialBubbleActions placement="end">
            <TableActionButton
                label={t('cms.browse_image')}
                icon={ImageUpIcon}
                tone="edit"
                onClick={(event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    openPicker();
                }}
            />
            <TableActionButton
                label={t('cms.remove_image')}
                icon={Trash2Icon}
                tone="danger"
                onClick={(event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    remove();
                }}
            />
        </RadialBubbleActions>
    );

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <div className="group relative min-w-0" style={boxStyle}>
            <label
                htmlFor={inputId}
                className={cn(
                    'relative flex size-full cursor-pointer flex-col items-center justify-center overflow-hidden rounded-[4px] text-center transition-colors duration-200',
                    previewSrc
                        ? 'bg-muted ring-1 ring-border'
                        : 'border-2 border-dashed border-primary/30 bg-primary/5 hover:border-primary hover:bg-primary/10',
                    'focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/25',
                    invalid && 'border-danger from-danger/12 focus-within:border-danger focus-within:ring-danger/25',
                    'group-data-[error=true]/field:border-danger',
                    disabled && 'pointer-events-none cursor-not-allowed opacity-70',
                )}
            >
                <input
                    ref={inputRef}
                    id={inputId}
                    type="file"
                    accept={accept}
                    required={required && ! previewSrc}
                    disabled={disabled}
                    aria-invalid={invalid}
                    className="sr-only"
                    onChange={(event) => select(takeImageFile(event.target.files))}
                />
                {previewSrc ? (
                    <img src={previewSrc} alt="" className="absolute inset-0 size-full object-cover object-center" />
                ) : (
                    <>
                        <ImageUpIcon className="size-5 text-primary" strokeWidth={1.6} />
                        <span className={cn('mt-2', browseButtonClass)}>{t('cms.browse_image')}</span>
                    </>
                )}
            </label>
            {previewSrc && ! radialMenu ? (
                <>
                    <div className="pointer-events-none absolute inset-0 rounded-[4px] bg-gradient-to-t from-black/50 via-black/10 to-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-within:opacity-100" />
                    <span className="pointer-events-none absolute inset-x-0 bottom-0 z-[1] flex justify-center px-2 pb-2 opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-within:opacity-100">
                        <span className={browseButtonClass}>{t('cms.browse_image')}</span>
                    </span>
                    <button
                        type="button"
                        disabled={disabled}
                        aria-label={t('cms.remove_image')}
                        className="absolute top-1.5 right-1.5 z-[1] inline-flex size-6 items-center justify-center rounded-[4px] bg-white text-danger opacity-0 shadow-sm ring-1 ring-black/10 transition-opacity duration-200 hover:bg-danger hover:text-danger-foreground group-hover:opacity-100 group-focus-within:opacity-100"
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            remove();
                        }}
                    >
                        <XIcon className="size-3.5" strokeWidth={2.4} />
                    </button>
                </>
            ) : null}
            </div>
            {previewSrc && radialMenu ? radialActions : null}
        </div>
    );
}
