import { useState } from "react";
import { Head } from "@inertiajs/react";

import {
    BannerFormDialog,
    type BannerItem,
} from "@/components/cms/banner/BannerFormDialog";
import {
    CmsIndexPage,
    type CmsFilters,
} from "@/components/cms/shared/CmsIndexPage";
import type { Paginated } from "@/components/Pagination";
import { StatusBadge } from "@/components/StatusBadge";
import { useTranslation } from "@/hooks/useTranslation";

type Props = {
    items: Paginated<BannerItem>;
    filters: CmsFilters;
};

export default function BannersIndex({ items, filters }: Props) {
    const { t, locale } = useTranslation();
    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<BannerItem | null>(null);

    return (
        <>
            <Head title={t("menu.cms_banners")} />
            <CmsIndexPage
                createLabelKey="cms.create_banner"
                indexHref="/cms/banners"
                destroyBase="/cms/banners"
                items={items}
                filters={filters}
                statusFilter="active"
                onCreate={() => {
                    setEditingItem(null);
                    setFormOpen(true);
                }}
                onEdit={(row) => {
                    setEditingItem(row);
                    setFormOpen(true);
                }}
                formDialog={
                    <BannerFormDialog
                        open={formOpen}
                        onOpenChange={(open) => {
                            setFormOpen(open);

                            if (!open) {
                                setEditingItem(null);
                            }
                        }}
                        item={editingItem}
                    />
                }
                columns={[
                    {
                        id: "title",
                        header: t("cms.image"),
                        mobile: "title",
                        sortable: true,
                        className: "font-medium",
                        cell: (row) => {
                            const imageUrl =
                                locale === "en"
                                    ? row.image_url_en
                                    : locale === "zh"
                                      ? row.image_url_zh
                                      : row.image_url_my;

                            return (
                                <span className="inline-flex items-center gap-2">
                                    {imageUrl ? (
                                        <img
                                            src={imageUrl}
                                            alt=""
                                            className="size-8 rounded object-cover"
                                        />
                                    ) : null}
                                </span>
                            );
                        },
                    },
                    {
                        id: "sort_order",
                        header: t("cms.sort_order"),
                        sortable: true,
                        cell: (row) => row.sort_order,
                    },
                    {
                        id: "is_active",
                        header: t("common.status"),
                        sortable: true,
                        mobile: "badge",
                        cell: (row) => (
                            <StatusBadge
                                status={row.is_active ? "active" : "inactive"}
                            />
                        ),
                    },
                ]}
            />
        </>
    );
}
