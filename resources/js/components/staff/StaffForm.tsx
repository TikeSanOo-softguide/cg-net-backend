import { FormEvent } from 'react';
import type { InertiaFormProps } from '@inertiajs/react';
import { CircleDotIcon, LockIcon, MailIcon, ShieldIcon, UserIcon } from 'lucide-react';

import { FormActionBar } from '@/components/FormActionBar';
import { MultiSelect } from '@/components/MultiSelect';
import { Card, CardContent } from '@/components/ui/card';
import { FormField } from '@/components/ui/form-field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/useTranslation';

export type StaffRoleOption = {
    id: number;
    name: string;
};

export type StaffFormValues = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    status: string;
    role_ids: number[];
};

type StaffFormProps = {
    form: InertiaFormProps<StaffFormValues>;
    roles: StaffRoleOption[];
    onSubmit: (event: FormEvent) => void;
    cancelHref: string;
    passwordRequired?: boolean;
};

export function StaffForm({ form, roles, onSubmit, cancelHref, passwordRequired = true }: StaffFormProps) {
    const { t } = useTranslation();

    return (
        <Card className="max-w-3xl gap-0 py-0">
            <CardContent className="px-4 py-4 pb-24 sm:px-5 sm:py-5 sm:pb-5">
                <form onSubmit={onSubmit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField label={t('staff.name')} htmlFor="name" error={form.errors.name} icon={UserIcon} required className="sm:col-span-2">
                        <Input
                            id="name"
                            value={form.data.name}
                            required
                            autoComplete="name"
                            aria-invalid={Boolean(form.errors.name)}
                            onChange={(event) => form.setData('name', event.target.value)}
                        />
                    </FormField>
                    <FormField label={t('staff.email')} htmlFor="email" error={form.errors.email} icon={MailIcon} required>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            required
                            autoComplete="username"
                            aria-invalid={Boolean(form.errors.email)}
                            onChange={(event) => form.setData('email', event.target.value)}
                        />
                    </FormField>
                    <FormField label={t('common.status')} htmlFor="status" error={form.errors.status} icon={CircleDotIcon} required>
                        <Select value={form.data.status} onValueChange={(value) => form.setData('status', value)}>
                            <SelectTrigger id="status" className="w-full" aria-invalid={Boolean(form.errors.status)}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">{t('status.active')}</SelectItem>
                                <SelectItem value="inactive">{t('status.inactive')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        label={t('staff.password')}
                        htmlFor="password"
                        error={form.errors.password}
                        icon={LockIcon}
                        required={passwordRequired}
                    >
                        <Input
                            id="password"
                            type="password"
                            value={form.data.password}
                            required={passwordRequired}
                            autoComplete="new-password"
                            placeholder={passwordRequired ? undefined : t('staff.password_optional')}
                            aria-invalid={Boolean(form.errors.password)}
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={t('staff.password_confirmation')}
                        htmlFor="password_confirmation"
                        error={form.errors.password_confirmation}
                        icon={LockIcon}
                        required={passwordRequired}
                    >
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={form.data.password_confirmation}
                            required={passwordRequired || form.data.password.length > 0}
                            autoComplete="new-password"
                            aria-invalid={Boolean(form.errors.password_confirmation)}
                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={t('staff.roles')}
                        htmlFor="role_ids"
                        error={form.errors.role_ids}
                        required
                        className="sm:col-span-2"
                    >
                        <MultiSelect
                            id="role_ids"
                            icon={ShieldIcon}
                            values={form.data.role_ids.map(String)}
                            options={roles.map((role) => ({ value: String(role.id), label: role.name }))}
                            placeholder={t('staff.roles_placeholder')}
                            invalid={Boolean(form.errors.role_ids)}
                            onChange={(values) => form.setData('role_ids', values.map(Number))}
                        />
                    </FormField>
                    <FormActionBar cancelHref={cancelHref} processing={form.processing} className="sm:col-span-2" />
                </form>
            </CardContent>
        </Card>
    );
}
