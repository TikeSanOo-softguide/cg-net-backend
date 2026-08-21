import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
                'h-8 min-w-0 flex-1 rounded-[8px] px-1 text-[11px] font-medium transition-all duration-200 ease-out',
                'hover:-translate-y-0.5 hover:shadow-sm active:translate-y-0',
                selected
                    ? 'bg-primary text-primary-foreground shadow-sm hover:bg-primary-hover'
                    : 'bg-muted text-foreground hover:bg-primary/12',
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
            <label className="relative size-9 shrink-0 cursor-pointer overflow-hidden rounded-[8px] ring-1 ring-border">
                <span className="absolute inset-0" style={{ backgroundColor: swatchHsl(preview) }} />
                <input
                    type="color"
                    value={preview}
                    onChange={(event) => onChange(event.target.value.toLowerCase())}
                    className="absolute inset-0 cursor-pointer opacity-0"
                    aria-label="Pick primary color"
                />
            </label>
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
                className="h-9 rounded-[8px] font-mono text-xs tracking-wide"
            />
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

    useEffect(() => {
        if (! open) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onOpenChange(false);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open, onOpenChange]);

    if (! open || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <>
            <button
                type="button"
                aria-label="Close theme settings"
                className="fixed z-[80] bg-black/25"
                style={{
                    top: 'var(--app-navbar-current, 0px)',
                    left: 'var(--app-sidebar-current, 0px)',
                    right: 0,
                    bottom: 0,
                }}
                onClick={() => onOpenChange(false)}
            />
            <aside
                role="dialog"
                aria-modal="true"
                aria-labelledby="theme-settings-title"
                className="fixed z-[81] flex w-[min(300px,calc(100vw-var(--app-sidebar-current,0px)))] flex-col overflow-hidden border-l border-border/70 bg-card"
                style={{
                    top: 'var(--app-navbar-current, 0px)',
                    right: 0,
                    bottom: 0,
                }}
            >
                <div className="shrink-0 px-4 pt-5 pb-4">
                    <p className="text-[10px] font-semibold tracking-[0.22em] text-primary uppercase">Customize</p>
                    <h2 id="theme-settings-title" className="font-heading mt-1 text-xl font-semibold tracking-tight text-foreground">
                        Theme
                    </h2>
                    <span className="mt-2 block h-1 w-9 rounded-[8px] bg-primary" />
                </div>

                <div className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-4 pb-4">
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
                        className="h-10 w-full rounded-[8px] text-sm"
                        onClick={resetThemeSettings}
                    >
                        Reset Default
                    </Button>
                </div>
            </aside>
        </>,
        document.body,
    );
}
