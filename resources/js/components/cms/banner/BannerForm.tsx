import { FormEvent, useState } from "react";
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
import { DatePicker } from "@/components/ui/date-picker";
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
import { SquareImageUpload } from "@/components/ui/square-image-upload";

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
    const [imageEn, setImageEn] = useState<File | null>(null);
    const [imageZh, setImageZh] = useState<File | null>(null);
    const [imageMy, setImageMy] = useState<File | null>(null);
    const [submitted, setSubmitted] = useState(false);
    const formatDateForInput = (date: string | null) => {
        if (!date) return "";

        return date.slice(0, 10);
    };
    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();
        onSubmit(event);
    };

    return (
        <CmsFormShell
            onSubmit={handleSubmit}
            onCancel={onCancel}
            processing={form.processing}
            mode={mode}
        >
            <FormField
                label={t("cms.banner.image_en")}
                htmlFor="banner-image-en"
                required
                className="sm:col-span-2"
                error={
                    submitted && !imageEn && !imageUrls?.en
                        ? t("cms.banner.validation.image_en_required")
                        : form.errors.image_url_en ? t("cms.banner.validation.image_en_required"): undefined
                }
            >
                <SquareImageUpload
                    id="banner-image-en"
                    width={520}
                    height={150}
                    value={imageEn}
                    existingUrl={imageUrls?.en}
                    onChange={(file) => {
                        setImageEn(file);
                        form.setData("image_url_en", file);
                        form.clearErrors("image_url_en");
                    }}
                />
            </FormField>
            <FormField
                label={t("cms.banner.image_zh")}
                htmlFor="banner-image-zh"
                required
                className="sm:col-span-2"
                error={
                    submitted && !imageZh && !imageUrls?.zh
                        ? t("validation.required")
                        : form.errors.image_url_zh ? t("cms.banner.validation.image_zh_required"): undefined
                }
            >
                <SquareImageUpload
                    id="banner-image-zh"
                    width={520}
                    height={150}
                    value={imageZh}
                    existingUrl={imageUrls?.zh}
                    onChange={(file) => {
                        setImageZh(file);
                        form.setData("image_url_zh", file);
                        form.clearErrors("image_url_zh");
                    }}
                />
            </FormField>
            <FormField
                label={t("cms.banner.image_my")}
                htmlFor="banner-image-my"
                required
                className="sm:col-span-2"
                error={
                    submitted && !imageMy && !imageUrls?.my
                        ? t("validation.required")
                        : form.errors.image_url_my ? t("cms.banner.validation.image_my_required"): undefined
                }
            >
                <SquareImageUpload
                    id="banner-image-my"
                    width={520}
                    height={150}
                    value={imageMy}
                    existingUrl={imageUrls?.my}
                    onChange={(file) => {
                        setImageMy(file);
                        form.setData("image_url_my", file);
                        form.clearErrors("image_url_my");
                    }}
                />
            </FormField>
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
                <DatePicker
                    id="start_date"
                    value={form.data.start_date}
                    max={form.data.end_date || undefined}
                    onChange={(value) => form.setData("start_date", value)}
                />
            </FormField>
            <FormField
                label={t("cms.end_date")}
                htmlFor="end_date"
                error={form.errors.end_date}
                icon={CalendarClockIcon}
            >
                <DatePicker
                    id="end_date"
                    value={form.data.end_date}
                    min={form.data.start_date || undefined}
                    onChange={(value) => form.setData("end_date", value)}
                />
            </FormField>
        </CmsFormShell>
    );
}
