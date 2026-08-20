import { useEffect, useState, type ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import {
    DEFAULT_PRIMARY,
    PRIMARY_PRESETS,
    borderRadiusClass,
    cardStyleClass,
    normalizeHex,
    shadowStyleClass,
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

function Tile({
    selected,
    label,
    onClick,
    children,
}: {
    selected: boolean;
    label: string;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={selected}
            className={cn(
                'flex flex-col items-center gap-1 rounded-md bg-transparent p-1.5 transition-all duration-200',
                selected
                    ? 'scale-[1.02] bg-muted/70 ring-1 ring-primary/45'
                    : 'hover:scale-[1.01] hover:bg-muted/50',
            )}
        >
            <div className="flex h-8 w-full items-center justify-center">{children}</div>
            <span className="text-center text-[9px] font-medium leading-none tracking-wide text-muted-foreground">{label}</span>
        </button>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="flex min-h-0 flex-col gap-1.5 border-b border-border/40 py-2.5 last:border-b-0">
            <h3 className="font-heading text-[10px] font-medium tracking-[0.18em] text-muted-foreground uppercase">{title}</h3>
            {children}
        </section>
    );
}

function HexColorField({
    id,
    value,
    onChange,
}: {
    id: string;
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
            <label className="relative size-8 shrink-0 cursor-pointer overflow-hidden rounded-md ring-1 ring-border/80">
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
                id={id}
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
                className="h-8 font-mono text-xs tracking-wide"
            />
        </div>
    );
}

const cardStyleOptions: { value: CardStyle; label: string }[] = [
    { value: 'flat', label: 'Flat' },
    { value: 'bordered', label: 'Bordered' },
    { value: 'shadow', label: 'Shadow' },
    { value: 'elevated', label: 'Elevated' },
];

const radiusOptions: { value: BorderRadius; label: string }[] = [
    { value: 'none', label: 'None' },
    { value: 'sm', label: 'Small' },
    { value: 'md', label: 'Medium' },
    { value: 'lg', label: 'Large' },
    { value: 'full', label: 'Full' },
];

const shadowOptions: { value: ShadowStyle; label: string }[] = [
    { value: 'none', label: 'None' },
    { value: 'sm', label: 'Small' },
    { value: 'md', label: 'Medium' },
    { value: 'lg', label: 'Large' },
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

    const tileRadius = borderRadiusClass[borderRadius === 'full' ? 'lg' : borderRadius];

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="h-dvh w-[360px] max-w-[90vw] gap-0 overflow-hidden bg-card p-0 sm:max-w-[360px] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                <SheetHeader className="shrink-0 space-y-0.5 border-b border-border/40 px-5 py-3.5 pr-12">
                    <SheetTitle className="font-heading text-[13px] font-medium tracking-[0.16em] uppercase">Theme</SheetTitle>
                    <SheetDescription className="text-[11px] tracking-wide">Live preview. Changes save instantly.</SheetDescription>
                </SheetHeader>

                <div className="flex min-h-0 flex-1 flex-col overflow-hidden px-5 pb-3">
                    <Section title="Card style">
                        <div className="grid grid-cols-4 gap-1">
                            {cardStyleOptions.map((option) => (
                                <Tile
                                    key={option.value}
                                    label={option.label}
                                    selected={cardStyle === option.value}
                                    onClick={() => setCardStyle(option.value)}
                                >
                                    <div
                                        className={cn(
                                            'h-7 w-11 bg-card ring-1 ring-border/50',
                                            tileRadius,
                                            cardStyleClass[option.value],
                                            option.value === 'shadow' && 'shadow-card',
                                            option.value === 'elevated' && 'shadow-md',
                                        )}
                                    >
                                        <div className="mt-1.5 ml-1.5 h-0.5 w-5 rounded-full bg-muted-foreground/30" />
                                        <div className="mt-1 ml-1.5 h-0.5 w-3 rounded-full bg-muted-foreground/20" />
                                    </div>
                                </Tile>
                            ))}
                        </div>
                    </Section>

                    <Section title="Border radius">
                        <div className="grid grid-cols-5 gap-1">
                            {radiusOptions.map((option) => (
                                <Tile
                                    key={option.value}
                                    label={option.label}
                                    selected={borderRadius === option.value}
                                    onClick={() => setBorderRadius(option.value)}
                                >
                                    <div
                                        className={cn(
                                            'size-7 border border-border/80 bg-card',
                                            option.value === 'full' ? 'rounded-full' : borderRadiusClass[option.value],
                                        )}
                                    />
                                </Tile>
                            ))}
                        </div>
                    </Section>

                    <Section title="Primary color">
                        <div className="grid grid-cols-4 gap-1">
                            {PRIMARY_PRESETS.map((preset) => (
                                <Tile
                                    key={preset.hex}
                                    label={preset.name.replace('Brand ', '')}
                                    selected={primaryColor === preset.hex}
                                    onClick={() => setPrimaryColor(preset.hex)}
                                >
                                    <div
                                        className="size-6 rounded-full ring-1 ring-foreground/10"
                                        style={{ backgroundColor: swatchHsl(preset.hex) }}
                                    />
                                </Tile>
                            ))}
                        </div>
                        <HexColorField id="primary-hex" value={primaryColor} onChange={setPrimaryColor} />
                    </Section>

                    <Section title="Shadow style">
                        <div className="grid grid-cols-5 gap-1">
                            {shadowOptions.map((option) => (
                                <Tile
                                    key={option.value}
                                    label={option.label}
                                    selected={shadowStyle === option.value}
                                    onClick={() => setShadowStyle(option.value)}
                                >
                                    <div className={cn('h-7 w-10 bg-card ring-1 ring-border/50', tileRadius, shadowStyleClass[option.value])} />
                                </Tile>
                            ))}
                        </div>
                    </Section>

                    <Button
                        type="button"
                        variant="ghost"
                        onClick={resetThemeSettings}
                        className="mt-auto h-8 shrink-0 text-[11px] font-medium tracking-wide text-muted-foreground hover:text-foreground"
                    >
                        Reset to defaults
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
