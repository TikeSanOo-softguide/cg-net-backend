import { type ReactNode } from 'react';

import LanguageSwitcher from '@/components/LanguageSwitcher';

type AuthLayoutProps = {
    children: ReactNode;
};

export default function AuthLayout({ children }: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-dvh flex-col bg-background px-4 py-8 sm:items-center sm:justify-center sm:px-6">
            <div className="absolute top-4 right-4 sm:top-6 sm:right-6">
                <LanguageSwitcher />
            </div>

            <div className="mx-auto flex w-full max-w-[400px] flex-1 flex-col sm:flex-none">
                {children}
            </div>
        </div>
    );
}
