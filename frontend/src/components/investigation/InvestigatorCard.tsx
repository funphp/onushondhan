import Link from "next/link";
import { Avatar } from "@/components/ui/Avatar";
import { formatNumber } from "@/lib/format";
import type { InvestigatorSummary } from "@/lib/types";

export function InvestigatorCard({ inv, extra }: { inv: InvestigatorSummary; extra?: React.ReactNode }) {
  return (
    <div className="flex items-center gap-3 rounded-lg border border-line bg-surface p-4">
      <Avatar name={inv.public_name} src={inv.avatar_url} size={44} />
      <div className="min-w-0 flex-1">
        <Link href={`/investigators/${inv.slug}`} className="block truncate font-semibold hover:text-accent">{inv.public_name}</Link>
        <p className="text-xs text-muted">
          {formatNumber(inv.published_investigations_count)} published · {formatNumber(inv.followers_count)} followers · {formatNumber(inv.supporters_count)} supporters
        </p>
      </div>
      {extra}
    </div>
  );
}
