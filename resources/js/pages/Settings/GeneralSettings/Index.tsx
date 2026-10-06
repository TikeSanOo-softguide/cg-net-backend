import { useState } from 'react';
import { Head, router } from '@inertiajs/react';

import { ConfirmDialog } from '@/components/ConfirmDialog';
import { PageContent } from '@/components/PageContent';
import { PageHeader } from '@/components/PageHeader';
import { useCan } from '@/hooks/useCan';
import { useTranslation } from '@/hooks/useTranslation';

import { FaqFormDialog, type FaqItem } from '@/components/settings/general-settings/faq/FaqFormDialog';
import { FaqTable } from '@/components/settings/general-settings/faq/FaqTable';

import {
    TermAndConditionFormDialog,
    type TermAndConditionItem,
} from '@/components/settings/general-settings/term-and-conditon/TermAndConditionFormDialog';
import { TermAndConditionTable } from '@/components/settings/general-settings/term-and-conditon/TermAndConditionTable';

import {
    SupportContactFormDialog,
    type SupportContactItem,
} from '@/components/settings/general-settings/support-contact/SupportContactFormDialog';
import { SupportContactTable } from '@/components/settings/general-settings/support-contact/SupportContactTable';

type Props = {
    termsAndConditions: TermAndConditionItem[];
    faqs: FaqItem[];
    supportContacts: SupportContactItem[];
};

type SelectedSection = 'termsAndConditions' | 'faqs' | 'supportContacts';

type ActiveForm =
    | {
          kind: 'termsAndConditions';
          item: TermAndConditionItem | null;
      }
    | {
          kind: 'faqs';
          item: FaqItem | null;
      }
    | {
          kind: 'supportContacts';
          item: SupportContactItem | null;
      };

type DeleteTarget =
    | {
          kind: 'termsAndConditions';
          item: TermAndConditionItem;
      }
    | {
          kind: 'faqs';
          item: FaqItem;
      }
    | {
          kind: 'supportContacts';
          item: SupportContactItem;
      };

function getDeleteRoute(kind: DeleteTarget['kind'], id: number): string {
    switch (kind) {
        case 'termsAndConditions':
            return `/settings/general/terms-and-conditions/${id}`;
        case 'faqs':
            return `/settings/general/faqs/${id}`;
        case 'supportContacts':
            return `/settings/general/support-contacts/${id}`;
    }
}

