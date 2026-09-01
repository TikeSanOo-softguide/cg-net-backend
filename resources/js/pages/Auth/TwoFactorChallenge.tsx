import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { KeyRoundIcon, ShieldCheckIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';

export default function TwoFactorChallenge() {
    const form = useForm({
        code: '',
        recovery_code: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/two-factor-challenge');
    };

    return (
        <>
            <Head title="Two-factor challenge" />
            <Card className="mx-auto w-full max-w-[400px]">
                <CardHeader>
                    <CardTitle>Two-factor authentication</CardTitle>
                    <CardDescription>Optional 2FA is scaffolded. Enter an authenticator code or a recovery code.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="flex flex-col gap-5">
                        <FormField label="Authentication code" htmlFor="code" error={form.errors.code} icon={ShieldCheckIcon}>
                            <Input
                                id="code"
                                className="font-mono"
                                value={form.data.code}
                                onChange={(event) => form.setData('code', event.target.value)}
                                autoComplete="one-time-code"
                                aria-invalid={Boolean(form.errors.code)}
                            />
                        </FormField>
                        <FormField label="Recovery code" htmlFor="recovery_code" icon={KeyRoundIcon}>
                            <Input
                                id="recovery_code"
                                className="font-mono"
                                value={form.data.recovery_code}
                                onChange={(event) => form.setData('recovery_code', event.target.value)}
                            />
                        </FormField>
                        <Button type="submit" variant="primary" size="md" disabled={form.processing}>
                            {form.processing ? <Spinner size="xs" className="text-current" /> : <ShieldCheckIcon />}
                            Continue
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </>
    );
}
