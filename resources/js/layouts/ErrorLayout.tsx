import { type ReactNode } from 'react';
import { usePage } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import AuthLayout from '@/layouts/AuthLayout';

type ErrorLayoutProps = {
    children: ReactNode;
};

export default function ErrorLayout({ children }: ErrorLayoutProps) {
    const user = usePage().props.auth?.user;

    if (user) {
        return <AppLayout>{children}</AppLayout>;
    }

    return <AuthLayout>{children}</AuthLayout>;
}
