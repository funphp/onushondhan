"use client";

import { Badge } from "@/components/ui/Badge";
import { labelFor } from "@/i18n";
import { useI18n } from "@/i18n/client";

export function StatusBadge({ status }: { status: string }) {
  const { t } = useI18n();
  const tone = status === "published" ? "success" : status === "draft" ? "neutral" : status === "restricted" || status === "removed" ? "danger" : "warning";
  return <Badge tone={tone}>{labelFor(t.statuses, status)}</Badge>;
}
