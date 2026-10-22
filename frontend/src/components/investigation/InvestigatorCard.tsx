"use client";

import Link from "next/link";
import type { ReactNode } from "react";
import { Avatar } from "@/components/ui/Avatar";
import { useI18n } from "@/i18n/client";
import type { InvestigatorSummary } from "@/lib/types";

export function InvestigatorCard({ inv, extra }: { inv: InvestigatorSummary; extra?: ReactNode }) {
  const { t, f } = useI18n();
  return (
    <div className="flex items-center gap-3 rounded-lg border border-line bg-surface p-4">
      <Avatar name={inv.public_name} src={inv.avatar_url} size={44} />
      <div className="min-w-0 flex-1">
        <Link href={`/investigators/${inv.slug}`} className="block truncate font-semibold hover:text-accent">{inv.public_name}</Link>
        <p className="text-xs text-muted">{t.card.investigatorStats(f.number(inv.published_investigations_count), f.number(inv.followers_count), f.number(inv.supporters_count))}</p>
      </div>
      {extra}
    </div>
  );
}
