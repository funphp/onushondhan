"use client";

import { Badge } from "@/components/ui/Badge";
import { labelFor } from "@/i18n";
import { useI18n } from "@/i18n/client";

export function StatusBadge({ status, review }: { status: string; review?: { status: string } | null }) {
  const { t } = useI18n();
  if (review?.status === "pending") return <Badge tone="warning">{t.review.pending}</Badge>;
  if (review?.status === "rejected") return <Badge tone="danger">{t.review.rejected}</Badge>;
  const tone = status === "published" ? "success" : status === "draft" ? "neutral" : status === "restricted" || status === "removed" ? "danger" : "warning";
  return <Badge tone={tone}>{labelFor(t.statuses, status)}</Badge>;
}
