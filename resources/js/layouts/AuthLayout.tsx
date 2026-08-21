import { type ReactNode } from 'react';

import LanguageSwitcher from '@/components/LanguageSwitcher';

type AuthLayoutProps = {
    children: ReactNode;
};

export default function AuthLayout({ children }: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-dvh flex-col overflow-x-hidden bg-background px-4 pt-16 pb-8 sm:px-6 sm:pt-20 sm:pb-10 lg:px-10 lg:py-12">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 bg-[radial-gradient(1100px_circle_at_8%_12%,hsl(var(--primary)/0.16),transparent_52%),radial-gradient(900px_circle_at_92%_6%,rgb(201_162_39/0.18),transparent_44%),linear-gradient(180deg,color-mix(in_srgb,var(--background)_88%,white),var(--background))]"
            />
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-warning/50 to-transparent"
            />

            <div className="absolute top-4 right-4 z-10 sm:top-6 sm:right-6">
                <LanguageSwitcher />
            </div>

            <div className="auth-shell relative z-[1] mx-auto flex w-full max-w-[820px] flex-1 flex-col justify-center text-foreground">
                {children}
            </div>
        </div>
    );
}
