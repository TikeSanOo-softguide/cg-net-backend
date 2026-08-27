import { type ReactNode } from 'react';

import LanguageSwitcher from '@/components/LanguageSwitcher';
import { useDocumentLang } from '@/hooks/useDocumentLang';

type AuthLayoutProps = {
    children: ReactNode;
};

export default function AuthLayout({ children }: AuthLayoutProps) {
    useDocumentLang();

    return (
        <div className="relative flex min-h-dvh flex-col overflow-x-hidden bg-[color-mix(in_srgb,hsl(var(--primary))_8%,#f3f5f7)] px-4 pt-16 pb-8 sm:px-6 sm:pt-20 sm:pb-10 lg:px-10 lg:py-12">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 bg-[radial-gradient(900px_circle_at_50%_-8%,hsl(var(--primary)/0.18),transparent_58%),linear-gradient(180deg,color-mix(in_srgb,hsl(var(--primary))_6%,white),transparent_42%)]"
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
