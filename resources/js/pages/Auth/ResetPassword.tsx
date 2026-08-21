import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { LockIcon, MailIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';

type ResetPasswordProps = {
    email: string;
    token: string;
};

export default function ResetPassword({ email, token }: ResetPasswordProps) {
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/reset-password');
    };

    return (
        <>
            <Head title="Reset password" />
            <Card className="mx-auto w-full max-w-[400px]">
                <CardHeader>
                    <CardTitle>Choose a new password</CardTitle>
                    <CardDescription>This link was emailed to your staff account.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <FormField label="Email" htmlFor="email" icon={MailIcon}>
                            <Input id="email" type="email" value={form.data.email} readOnly className="font-mono" />
                        </FormField>
                        <FormField label="Password" htmlFor="password" error={form.errors.password} icon={LockIcon}>
                            <Input
                                id="password"
                                type="password"
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                required
                                aria-invalid={Boolean(form.errors.password)}
                            />
                        </FormField>
                        <FormField label="Confirm password" htmlFor="password_confirmation" icon={LockIcon}>
                            <Input
                                id="password_confirmation"
                                type="password"
                                value={form.data.password_confirmation}
                                onChange={(event) => form.setData('password_confirmation', event.target.value)}
                                required
                            />
                        </FormField>
                        <Button type="submit" variant="primary" size="md" disabled={form.processing}>
                            <LockIcon />
                            Reset password
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </>
    );
}
