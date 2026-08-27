import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { HashIcon, RotateCcwIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { FormControl } from '@/components/ui/form-control';
import { Input } from '@/components/ui/input';
import { useMediaQuery } from '@/hooks/useMediaQuery';
import {
    DEFAULT_PRIMARY,
    PRIMARY_PRESETS,
    normalizeHex,
    swatchHsl,
    type BorderRadius,
    type CardStyle,
    type ShadowStyle,
} from '@/lib/theme-settings';
import { useThemeSettings } from '@/providers/ThemeSettingsProvider';
import { cn } from '@/lib/utils';

type ThemeSettingsPanelProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="flex flex-col gap-2">
            <h3 className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">{title}</h3>
            {children}
        </section>
    );
}

function OptionTile({
    selected,
    onClick,
    children,
}: {
    selected: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={selected}
            onClick={onClick}
            className={cn(
                'h-8 min-w-0 flex-1 rounded-[6px] px-1 text-[11px] font-medium transition-colors duration-200',
                selected
                    ? 'bg-primary text-primary-foreground hover:bg-[color-mix(in_srgb,hsl(var(--primary))_88%,black)]'
                    : 'bg-secondary text-secondary-foreground hover:bg-muted',
            )}
        >
            {children}
        </button>
    );
}

function HexColorField({
    value,
    onChange,
}: {
    value: string;
    onChange: (hex: string) => void;
}) {
    const [draft, setDraft] = useState(value);
    const valid = normalizeHex(draft);
    const preview = valid ?? normalizeHex(value) ?? DEFAULT_PRIMARY;

    useEffect(() => {
        setDraft(value);
    }, [value]);

    return (
        <div className="flex items-center gap-2">
            <label className="relative size-9 shrink-0 cursor-pointer overflow-hidden rounded-[6px] ring-1 ring-border">
                <span className="absolute inset-0" style={{ backgroundColor: swatchHsl(preview) }} />
                <input
                    type="color"
                    value={preview}
                    onChange={(event) => onChange(event.target.value.toLowerCase())}
                    className="absolute inset-0 cursor-pointer opacity-0"
                    aria-label="Pick primary color"
                />
            </label>
            <FormControl icon={HashIcon}>
                <Input
                    value={draft}
                    onChange={(event) => {
                        const next = event.target.value;
                        setDraft(next);
                        const hex = normalizeHex(next);

                        if (hex) {
                            onChange(hex);
                        }
                    }}
                    aria-invalid={draft.length > 0 && ! valid}
                    spellCheck={false}
                    maxLength={7}
                    placeholder={DEFAULT_PRIMARY}
                    className="font-mono text-xs tracking-wide"
                />
            </FormControl>
        </div>
    );
}

const cardStyleOptions: { value: CardStyle; label: string }[] = [
    { value: 'flat', label: 'Flat' },
    { value: 'bordered', label: 'Border' },
    { value: 'shadow', label: 'Shadow' },
    { value: 'elevated', label: 'Lift' },
];

const radiusOptions: { value: BorderRadius; label: string }[] = [
    { value: 'none', label: '0' },
    { value: 'sm', label: 'SM' },
    { value: 'md', label: 'MD' },
    { value: 'lg', label: 'LG' },
    { value: 'full', label: 'Full' },
];

const shadowOptions: { value: ShadowStyle; label: string }[] = [
    { value: 'none', label: 'Off' },
    { value: 'sm', label: 'SM' },
    { value: 'md', label: 'MD' },
    { value: 'lg', label: 'LG' },
    { value: 'glow', label: 'Glow' },
];

const PANEL_DURATION_MS = 300;
const REDUCE_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