export default function GenSettingsIndex({ termsAndConditions, faqs, supportContacts }: Props) {
    const { t } = useTranslation();
    const can = useCan();
    const canCreate = can('settings.create');
    const canUpdate = can('settings.update');
    const canDelete = can('settings.delete');
    const [selectedSection, setSelectedSection] = useState<SelectedSection>('faqs');
    const [activeForm, setActiveForm] = useState<ActiveForm | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<DeleteTarget | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const openCreate = (kind: SelectedSection) => {
        setActiveForm({
            kind,
            item: null,
        } as ActiveForm);
    };

    const openFaqEdit = (item: FaqItem) => {
        setActiveForm({
            kind: 'faqs',
            item,
        });
    };

    const openTermAndConditionEdit = (item: TermAndConditionItem) => {
        setActiveForm({
            kind: 'termsAndConditions',
            item,
        });
    };

    const openSupportContactEdit = (item: SupportContactItem) => {
        setActiveForm({
            kind: 'supportContacts',
            item,
        });
    };

    const openFaqDelete = (item: FaqItem) => {
        setDeleteTarget({
            kind: 'faqs',
            item,
        });
    };

    const openTermAndConditionDelete = (item: TermAndConditionItem) => {
        setDeleteTarget({
            kind: 'termsAndConditions',
            item,
        });
    };

    const openSupportContactDelete = (item: SupportContactItem) => {
        setDeleteTarget({
            kind: 'supportContacts',
            item,
        });
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(getDeleteRoute(deleteTarget.kind, deleteTarget.item.id), {
            preserveScroll: true,

            onSuccess: () => {
                setDeleteTarget(null);
            },

            onStart: () => {
                setDeleteProcessing(true);
            },

            onFinish: () => {
                setDeleteProcessing(false);
            },
        });
    };

    return (
        <>
            <Head title={t('menu.general_settings')} />

            <PageContent>
                <PageHeader />

                <section className="rounded-xl border bg-card p-4 shadow-sm">
                    <div className="flex flex-wrap items-center justify-center gap-6">
                        <label className="mr-6 flex cursor-pointer items-center gap-2">
                            <input
                                type="radio"
                                name="general-setting-section"
                                value="termsAndConditions"
                                checked={selectedSection === 'termsAndConditions'}
                                onChange={() => setSelectedSection('termsAndConditions')}
                                className="size-4 accent-primary"
                            />

                            <span className="text-sm font-medium">
                                {t('settings.general_settings.terms_and_conditions')}
                            </span>
                        </label>

                        <label className="mr-6 flex cursor-pointer items-center gap-2">
                            <input
                                type="radio"
                                name="general-setting-section"
                                value="faqs"
                                checked={selectedSection === 'faqs'}
                                onChange={() => setSelectedSection('faqs')}
                                className="size-4 accent-primary"
                            />

                            <span className="text-sm font-medium">{t('settings.general_settings.faq')}</span>
                        </label>

                        <label className="mr-6 flex cursor-pointer items-center gap-2">
                            <input
                                type="radio"
                                name="general-setting-section"
                                value="supportContacts"
                                checked={selectedSection === 'supportContacts'}
                                onChange={() => setSelectedSection('supportContacts')}
                                className="size-4 accent-primary"
                            />

                            <span className="text-sm font-medium">
                                {t('settings.general_settings.support_contact')}
                            </span>
                        </label>
                    </div>
                </section>

                {selectedSection === 'faqs' ? (
                    <FaqTable
                        items={faqs}
                        canCreate={canCreate}
                        canUpdate={canUpdate}
                        canDelete={canDelete}
                        onCreate={() => openCreate('faqs')}
                        onEdit={openFaqEdit}
                        onDelete={openFaqDelete}
                    />
                ) : null}

                {selectedSection === 'termsAndConditions' ? (
                    <TermAndConditionTable
                        items={termsAndConditions}
                        canCreate={canCreate}
                        canUpdate={canUpdate}
                        canDelete={canDelete}
                        onCreate={() => openCreate('termsAndConditions')}
                        onEdit={openTermAndConditionEdit}
                        onDelete={openTermAndConditionDelete}
                    />
                ) : null}

                {selectedSection === 'supportContacts' ? (
                    <SupportContactTable
                        items={supportContacts}
                        canCreate={canCreate}
                        canUpdate={canUpdate}
                        canDelete={canDelete}
                        onCreate={() => openCreate('supportContacts')}
                        onEdit={openSupportContactEdit}
                        onDelete={openSupportContactDelete}
                    />
                ) : null}
            </PageContent>

            {activeForm?.kind === 'faqs' ? (
                <FaqFormDialog
                    open
                    item={activeForm.item}
                    onOpenChange={(open) => {
                        if (!open) {
                            setActiveForm(null);
                        }
                    }}
                />
            ) : null}

            {activeForm?.kind === 'termsAndConditions' ? (
                <TermAndConditionFormDialog
                    open
                    item={activeForm.item}
                    onOpenChange={(open) => {
                        if (!open) {
                            setActiveForm(null);
                        }
                    }}
                />
            ) : null}

            {activeForm?.kind === 'supportContacts' ? (
                <SupportContactFormDialog
                    open
                    item={activeForm.item}
                    onOpenChange={(open) => {
                        if (!open) {
                            setActiveForm(null);
                        }
                    }}
                />
            ) : null}

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                title={t('settings.general_settings.delete_title')}
                description={t('settings.general_settings.delete_description')}
                confirmLabel={t('common.delete')}
                destructive
                processing={deleteProcessing}
                onConfirm={confirmDelete}
            />
        </>
    );
}
