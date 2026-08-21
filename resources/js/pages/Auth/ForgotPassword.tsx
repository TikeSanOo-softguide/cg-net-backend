import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="flex flex-col gap-2">
                            <label htmlFor="email" className="text-sm font-medium">
                                Email
                            </label>
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                required
                            />
                            {form.errors.email ? <p className="text-sm text-danger">{form.errors.email}</p> : null}
                        </div>
                        <Button type="submit" variant="primary" size="md" disabled={form.processing}>
                            Email reset link
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </>
    );
}
