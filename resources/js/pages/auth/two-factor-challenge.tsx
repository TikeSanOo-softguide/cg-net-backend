import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
            <Card>
                <CardHeader>
                    <CardTitle>Two-factor authentication</CardTitle>
                    <CardDescription>Optional 2FA is scaffolded. Enter an authenticator code or a recovery code.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="flex flex-col gap-2">
                            <label htmlFor="code" className="text-sm font-medium">
                                Authentication code
                            </label>
                            <Input
                                id="code"
                                className="font-mono"
                                value={form.data.code}
                                onChange={(event) => form.setData('code', event.target.value)}
                                autoComplete="one-time-code"
                            />
                            {form.errors.code ? <p className="text-sm text-danger">{form.errors.code}</p> : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <label htmlFor="recovery_code" className="text-sm font-medium">
                                Recovery code
                            </label>
                            <Input
                                id="recovery_code"
                                className="font-mono"
                                value={form.data.recovery_code}
                                onChange={(event) => form.setData('recovery_code', event.target.value)}
                            />
                        </div>
                        <Button type="submit" variant="primary" size="md" disabled={form.processing}>
                            Continue
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </>
    );
}
