import { ClipboardListIcon, HeadphonesIcon, ShieldCheckIcon, ShieldIcon, type LucideIcon } from 'lucide-react';

import type { StaffRoleOption } from '@/components/staff/StaffForm';

type Translate = (key: string) => string;

const ROLE_META: Record<string, { icon: LucideIcon; descriptionKey: string }> = {
    'Super Admin': {
        icon: ShieldCheckIcon,
        descriptionKey: 'staff.role_descriptions.super_admin',
    },
    'Staff Officer': {
        icon: ClipboardListIcon,
        descriptionKey: 'staff.role_descriptions.staff_officer',
    },
    'Support Agent': {
        icon: HeadphonesIcon,
        descriptionKey: 'staff.role_descriptions.support_agent',
    },
};

export function staffRoleCard(role: StaffRoleOption, t: Translate) {
    const meta = ROLE_META[role.name];

    return {
        value: String(role.id),
        label: role.name,
        description: t(meta?.descriptionKey ?? 'staff.role_descriptions.custom'),
        icon: meta?.icon ?? ShieldIcon,
    };
}
