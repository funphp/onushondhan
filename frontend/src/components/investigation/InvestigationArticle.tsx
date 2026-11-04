"use client";

import Link from "next/link";
import { Alert } from "@/components/ui/Alert";
import { Badge } from "@/components/ui/Badge";
import { Markdown } from "@/components/ui/Markdown";
import { useI18n } from "@/i18n/client";
import { safeHref } from "@/lib/format";
import type { PublicInvestigation } from "@/lib/types";
import { EvidenceGallery } from "./EvidenceGallery";

/** Renders an investigation from its published snapshot (or the owner's preview). */
export function InvestigationArticle({ inv }: { inv: PublicInvestigation }) {
  const { t, f } = useI18n();
  const tombstone = inv.body === null && inv.notice && ["withdrawn", "restricted", "removed"].includes(inv.notice.type);

  return (
    <article className="space-y-8">
      <header className="space-y-4 border-t-4 border-ink pt-4">
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
          <span className="file-stamp mr-2 bg-ink px-2 py-0.5 text-paper">{t.card.file} {inv.id.replace(/^INV-/, "")}</span>
          {inv.category && <Link href={`/category/${inv.category.slug}`} className="inline-flex min-h-6 items-center font-semibold text-accent hover:underline">{inv.category.name}</Link>}
          {inv.subcategory && <><span className="text-muted">/</span><Link href={`/category/${inv.subcategory.slug}`} className="inline-flex min-h-6 items-center text-accent hover:underline">{inv.subcategory.name}</Link></>}
          {inv.status !== "published" && <Badge tone={inv.status === "archived" ? "neutral" : "warning"}>{t.statuses[inv.status as keyof typeof t.statuses] ?? inv.status}</Badge>}
        </div>
        <h1 className="font-display text-3xl font-bold leading-[1.15] sm:text-5xl">{inv.title}</h1>
        <p className="text-sm text-muted">
          {t.common.by} <Link href={`/investigators/${inv.investigator.slug}`} className="font-semibold text-ink hover:underline">{inv.investigator.public_name}</Link>
          {inv.published_at && <> · {t.investigation.publishedOn} <time dateTime={inv.published_at}>{f.date(inv.published_at)}</time></>}
          {inv.version > 1 && inv.updated_at && <> · {t.investigation.updatedOn} <time dateTime={inv.updated_at}>{f.date(inv.updated_at)}</time> ({t.investigation.version(f.digits(inv.version))})</>}
        </p>
        {(inv.location_name || inv.period_start) && (
          <p className="text-sm text-muted">
            {inv.location_name && <>{t.investigation.location}: {inv.location_country ? <Link href={`/location/${inv.location_country}`} className="underline">{inv.location_name}</Link> : inv.location_name}</>}
            {inv.period_start && <> · {t.investigation.period}: {f.date(inv.period_start)}{inv.period_end ? ` – ${f.date(inv.period_end)}` : ""}</>}
          </p>
        )}
      </header>

      {!tombstone && inv.cover_url && (
        <figure className="overflow-hidden bg-[#111]">
          {/* eslint-disable-next-line @next/next/no-img-element -- metadata-free rendition served by our API */}
          <img src={inv.cover_url} alt="" className="aspect-[16/9] w-full object-cover sm:aspect-[21/9]" />
        </figure>
      )}

      {inv.notice && (
        <Alert tone={inv.notice.type === "archived" ? "info" : "warning"} title={inv.notice.message}>
          {inv.notice.detail && <p>{inv.notice.detail}</p>}
          {inv.notice.date && <p className="text-xs text-muted">{f.date(inv.notice.date)}</p>}
        </Alert>
      )}

      <aside aria-label={t.investigation.responsibilityNotice} className="border-l-4 border-ink bg-surface p-4 text-sm text-muted">
        {inv.disclaimer}
      </aside>

      {!tombstone && (
        <>
          {inv.summary && !leadRepeatsBody(inv.summary, inv.body) && <p className="font-serif text-xl leading-relaxed">{inv.summary}</p>}
          <Markdown>{inv.body}</Markdown>

          {inv.findings.length > 0 && (
            <section aria-labelledby="findings-h" className="space-y-3">
              <h2 id="findings-h" className="font-display text-2xl font-bold">{t.investigation.findings}</h2>
              <ol className="space-y-3">
                {inv.findings.map((item, i) => (
                  <li key={i} className="rounded-lg border border-line bg-surface p-4">
                    <h3 className="font-semibold">{f.digits(i + 1)}. {item.title}</h3>
                    {item.body && <Markdown className="prose-report mt-2 text-base">{item.body}</Markdown>}
                  </li>
                ))}
              </ol>
            </section>
          )}

          {inv.recommendations.length > 0 && (
            <section aria-labelledby="rec-h">
              <h2 id="rec-h" className="mb-3 font-display text-2xl font-bold">{t.investigation.recommendations}</h2>
              <ul className="list-disc space-y-1 pl-6">{inv.recommendations.map((r, i) => <li key={i}>{r}</li>)}</ul>
            </section>
          )}

          {inv.timeline.length > 0 && (
            <section aria-labelledby="timeline-h">
              <h2 id="timeline-h" className="mb-3 font-display text-2xl font-bold">{t.investigation.timeline}</h2>
              <ol className="relative space-y-4 border-l-2 border-line pl-6">
                {inv.timeline.map((ev, i) => (
                  <li key={i}>
                    <span aria-hidden className="absolute -left-[7px] mt-1.5 h-3 w-3 rounded-full bg-accent" />
                    {ev.event_date && <time dateTime={ev.event_date} className="text-sm font-medium text-accent">{f.date(ev.event_date)}</time>}
                    <p className="font-semibold">{ev.title}</p>
                    {ev.description && <p className="text-sm text-muted">{ev.description}</p>}
                  </li>
                ))}
              </ol>
            </section>
          )}

          {inv.entities.length > 0 && (
            <section aria-labelledby="entities-h">
              <h2 id="entities-h" className="mb-3 font-display text-2xl font-bold">{t.investigation.entities}</h2>
              <p className="mb-2 text-xs text-muted">{t.investigation.entitiesNote}</p>
              <ul className="flex flex-wrap gap-2">
                {inv.entities.map((e, i) => <li key={i} className="rounded-md border border-line px-3 py-1 text-sm">{e.name} <span className="text-muted">· {t.entityTypes[e.type as keyof typeof t.entityTypes] ?? e.type}{e.role ? ` · ${e.role}` : ""}</span></li>)}
              </ul>
            </section>
          )}

          <EvidenceGallery items={inv.evidence} />

          {inv.sources.length > 0 && (
            <section aria-labelledby="sources-h">
              <h2 id="sources-h" className="mb-3 font-display text-2xl font-bold">{t.investigation.sources}</h2>
              <ol className="list-decimal space-y-2 pl-6 text-sm">
                {inv.sources.map((s, i) => {
                  const href = safeHref(s.url);
                  return (
                    <li key={i}>
                      {href ? <a href={href} rel="noopener noreferrer nofollow ugc" target="_blank" className="font-medium text-accent underline">{s.title}</a> : <span className="font-medium">{s.title}</span>}
                      {s.publisher && <span> — {s.publisher}</span>}
                      {s.source_type && <span className="text-muted"> ({t.sourceTypes[s.source_type as keyof typeof t.sourceTypes] ?? s.source_type})</span>}
                      {s.accessed_at && <span className="text-muted">, {t.investigation.accessed(f.date(s.accessed_at))}</span>}
                      {s.citation_text && <p className="text-muted">{s.citation_text}</p>}
                    </li>
                  );
                })}
              </ol>
              <p className="mt-2 text-xs text-muted">{t.investigation.sourcesNote}</p>
            </section>
          )}

          {(inv.tags.length > 0 || inv.locations.length > 0) && (
            <footer className="flex flex-wrap gap-2 border-t border-line pt-4">
              {inv.tags.map((tag) => <Link key={tag.slug} href={`/tag/${tag.slug}`} className="rounded-full bg-accent-soft px-3 py-1 text-sm">#{tag.name}</Link>)}
              {inv.locations.map((l, i) => l.country && (
                <Link key={i} href={`/location/${l.country}${l.district ? `?location=${encodeURIComponent(l.district)}` : ""}`} className="rounded-full border border-line px-3 py-1 text-sm">
                  {[l.area, l.city, l.district, l.region, f.country(l.country)].filter(Boolean).join(", ")}
                </Link>
              ))}
            </footer>
          )}
        </>
      )}
    </article>
  );
}

/**
 * Summaries are generated from the opening of the report, so showing one as a lead would
 * repeat the first paragraph. Older, hand-written summaries are still shown.
 */
export function leadRepeatsBody(summary: string, body: string | null): boolean {
  if (!body) return false;
  const plain = (s: string) => s.replace(/\[([^\]]*)\]\([^)]*\)/g, "$1").replace(/[#>*_~`|-]+/g, " ").replace(/\s+/g, " ").trim();
  const head = plain(summary.replace(/…$/, "")).slice(0, 80);
  return head.length > 0 && plain(body.slice(0, 2000).replace(/^\s{0,3}#{1,6}\s.*$/gm, "")).startsWith(head);
}
