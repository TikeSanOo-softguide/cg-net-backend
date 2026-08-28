import { FormEvent, useState } from "react";
import type { InertiaFormProps } from "@inertiajs/react";
import { ContactIcon } from "lucide-react";
import { FormField } from "@/components/ui/form-field";
import { CmsFormShell } from "../shared/CmsFormShell";
import { Input } from "@/components/ui/input";
import { useTranslation } from "@/hooks/useTranslation";

export type ContactFormValues = {
    contact_point: string;
};

type ContactFormProps = {
    form: InertiaFormProps<ContactFormValues>;
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    mode?: "create" | "edit";
};

export function ContactForm({
    form,
    onSubmit,
    onCancel,
    mode = "create",
}: ContactFormProps) {
    const { t } = useTranslation();
    const [submitted, setSubmitted] = useState(false);

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        setSubmitted(true);
        if (!form.data.contact_point.trim()) {
            return;
        }

        form.clearErrors("contact_point");
        onSubmit(event);
    };

    const handleChange = (value: string) => {
        form.setData("contact_point", value);

        if (value.trim()) {
            form.clearErrors("contact_point");
        }
    };

    const contactPointError =
        submitted && !form.data.contact_point.trim()
            ? t("cms.contact.validation.contact_point_required")
            : form.errors.contact_point;

    return (
        <CmsFormShell
            onSubmit={handleSubmit}
            onCancel={onCancel}
            processing={form.processing}
            mode={mode}
        >
            <FormField
                label={t("cms.contact_point")}
                htmlFor="contact_point"
                error={contactPointError}
                icon={ContactIcon}
                required
                className="sm:col-span-2"
            >
                <Input
                    id="contact_point"
                    name="contact_point"
                    value={form.data.contact_point}
                    onChange={(event) => handleChange(event.target.value)}
                />
            </FormField>
        </CmsFormShell>
    );
}
