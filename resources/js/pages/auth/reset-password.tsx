import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
            <Card>
                <CardHeader>
                    <CardTitle>Choose a new password</CardTitle>
                    <CardDescription>This link was emailed to your staff account.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="flex flex-col gap-2">
                            <label htmlFor="email" className="text-sm font-medium">
                                Email
                            </label>
                            <Input id="email" type="email" value={form.data.email} readOnly className="font-mono" />
                        </div>
                        <div className="flex flex-col gap-2">
                            <label htmlFor="password" className="text-sm font-medium">
                                Password
                            </label>
                            <Input
                                id="password"
                                type="password"
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                required
                            />
                            {form.errors.password ? <p className="text-sm text-danger">{form.errors.password}</p> : null}
                        </div>
                        <div className="flex flex-col gap-2">
                            <label htmlFor="password_confirmation" className="text-sm font-medium">
                                Confirm password
                            </label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                value={form.data.password_confirmation}
                                onChange={(event) => form.setData('password_confirmation', event.target.value)}
                                required
                            />
                        </div>
                        <Button type="submit" variant="primary" size="md" disabled={form.processing}>
                            Reset password
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </>
    );
}
