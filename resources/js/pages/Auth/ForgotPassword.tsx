import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { UserIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/useTranslation';

type ForgotPasswordProps = {
    status?: string;
};

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const { t } = useTranslation();
    const form = useForm({
        username: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/forgot-password');
    };

    return (
        <>
            <Head title="Forgot password" />
            <Card className="mx-auto w-full max-w-[400px]">
                <CardHeader>
                    <CardTitle>Reset password</CardTitle>
                    <CardDescription>Enter your staff username and we will send a reset link.</CardDescription>
                </CardHeader>
                <CardContent>
                    {status ? <p className="mb-4 text-sm text-accent-foreground">{status}</p> : null}
                    <form onSubmit={submit} className="flex flex-col gap-5">
                        <FormField label={t('auth.username')} htmlFor="username" error={form.errors.username} icon={UserIcon}>
                            <Input
                                id="username"
                                type="text"
                                value={form.data.username}
                                onChange={(event) => form.setData('username', event.target.value)}
                                autoComplete="username"
                                required
                                aria-invalid={Boolean(form.errors.username)}
                            />
                        </FormField>
                        <Button type="submit" variant="primary" size="md" disabled={form.processing}>
                            {form.processing ? <Spinner size="xs" className="text-current" /> : null}
                            Send reset link
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </>
    );
}
