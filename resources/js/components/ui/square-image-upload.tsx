import { type CSSProperties, type DragEvent, useEffect, useId, useRef, useState } from 'react';
import { CloudUploadIcon, ImageUpIcon, Trash2Icon, XIcon } from 'lucide-react';

import { RadialBubbleActions } from '@/components/data-table/RadialBubbleActions';
import { TableActionButton } from '@/components/TableActionButton';

import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

export type SquareImageUploadProps = {
    id?: string;
    accept?: string;
    required?: boolean;
    disabled?: boolean;
    invalid?: boolean;
    value?: File | null;
    existingUrl?: string | null;
    onChange: (file: File | null) => void;
    className?: string;
    width?: number | string;
    height?: number | string;
    radialMenu?: boolean;
};

export function toUploadSize(value?: number | string): string | undefined {
    if (value == null) {
        return undefined;
    }

    return typeof value === 'number' ? `${value}px` : value;
}

export function imageUploadBoxStyle(width?: number | string, height?: number | string): CSSProperties {
    const widthValue = toUploadSize(width);
    const heightValue = toUploadSize(height);

    return {
        width: widthValue ?? '100%',
        maxWidth: '100%',
        height: heightValue,
        aspectRatio: heightValue ? undefined : '1 / 1',
    };
}

export const browseButtonClass =
    'inline-flex h-7 items-center justify-center rounded-[4px] bg-primary px-2.5 text-[11px] font-medium text-primary-foreground shadow-sm';

export function takeImageFile(files: FileList | null): File | null {
    const file = files?.[0];

    if (! file) {
        return null;
    }

    if (file.type.startsWith('image/')) {
        return file;
    }

    return /\.(jpe?g|png|webp|gif|avif)$/i.test(file.name) ? file : null;
}

export function useSquareImagePreview({
    value,
    existingUrl,
    onChange,
}: Pick<SquareImageUploadProps, 'value' | 'existingUrl' | 'onChange'>) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(value ?? null);
    const [objectUrl, setObjectUrl] = useState<string | null>(null);
    const [dismissedExisting, setDismissedExisting] = useState(false);

    useEffect(() => {
        if (value !== undefined) {
            setFile(value);
        }
    }, [value]);

    useEffect(() => {
        if (! file) {
            setObjectUrl(null);

            return;
        }

        const url = URL.createObjectURL(file);
        setObjectUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const previewSrc = objectUrl ?? (dismissedExisting ? null : existingUrl) ?? null;

    const select = (next: File | null) => {
        setFile(next);

        if (next) {
            setDismissedExisting(false);
        }

        if (! next && inputRef.current) {
            inputRef.current.value = '';
        }

        onChange(next);
    };

    const remove = () => {
        if (file) {
            select(null);

            return;
        }

        setDismissedExisting(true);
        onChange(null);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    return {
        inputRef,
        previewSrc,
        select,
        remove,
        openPicker: () => inputRef.current?.click(),
    };
}

export function SquareImageUpload({
    id,
    accept = 'image/jpeg,image/png,image/webp',
    required = false,
    disabled = false,
    invalid = false,
    value,
    existingUrl,
    onChange,
    className,
    width = 520,
    height,
    radialMenu = false,
}: SquareImageUploadProps) {
    const { t } = useTranslation();
    const generatedId = useId();
    const inputId = id ?? generatedId;
    const { inputRef, previewSrc, select, remove, openPicker } = useSquareImagePreview({ value, existingUrl, onChange });
    const [dragging, setDragging] = useState(false);
    const boxStyle = imageUploadBoxStyle(width, height);

    const onDragOver = (event: DragEvent<HTMLLabelElement>) => {
        event.preventDefault();

        if (! disabled) {
            setDragging(true);
        }
    };

    const onDrop = (event: DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        setDragging(false);

        if (disabled) {
            return;
        }

        const next = takeImageFile(event.dataTransfer.files);

        if (next) {
            select(next);
        }
    };

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
        <div className={cn('flex items-center gap-3', className)}>
            <div className="min-w-0" style={boxStyle}>
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
                <div className="group relative size-full overflow-hidden rounded-[10px] bg-muted shadow-[0_8px_24px_rgb(23_50_54/0.08)] ring-1 ring-border dark:shadow-[0_8px_24px_rgb(0_0_0/0.28)]">
                    <img src={previewSrc} alt="" className="absolute inset-0 size-full object-cover object-center" />
                    {radialMenu ? null : (
                        <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/55 via-black/10 to-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-within:opacity-100" />
                    )}
                    {radialMenu ? null : (
                        <button
                            type="button"
                            disabled={disabled}
                            aria-label={t('cms.remove_image')}
                            className="absolute top-3 right-3 z-[2] inline-flex size-8 items-center justify-center rounded-[8px] bg-white text-danger opacity-0 shadow-md ring-1 ring-black/10 transition-all duration-200 hover:bg-danger hover:text-danger-foreground group-hover:opacity-100 group-focus-within:opacity-100"
                            onClick={(event) => {
                                event.preventDefault();
                                event.stopPropagation();
                                remove();
                            }}
                        >
                            <XIcon className="size-4" strokeWidth={2.4} />
                        </button>
                    )}
                    <label
                        htmlFor={inputId}
                        className={cn('absolute inset-0 z-[1] cursor-pointer', disabled && 'pointer-events-none')}
                    >
                        <span className="sr-only">{t('cms.browse_image')}</span>
                    </label>
                    {radialMenu ? null : (
                        <span className="pointer-events-none absolute inset-x-0 bottom-0 z-[1] flex justify-center px-3 pb-3 opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-within:opacity-100">
                            <span className={browseButtonClass}>{t('cms.browse_image')}</span>
                        </span>
                    )}
                </div>
            ) : (
                <label
                    htmlFor={inputId}
                    className={cn(
                        'flex size-full cursor-pointer flex-col items-center justify-center rounded-[10px] border-2 border-dashed px-6 py-6 text-center transition-all duration-200',
                        'border-primary/35 bg-[linear-gradient(180deg,hsl(var(--primary)/0.08),hsl(var(--primary)/0.02))]',
                        'hover:border-primary hover:bg-primary/10 hover:shadow-[0_10px_28px_rgb(23_50_54/0.08)]',
                        dragging && 'scale-[1.01] border-primary bg-primary/14 shadow-[0_12px_32px_hsl(var(--primary)/0.18)]',
                        invalid && 'border-danger bg-danger/8 hover:border-danger',
                        'group-data-[error=true]/field:border-danger',
                        disabled && 'pointer-events-none opacity-70',
                    )}
                    onDragOver={onDragOver}
                    onDragLeave={() => setDragging(false)}
                    onDrop={onDrop}
                >
                    <span className="flex size-10 items-center justify-center rounded-full bg-primary/12 text-primary shadow-[0_0_0_6px_hsl(var(--primary)/0.06)]">
                        <CloudUploadIcon className="size-5" strokeWidth={1.6} />
                    </span>
                    <p className="mt-2.5 text-[12px] font-medium leading-5 text-foreground">{t('cms.drag_drop_image')}</p>
                    <p className="mt-0.5 text-[10px] leading-4 text-muted-foreground">{t('cms.image_upload_hint')}</p>
                    <span className={cn('mt-2.5', browseButtonClass)}>{t('cms.browse_image')}</span>
                </label>
            )}
            </div>
            {previewSrc && radialMenu ? radialActions : null}
        </div>
    );
}
