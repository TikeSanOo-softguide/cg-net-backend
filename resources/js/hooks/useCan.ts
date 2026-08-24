import { useCallback } from 'react';
import { usePage } from '@inertiajs/react';

export function useCan() {
    const { auth } = usePage().props;

    return useCallback(
        (permission: string): boolean => {
            if (auth.is_super_admin) {
                return true;
            }

            return auth.permissions.includes(permission);
        },
        [auth.is_super_admin, auth.permissions],
    );
}
