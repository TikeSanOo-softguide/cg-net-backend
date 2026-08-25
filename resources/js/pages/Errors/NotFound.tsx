import { Head, Link, usePage } from '@inertiajs/react';
import { HouseIcon, LogInIcon } from 'lucide-react';

import { NotFoundIllustration } from '@/components/errors/NotFoundIllustration';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/useTranslation';

export default function NotFound() {
    const { t } = useTranslation();
    const user = usePage().props.auth?.user ?? null;

    return (
        <>
            <Head title={t('errors.not_found_title')} />

            <div className={user ? 'flex min-h-[min(36rem,100%)] items-center justify-center py-8' : undefined}>
                <Card className="w-full min-w-0 max-w-[820px] gap-0 overflow-hidden rounded-[12px] border-0 bg-[#FFFFFF] py-0 shadow-[0_8px_30px_rgb(23_50_54/0.08)]">
                    <CardContent className="flex flex-col items-center px-6 py-8 text-center sm:px-10 sm:py-10">
                        <NotFoundIllustration className="max-h-52 w-full max-w-[420px] sm:max-h-64 md:max-h-72" />
                        <a
                            href="https://storyset.com/illustration/404-error-with-people-holding-the-numbers/pana"
                            target="_blank"
                            rel="noreferrer"
                            className="mt-3 text-[11px] tracking-wide text-muted-foreground transition-colors hover:text-primary"
                        >
                            {t('auth.illustration_credit')}
                        </a>
                        <h1 className="mt-5 font-heading text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            {t('errors.not_found_title')}
                        </h1>
                        <p className="mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                            {t('errors.not_found_description')}
                        </p>
                        <Button asChild variant="primary" size="md" className="mt-6">
                            <Link href={user ? '/dashboard' : '/login'}>
                                {user ? <HouseIcon /> : <LogInIcon />}
                                {user ? t('errors.back_home') : t('auth.sign_in')}
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
