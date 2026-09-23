import Link from "next/link";
import { Badge } from "@/components/ui/Badge";
import { countryName, formatDate, formatNumber } from "@/lib/format";
import type { InvestigationCard as Card } from "@/lib/types";

export function InvestigationCard({ item, compact }: { item: Card; compact?: boolean }) {
  return (
    <article className="flex h-full flex-col rounded-lg border border-line bg-surface p-5">
      <div className="mb-2 flex flex-wrap items-center gap-2 text-xs text-muted">
        {item.category && <Link href={`/category/${item.category.slug}`} className="font-medium text-accent hover:underline">{item.category.name}</Link>}
        {item.location_country && <span>· {item.location_name ?? countryName(item.location_country)}</span>}
        {item.status === "archived" && <Badge>Archived</Badge>}
      </div>
      <h3 className={`font-serif font-bold leading-snug ${compact ? "text-lg" : "text-xl"}`}>
        <Link href={item.url} className="hover:text-accent">{item.title}</Link>
      </h3>
      {!compact && <p className="mt-2 line-clamp-3 text-sm text-muted">{item.summary}</p>}
      <div className="mt-auto pt-4 text-xs text-muted">
        {item.investigator && (
          <p>
            By <Link href={`/investigators/${item.investigator.slug}`} className="font-medium text-ink hover:underline">{item.investigator.public_name}</Link>
            {item.investigator.identity_mode === "protected_public_identity" && <span title="Publishes under a protected public identity"> (protected identity)</span>}
          </p>
        )}
        <p className="mt-1">
          <time dateTime={item.published_at ?? undefined}>{formatDate(item.published_at)}</time>
          {item.version > 1 && <span> · updated {formatDate(item.updated_at)}</span>}
          <span> · {formatNumber(item.counts.views)} reads · {formatNumber(item.counts.comments)} comments</span>
        </p>
      </div>
    </article>
  );
}

export function InvestigationGrid({ items, compact }: { items: Card[]; compact?: boolean }) {
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {items.map((i) => (
        <InvestigationCard key={i.id} item={i} compact={compact} />
      ))}
    </div>
  );
}
