import '../css/app.css';
import './echo';
import { createInertiaApp } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import AuthLayout from '@/layouts/AuthLayout';
import { FloatingThemeSettingsButton } from '@/components/theme/FloatingThemeSettingsButton';
import { ThemeProvider } from '@/providers/ThemeProvider';
import { ThemeSettingsProvider } from '@/providers/ThemeSettingsProvider';

const appName = import.meta.env.VITE_APP_NAME || 'CG-Net Admin';

createInertiaApp({
    pages: './pages',
    title: (title) => (title ? `${title} — ${appName}` : appName),
    layout: (name) => {
        if (name.startsWith('Auth/') || name.startsWith('auth/')) {
            return AuthLayout;
        }

        return AppLayout;
    },
    withApp: (app) => (
        <ThemeProvider>
            <ThemeSettingsProvider>
                {app}
                <FloatingThemeSettingsButton />
            </ThemeSettingsProvider>
        </ThemeProvider>
    ),
    strictMode: true,
    progress: {
        color: 'var(--primary)',
    },
});
