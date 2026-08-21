import { FormEvent, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { EyeIcon, EyeOffIcon, Loader2Icon, LockIcon, UserIcon } from 'lucide-react';

import { AuthOutlineInput } from '@/components/auth/AuthOutlineInput';
import { LoginIllustration } from '@/components/auth/LoginIllustration';
import { BrandLockup } from '@/components/layout/BrandLockup';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

type LoginProps = {
    status?: string;
};

export default function Login({ status }: LoginProps) {
    const { t } = useTranslation();
    const [showPassword, setShowPassword] = useState(false);
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

            <Card className="relative w-full min-w-0 gap-0 overflow-hidden rounded-[12px] border border-border bg-[#FFFFFF] py-0 shadow-[0_8px_30px_rgb(23_50_54/0.08)]">
                <div className="grid w-full grid-cols-1 md:grid-cols-2">
                    <div className="order-1 flex justify-center px-5 pt-5 md:hidden">
                        <BrandLockup compact href="/" className="justify-center" logoClassName="size-14 sm:size-16" />
                    </div>

                    <div className="relative order-2 flex flex-col items-center justify-center bg-[#FFFFFF] px-5 py-4 sm:px-6 sm:py-5 md:order-1 md:min-h-[360px] md:border-r md:border-border md:px-6 md:py-8 lg:px-8">
                        <LoginIllustration className="relative z-[1] max-h-28 w-full max-w-[180px] sm:max-h-36 sm:max-w-[220px] md:max-h-[280px] md:max-w-none lg:max-h-[300px]" />
                        <a
                            href="https://storyset.com/illustration/online-world/amico"
                            target="_blank"
                            rel="noreferrer"
                            className="relative z-[1] mt-2 text-[11px] tracking-wide text-muted-foreground transition-colors hover:text-primary"
                        >
                            {t('auth.illustration_credit')}
                        </a>
                    </div>

                    <CardContent className="order-3 flex min-w-0 flex-col justify-center px-5 pt-4 pb-5 sm:px-6 sm:pb-6 md:order-2 md:px-6 md:py-8 lg:px-8">
                        <div className="mb-4 flex justify-center md:mb-5">
                            <BrandLockup compact href="/" className="hidden justify-center md:flex" logoClassName="size-16" />
                        </div>

                        {status ? <p className="mb-4 text-center text-sm text-accent-foreground">{status}</p> : null}

                        <form onSubmit={submit} className="flex flex-col gap-4">
                            <div className="flex min-w-0 flex-col gap-1.5">
                                <label htmlFor="email" className="sr-only">
                                    {t('auth.username')}
                                </label>
                                <AuthOutlineInput
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    autoComplete="username"
                                    placeholder={t('auth.username')}
                                    invalid={Boolean(form.errors.email)}
                                    required
                                    leftIcon={<UserIcon className="size-[18px]" strokeWidth={1.75} />}
                                />
                                {form.errors.email ? <p className="text-sm text-danger">{form.errors.email}</p> : null}
                            </div>

                            <div className="flex min-w-0 flex-col gap-1.5">
                                <label htmlFor="password" className="sr-only">
                                    {t('auth.password')}
                                </label>
                                <AuthOutlineInput
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    value={form.data.password}
                                    onChange={(event) => form.setData('password', event.target.value)}
                                    autoComplete="current-password"
                                    placeholder={t('auth.password')}
                                    invalid={Boolean(form.errors.password)}
                                    required
                                    leftIcon={<LockIcon className="size-[18px]" strokeWidth={1.75} />}
                                    rightSlot={
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((current) => ! current)}
                                            className="flex size-10 items-center justify-center rounded-[8px] text-muted-foreground transition-colors hover:text-foreground focus-visible:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-0 focus-visible:outline-none"
                                            aria-label={showPassword ? t('auth.hide_password') : t('auth.show_password')}
                                            aria-pressed={showPassword}
                                        >
                                            {showPassword ? (
                                                <EyeOffIcon className="size-[18px]" strokeWidth={1.75} />
                                            ) : (
                                                <EyeIcon className="size-[18px]" strokeWidth={1.75} />
                                            )}
                                        </button>
                                    }
                                />
                                {form.errors.password ? <p className="text-sm text-danger">{form.errors.password}</p> : null}
                            </div>

                            <label className="flex items-center gap-2 text-sm text-foreground">
                                <input
                                    type="checkbox"
                                    checked={form.data.remember}
                                    onChange={(event) => form.setData('remember', event.target.checked)}
                                    className={cn(
                                        'size-4 rounded-[4px] border border-input bg-transparent text-primary accent-primary',
                                        'focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-background',
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
                                className="text-center text-sm text-muted-foreground transition-colors hover:text-primary"
                            >
                                {t('auth.forgot_password')}
                            </Link>
                        </form>
                    </CardContent>
                </div>
            </Card>
        </>
    );
}
