"use client";

import Link from "next/link";
import { Badge } from "@/components/ui/Badge";
import { cn } from "@/components/ui/cn";
import { useI18n } from "@/i18n/client";
import type { InvestigationCard as Card } from "@/lib/types";

/** Header image, or a hatched "case file" panel with the category when a report has none. */
export function CoverArt({ item, className, large, bare }: { item: Card; className?: string; large?: boolean; bare?: boolean }) {
  if (item.cover_url) {
    // eslint-disable-next-line @next/next/no-img-element -- metadata-free rendition served by our API
    return <img src={item.cover_url} alt="" loading={large ? "eager" : "lazy"} className={cn("h-full w-full bg-[#1a1a1a] object-cover", className)} />;
  }
  return (
    <div aria-hidden className={cn("dossier-hatch flex h-full w-full flex-col justify-start gap-1 p-4 text-white", className)}>
      {!bare && <>
      <span className="file-stamp text-[#ff6b78]">{item.id}</span>
      {item.category && <span className={cn("font-display font-bold leading-tight text-white/80", large ? "text-3xl" : "text-xl")}>{item.category.name}</span>}
      </>}
    </div>
  );
}

/** Category (red) and file number (mono) — the dossier label above every headline. */
export function FileLabel({ item, onDark }: { item: Card; onDark?: boolean }) {
  const { t } = useI18n();
  return (
    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
      {item.category && <Link href={`/category/${item.category.slug}`} className={cn("inline-flex min-h-6 items-center font-semibold hover:underline", onDark ? "text-[#ff6b78]" : "text-accent")}>{item.category.name}</Link>}
      <span className={cn("file-stamp", onDark ? "text-white/70" : "text-muted")}>{t.card.file} {item.id.replace(/^INV-/, "")}</span>
      {item.status === "archived" && <Badge>{t.card.archived}</Badge>}
    </div>
  );
}

function Byline({ item, onDark }: { item: Card; onDark?: boolean }) {
  const { t, f } = useI18n();
  return (
    <p className={cn("text-xs", onDark ? "text-white/75" : "text-muted")}>
      {item.investigator && (
        <><Link href={`/investigators/${item.investigator.slug}`} className={cn("font-semibold hover:underline", onDark ? "text-white" : "text-ink")}>{item.investigator.public_name}</Link> · </>
      )}
      <time dateTime={item.published_at ?? undefined}>{f.date(item.published_at)}</time>
      {item.version > 1 && <span> · {t.card.updated(f.date(item.updated_at))}</span>}
      <span> · {t.card.stats(f.number(item.counts.views), f.number(item.counts.comments))}</span>
    </p>
  );
}

export function InvestigationCard({ item, compact }: { item: Card; compact?: boolean }) {
  return (
    <article className="group flex h-full flex-col border-t-2 border-ink">
      {!compact && (
        <Link href={item.url} tabIndex={-1} aria-hidden className="mt-3 block aspect-[16/10] overflow-hidden">
          <CoverArt item={item} className="transition-transform duration-500 group-hover:scale-[1.03]" />
        </Link>
      )}
      <div className="flex flex-1 flex-col gap-2 pt-3">
        <FileLabel item={item} />
        <h3 className={cn("font-display font-bold leading-snug", compact ? "text-lg" : "text-[1.35rem]")}>
          <Link href={item.url} className="hover:text-accent">{item.title}</Link>
        </h3>
        {!compact && <p className="line-clamp-3 text-sm leading-relaxed text-muted">{item.summary}</p>}
        <div className="mt-auto pt-1"><Byline item={item} /></div>
      </div>
    </article>
  );
}

export function InvestigationGrid({ items, compact }: { items: Card[]; compact?: boolean }) {
  return (
    <div className="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
      {items.map((i) => (
        <InvestigationCard key={i.id} item={i} compact={compact} />
      ))}
    </div>
  );
}

/** Big photo-led lead story with the headline set over the image. */
export function CoverStory({ item }: { item: Card }) {
  const { t } = useI18n();
  return (
    <article className="relative overflow-hidden bg-[#111] text-white">
      <div className="aspect-[4/3] sm:aspect-[16/9]">
        <CoverArt item={item} large bare />
      </div>
      <div className="absolute inset-0 bg-gradient-to-t from-black/95 via-black/55 to-black/5" aria-hidden />
      <div className="absolute inset-x-0 bottom-0 space-y-3 p-5 sm:p-8">
        <span className="file-stamp inline-block bg-accent px-2 py-0.5 text-white">{t.card.coverStory}</span>
        <FileLabel item={item} onDark />
        <h2 className="max-w-3xl font-display text-2xl font-bold leading-tight sm:text-4xl">
          <Link href={item.url} className="decoration-[#ff3b4e] decoration-2 underline-offset-4 hover:underline">{item.title}</Link>
        </h2>
        <p className="hidden max-w-2xl text-sm leading-relaxed text-white/85 sm:line-clamp-2">{item.summary}</p>
        <Byline item={item} onDark />
      </div>
    </article>
  );
}

/** Numbered list (০১, ০২, …) without boxes, for "most read" style rails. */
export function NumberedList({ items }: { items: Card[] }) {
  const { f } = useI18n();
  return (
    <ol className="divide-y divide-line">
      {items.map((item, i) => (
        <li key={item.id} className="flex gap-4 py-3 first:pt-0">
          <span aria-hidden className="w-10 shrink-0 font-display text-3xl font-bold leading-none text-accent">{i < 9 ? f.digits(0) : ""}{f.digits(i + 1)}</span>
          <div className="min-w-0 space-y-1">
            <h3 className="font-display text-base font-bold leading-snug"><Link href={item.url} className="hover:text-accent">{item.title}</Link></h3>
            <p className="text-xs text-muted">{item.investigator?.public_name}{item.category ? ` · ${item.category.name}` : ""}</p>
          </div>
        </li>
      ))}
    </ol>
  );
}