export function ThemeSettingsPanel({ open, onOpenChange }: ThemeSettingsPanelProps) {
    const {
        primaryColor,
        cardStyle,
        borderRadius,
        shadowStyle,
        setPrimaryColor,
        setCardStyle,
        setBorderRadius,
        setShadowStyle,
        resetThemeSettings,
    } = useThemeSettings();
    const reduceMotion = useMediaQuery(REDUCE_MOTION_QUERY);
    const [mounted, setMounted] = useState(open);
    const [entered, setEntered] = useState(open);

    useEffect(() => {
        if (open) {
            setMounted(true);
            let inner = 0;
            const outer = window.requestAnimationFrame(() => {
                inner = window.requestAnimationFrame(() => setEntered(true));
            });

            return () => {
                window.cancelAnimationFrame(outer);
                window.cancelAnimationFrame(inner);
            };
        }

        setEntered(false);
        const timeout = window.setTimeout(() => setMounted(false), reduceMotion ? 0 : PANEL_DURATION_MS);

        return () => window.clearTimeout(timeout);
    }, [open, reduceMotion]);

    useEffect(() => {
        if (! mounted) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onOpenChange(false);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [mounted, onOpenChange]);

    useEffect(() => {
        if (! mounted) {
            return;
        }

        const main = document.querySelector('main');
        const previousMainOverflow = main instanceof HTMLElement ? main.style.overflow : '';

        if (main instanceof HTMLElement) {
            main.style.overflow = 'hidden';
        }

        return () => {
            if (main instanceof HTMLElement) {
                main.style.overflow = previousMainOverflow;
            }
        };
    }, [mounted]);

    if (! mounted || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <>
            <button
                type="button"
                aria-label="Close theme settings"
                className={cn(
                    'fixed inset-0 z-[100] bg-black/40',
                    'motion-reduce:transition-none transition-opacity duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
                    entered ? 'opacity-100' : 'opacity-0',
                )}
                onClick={() => onOpenChange(false)}
            />
            <aside
                role="dialog"
                aria-modal="true"
                aria-labelledby="theme-settings-title"
                className={cn(
                    'app-theme-panel fixed inset-y-0 right-0 z-[110] flex h-dvh w-[min(300px,100%)] max-w-[300px] flex-col overflow-hidden shadow-sidebar',
                    'motion-reduce:transition-none transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]',
                    entered ? 'translate-x-0' : 'translate-x-full',
                )}
            >
                <div className="shrink-0 px-4 pt-5 pb-4">
                    <p className="text-[10px] font-semibold tracking-[0.22em] text-primary uppercase">Customize</p>
                    <h2 id="theme-settings-title" className="font-heading mt-1 text-xl font-semibold tracking-tight text-foreground">
                        Theme
                    </h2>
                    <span className="mt-2 block h-1 w-9 rounded-[8px] bg-primary" />
                </div>

                <div className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto overscroll-contain px-4 pb-4">
                    <Section title="Color">
                        <div className="flex gap-1.5">
                            {PRIMARY_PRESETS.map((preset) => {
                                const selected = primaryColor === preset.hex;

                                return (
                                    <button
                                        key={preset.hex}
                                        type="button"
                                        title={preset.name}
                                        aria-label={preset.name}
                                        aria-pressed={selected}
                                        onClick={() => setPrimaryColor(preset.hex)}
                                        className={cn(
                                            'size-6 shrink-0 rounded-[8px] transition-all duration-200 ease-out',
                                            'hover:-translate-y-0.5 hover:shadow-sm active:translate-y-0',
                                            selected ? 'ring-2 ring-primary ring-offset-2 ring-offset-card' : 'ring-1 ring-border',
                                        )}
                                    >
                                        <span
                                            className="block size-full rounded-[8px]"
                                            style={{ backgroundColor: swatchHsl(preset.hex) }}
                                        />
                                    </button>
                                );
                            })}
                        </div>
                        <HexColorField value={primaryColor} onChange={setPrimaryColor} />
                    </Section>

                    <Section title="Card">
                        <div className="flex gap-1.5">
                            {cardStyleOptions.map((option) => (
                                <OptionTile
                                    key={option.value}
                                    selected={cardStyle === option.value}
                                    onClick={() => setCardStyle(option.value)}
                                >
                                    {option.label}
                                </OptionTile>
                            ))}
                        </div>
                    </Section>

                    <Section title="Radius">
                        <div className="flex gap-1.5">
                            {radiusOptions.map((option) => (
                                <OptionTile
                                    key={option.value}
                                    selected={borderRadius === option.value}
                                    onClick={() => setBorderRadius(option.value)}
                                >
                                    {option.label}
                                </OptionTile>
                            ))}
                        </div>
                    </Section>

                    <Section title="Shadow">
                        <div className="flex gap-1.5">
                            {shadowOptions.map((option) => (
                                <OptionTile
                                    key={option.value}
                                    selected={shadowStyle === option.value}
                                    onClick={() => setShadowStyle(option.value)}
                                >
                                    {option.label}
                                </OptionTile>
                            ))}
                        </div>
                    </Section>
                </div>

                <div className="shrink-0 px-4 py-3">
                    <Button
                        type="button"
                        variant="primary"
                        className="w-full"
                        onClick={resetThemeSettings}
                    >
                        <RotateCcwIcon />
                        Reset Default
                    </Button>
                </div>
            </aside>
        </>,
        document.body,
    );
}
