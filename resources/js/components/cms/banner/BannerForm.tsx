import { FormEvent } from "react";
import type { InertiaFormProps } from "@inertiajs/react";
import {
    CalendarClockIcon,
    CalendarIcon,
    CircleDotIcon,
    HashIcon,
    Link2Icon,
    TypeIcon,
} from "lucide-react";

import { FormField } from "@/components/ui/form-field";
import { CmsFormShell } from "@/components/cms/shared/CmsFormShell";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { useTranslation } from "@/hooks/useTranslation";
import { CmsImageField } from "../shared/CmsImageField";

export type BannerFormValues = {
    image_url_en: File | null;
    image_url_zh: File | null;
    image_url_my: File | null;
    sort_order: number;
    start_date: string;
    end_date: string;
    is_active: boolean;
};

type BannerFormProps = {
    form: InertiaFormProps<BannerFormValues>;
    onSubmit: (event: FormEvent) => void;
    onCancel?: () => void;
    mode?: "create" | "edit";
    imageUrls?: {
        en?: string | null;
        zh?: string | null;
        my?: string | null;
    };
};

export function BannerForm({
    form,
    onSubmit,
    onCancel,
    mode = "create",
    imageUrls,
}: BannerFormProps) {
    const { t } = useTranslation();
    const formatDateForInput = (date: string | null) => {
        if (!date) return "";

        return date.slice(0, 10);
    };

    return (
        <CmsFormShell
            onSubmit={onSubmit}
            onCancel={onCancel}
            processing={form.processing}
            mode={mode}
        >
            <CmsImageField
                error={form.errors.image_url_en}
                currentUrl={imageUrls?.en}
                required={!imageUrls?.en}
                onChange={(file) => form.setData("image_url_en", file)}
            />
            <CmsImageField
                error={form.errors.image_url_zh}
                currentUrl={imageUrls?.zh}
                required={!imageUrls?.zh}
                onChange={(file) => form.setData("image_url_zh", file)}
            />
            <CmsImageField
                error={form.errors.image_url_my}
                currentUrl={imageUrls?.my}
                required={!imageUrls?.my}
                onChange={(file) => form.setData("image_url_my", file)}
            />
            <FormField
                label={t("cms.sort_order")}
                htmlFor="sort_order"
                error={form.errors.sort_order}
                icon={HashIcon}
            >
                <Input
                    id="sort_order"
                    type="number"
                    min={0}
                    value={form.data.sort_order}
                    onChange={(event) =>
                        form.setData("sort_order", Number(event.target.value))
                    }
                />
            </FormField>
            <FormField
                label={t("common.status")}
                htmlFor="is_active"
                error={form.errors.is_active}
                icon={CircleDotIcon}
            >
                <Select
                    value={form.data.is_active ? "1" : "0"}
                    onValueChange={(value) =>
                        form.setData("is_active", value === "1")
                    }
                >
                    <SelectTrigger id="is_active" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="1">{t("status.active")}</SelectItem>
                        <SelectItem value="0">
                            {t("status.inactive")}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </FormField>
            <FormField
                label={t("cms.start_date")}
                htmlFor="start_date"
                error={form.errors.start_date}
                icon={CalendarIcon}
            >
                <Input
                    id="start_date"
                    type="date"
                    value={form.data.start_date}
                    onChange={(event) =>
                        form.setData("start_date", event.target.value)
                    }
                />
            </FormField>
            <FormField
                label={t("cms.end_date")}
                htmlFor="end_date"
                error={form.errors.end_date}
                icon={CalendarClockIcon}
            >
                <Input
                    id="end_date"
                    type="date"
                    value={form.data.end_date}
                    onChange={(event) =>
                        form.setData("end_date", event.target.value)
                    }
                />
            </FormField>
        </CmsFormShell>
    );
}
