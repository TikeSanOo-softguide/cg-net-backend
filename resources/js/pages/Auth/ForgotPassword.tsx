import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { MailIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';

type ForgotPasswordProps = {
    status?: string;
};

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const form = useForm({
        email: '',
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
                    <CardDescription>Enter your staff email and we will send a reset link.</CardDescription>
                </CardHeader>
                <CardContent>
                    {status ? <p className="mb-4 text-sm text-accent-foreground">{status}</p> : null}
                    <form onSubmit={submit} className="flex flex-col gap-5">
                        <FormField label="Email" htmlFor="email" error={form.errors.email} icon={MailIcon}>
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                required
                                aria-invalid={Boolean(form.errors.email)}
                            />
                        </FormField>
                        <Button type="submit" variant="primary" size="md" disabled={form.processing}>
                            <MailIcon />
                            Email reset link
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </>
    );
}
