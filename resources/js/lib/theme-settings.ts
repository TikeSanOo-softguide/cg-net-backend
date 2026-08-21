export const THEME_SETTINGS_KEY = 'isp-admin-theme-settings';
export const DEFAULT_PRIMARY = '#1d4ed8';

export const PRIMARY_PRESETS = [
    { name: 'Royal', hex: '#1d4ed8' },
    { name: 'Brand Teal', hex: '#173236' },
    { name: 'Ocean', hex: '#0f766e' },
    { name: 'Violet', hex: '#7c3aed' },
    { name: 'Rose', hex: '#be123c' },
    { name: 'Amber', hex: '#b45309' },
    { name: 'Forest', hex: '#15803d' },
    { name: 'Slate', hex: '#334155' },
] as const;

export type BorderRadius = 'none' | 'sm' | 'md' | 'lg' | 'full';
export type CardStyle = 'flat' | 'bordered' | 'shadow' | 'elevated';
export type ShadowStyle = 'none' | 'sm' | 'md' | 'lg' | 'glow';

export type ThemeSettings = {
    primaryColor: string;
    cardStyle: CardStyle;
    borderRadius: BorderRadius;
    shadowStyle: ShadowStyle;
};

export const DEFAULT_THEME_SETTINGS: ThemeSettings = {
    primaryColor: DEFAULT_PRIMARY,
    cardStyle: 'flat',
    borderRadius: 'md',
    shadowStyle: 'md',
};

export function normalizeHex(input: string): string | null {
    let value = input.trim();

    if (! value.startsWith('#')) {
        value = `#${value}`;
    }

    if (/^#[0-9a-fA-F]{3}$/.test(value)) {
        value = `#${value[1]}${value[1]}${value[2]}${value[2]}${value[3]}${value[3]}`;
    }

    if (! /^#[0-9a-fA-F]{6}$/.test(value)) {
        return null;
    }

    return value.toLowerCase();
}

export function hexToHsl(hex: string): { h: number; s: number; l: number } {
    const r = Number.parseInt(hex.slice(1, 3), 16) / 255;
    const g = Number.parseInt(hex.slice(3, 5), 16) / 255;
    const b = Number.parseInt(hex.slice(5, 7), 16) / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const l = (max + min) / 2;
    let h = 0;
    let s = 0;

    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

        switch (max) {
            case r:
                h = (g - b) / d + (g < b ? 6 : 0);
                break;
            case g:
                h = (b - r) / d + 2;
                break;
            default:
                h = (r - g) / d + 4;
        }

        h /= 6;
    }

    return {
        h: Math.round(h * 360),
        s: Math.round(s * 100),
        l: Math.round(l * 100),
    };
}

export function formatHslChannels(h: number, s: number, l: number): string {
    return `${h} ${s}% ${l}%`;
}

export function swatchHsl(hex: string): string {
    const { h, s, l } = hexToHsl(hex);

    return `hsl(${formatHslChannels(h, s, l)})`;
}

export function contrastForeground(lightness: number): string {
    return lightness < 55 ? '0 0% 100%' : '222 47% 11%';
}

function darkModeLightness(lightness: number): number {
    if (lightness < 40) {
        return Math.min(lightness + 46, 64);
    }

    if (lightness < 55) {
        return Math.min(Math.max(lightness, 52), 68);
    }

    return Math.max(42, Math.min(lightness, 62));
}

export function applyPrimaryColor(hex: string): void {
    const normalized = normalizeHex(hex);

    if (! normalized || typeof document === 'undefined') {
        return;
    }

    const { h, s, l } = hexToHsl(normalized);
    const hoverL = Math.min(l + 4, 96);
    const darkL = darkModeLightness(l);
    const root = document.documentElement;

    root.style.setProperty('--theme-primary', formatHslChannels(h, s, l));
    root.style.setProperty('--theme-primary-hover', formatHslChannels(h, s, hoverL));
    root.style.setProperty('--theme-primary-foreground', contrastForeground(l));
    root.style.setProperty('--theme-primary-dark', formatHslChannels(h, s, darkL));
    root.style.setProperty('--theme-primary-dark-hover', formatHslChannels(h, s, Math.min(darkL + 4, 90)));
    root.style.setProperty('--theme-primary-dark-foreground', contrastForeground(darkL));
}

function pick<T extends string>(value: unknown, allowed: readonly T[], fallback: T): T {
    return allowed.includes(value as T) ? (value as T) : fallback;
}

function migratePrimaryColor(hex: string): string {
    return hex === '#173236' ? DEFAULT_PRIMARY : hex;
}

export function readStoredThemeSettings(): ThemeSettings {
    if (typeof window === 'undefined') {
        return DEFAULT_THEME_SETTINGS;
    }

    try {
        const raw = window.localStorage.getItem(THEME_SETTINGS_KEY);

        if (! raw) {
            return DEFAULT_THEME_SETTINGS;
        }

        const parsed = JSON.parse(raw) as Partial<ThemeSettings> & {
            cardRadius?: BorderRadius;
            cardVariant?: CardStyle;
        };

        return {
            primaryColor: migratePrimaryColor(normalizeHex(parsed.primaryColor ?? '') ?? DEFAULT_PRIMARY),
            cardStyle: pick(parsed.cardStyle ?? parsed.cardVariant, ['flat', 'bordered', 'shadow', 'elevated'], DEFAULT_THEME_SETTINGS.cardStyle),
            borderRadius: pick(parsed.borderRadius ?? parsed.cardRadius, ['none', 'sm', 'md', 'lg', 'full'], DEFAULT_THEME_SETTINGS.borderRadius),
            shadowStyle: pick(parsed.shadowStyle, ['none', 'sm', 'md', 'lg', 'glow'], DEFAULT_THEME_SETTINGS.shadowStyle),
        };
    } catch {
        return DEFAULT_THEME_SETTINGS;
    }
}

export function writeStoredThemeSettings(settings: ThemeSettings): void {
    window.localStorage.setItem(THEME_SETTINGS_KEY, JSON.stringify(settings));
}

export const borderRadiusClass: Record<BorderRadius, string> = {
    none: 'rounded-none',
    sm: 'rounded-sm',
    md: 'rounded-md',
    lg: 'rounded-lg',
    full: 'rounded-3xl',
};

export const cardStyleClass: Record<CardStyle, string> = {
    flat: 'border-0',
    bordered: 'border border-border',
    shadow: 'border border-border/80',
    elevated: 'border-0',
};

export const shadowStyleClass: Record<ShadowStyle, string> = {
    none: 'shadow-none',
    sm: 'shadow-sm',
    md: 'shadow-card',
    lg: 'shadow-lg',
    glow: 'shadow-[0_8px_28px_hsl(var(--primary)/0.28)]',
};
