import { router } from '@inertiajs/react';

type PageWithDeleteError = {
    props: {
        errors?: {
            delete?: string | string[];
        };
    };
};

export function visitBulkDelete(url: string, ids: Array<number | string>): Promise<void> {
    return new Promise((resolve, reject) => {
        router.delete(url, {
            data: { ids },
            preserveScroll: true,
            onSuccess: (page) => {
                const deleteError = (page as PageWithDeleteError).props.errors?.delete;

                if (deleteError) {
                    reject(new Error(Array.isArray(deleteError) ? deleteError[0] : deleteError));

                    return;
                }

                resolve();
            },
            onError: () => reject(new Error('bulk-delete-failed')),
        });
    });
}
