import { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';

import { BrandLockup } from '@/components/layout/BrandLockup';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type LoginProps = {
    status?: string;
};

export default function Login({ status }: LoginProps) {
    const { t } = useTranslation();
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/login');
    };

    return (
        <>
            <Head title={t('auth.sign_in')} />

            <Card className="gap-0 overflow-hidden py-0 shadow-card">
                <div className="flex flex-col items-center px-6 pt-10 pb-6">
                    <BrandLockup compact href="/" className="justify-center" logoClassName="size-20 sm:size-24" />
                </div>

                <CardContent className="px-6 pb-10">
                    {status ? <p className="mb-4 text-center text-sm text-accent-foreground">{status}</p> : null}

                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="email" className="sr-only">
                                {t('auth.email')}
                            </label>
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                autoComplete="username"
                                placeholder={t('auth.email')}
                                aria-invalid={Boolean(form.errors.email)}
                                className="h-11 rounded-[8px]"
                                required
                            />
                            {form.errors.email ? <p className="text-sm text-danger">{form.errors.email}</p> : null}
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label htmlFor="password" className="sr-only">
                                {t('auth.password')}
                            </label>
                            <Input
                                id="password"
                                type="password"
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                autoComplete="current-password"
                                placeholder={t('auth.password')}
                                aria-invalid={Boolean(form.errors.password)}
                                className="h-11 rounded-[8px]"
                                required
                            />
                            {form.errors.password ? <p className="text-sm text-danger">{form.errors.password}</p> : null}
                        </div>

                        <label className="flex items-center gap-2 text-sm text-foreground">
                            <input
                                type="checkbox"
                                checked={form.data.remember}
                                onChange={(event) => form.setData('remember', event.target.checked)}
                                className={cn(
                                    'size-4 rounded-[4px] border border-input bg-surface text-primary accent-primary',
                                    'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                                )}
                            />
                            {t('auth.remember_me')}
                        </label>

                        <Button type="submit" variant="primary" size="md" className="mt-1 h-11 w-full rounded-[8px]" disabled={form.processing}>
                            {form.processing ? <Loader2Icon className="size-4 animate-spin" /> : null}
                            {t('auth.sign_in')}
                        </Button>

                        <Link
                            href="/forgot-password"
                            className="text-center text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            {t('auth.forgot_password')}
                        </Link>
                    </form>
                </CardContent>
            </Card>
        </>
    );
}
