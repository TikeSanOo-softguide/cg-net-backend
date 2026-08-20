import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import {
    applyPrimaryColor,
    DEFAULT_THEME_SETTINGS,
    readStoredThemeSettings,
    writeStoredThemeSettings,
    type BorderRadius,
    type CardStyle,
    type ShadowStyle,
    type ThemeSettings,
} from '@/lib/theme-settings';

type ThemeSettingsContextValue = ThemeSettings & {
    setPrimaryColor: (hex: string) => void;
    setCardStyle: (style: CardStyle) => void;
    setBorderRadius: (radius: BorderRadius) => void;
    setShadowStyle: (style: ShadowStyle) => void;
    resetThemeSettings: () => void;
};

const ThemeSettingsContext = createContext<ThemeSettingsContextValue | null>(null);

export function ThemeSettingsProvider({ children }: { children: ReactNode }) {
    const [settings, setSettings] = useState<ThemeSettings>(() => {
        const stored = readStoredThemeSettings();
        applyPrimaryColor(stored.primaryColor);

        return stored;
    });

    useEffect(() => {
        applyPrimaryColor(settings.primaryColor);
        writeStoredThemeSettings(settings);
    }, [settings]);

    const setPrimaryColor = useCallback((primaryColor: string) => {
        setSettings((current) => ({ ...current, primaryColor }));
    }, []);

    const setCardStyle = useCallback((cardStyle: CardStyle) => {
        setSettings((current) => ({ ...current, cardStyle }));
    }, []);

    const setBorderRadius = useCallback((borderRadius: BorderRadius) => {
        setSettings((current) => ({ ...current, borderRadius }));
    }, []);

    const setShadowStyle = useCallback((shadowStyle: ShadowStyle) => {
        setSettings((current) => ({ ...current, shadowStyle }));
    }, []);

    const resetThemeSettings = useCallback(() => {
        setSettings(DEFAULT_THEME_SETTINGS);
    }, []);

    const value = useMemo<ThemeSettingsContextValue>(
        () => ({
            ...settings,
            setPrimaryColor,
            setCardStyle,
            setBorderRadius,
            setShadowStyle,
            resetThemeSettings,
        }),
        [settings, setPrimaryColor, setCardStyle, setBorderRadius, setShadowStyle, resetThemeSettings],
    );

    return <ThemeSettingsContext.Provider value={value}>{children}</ThemeSettingsContext.Provider>;
}

export function useThemeSettings(): ThemeSettingsContextValue {
    const context = useContext(ThemeSettingsContext);

    if (! context) {
        throw new Error('useThemeSettings must be used within ThemeSettingsProvider');
    }

    return context;
}

export function useOptionalThemeSettings(): ThemeSettingsContextValue | null {
    return useContext(ThemeSettingsContext);
}
