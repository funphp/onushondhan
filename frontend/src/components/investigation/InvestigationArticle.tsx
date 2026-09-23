import Link from "next/link";
import { Alert } from "@/components/ui/Alert";
import { Badge } from "@/components/ui/Badge";
import { Markdown } from "@/components/ui/Markdown";
import { countryName, formatDate, humanize, safeHref } from "@/lib/format";
import type { PublicInvestigation } from "@/lib/types";
import { EvidenceGallery } from "./EvidenceGallery";

/** Renders an investigation from its published snapshot (or the owner's preview). */
export function InvestigationArticle({ inv }: { inv: PublicInvestigation }) {
  const tombstone = inv.body === null && inv.notice && ["withdrawn", "restricted", "removed"].includes(inv.notice.type);

  return (
    <article className="space-y-8">
      <header className="space-y-3">
        <div className="flex flex-wrap items-center gap-2 text-sm">
          {inv.category && <Link href={`/category/${inv.category.slug}`} className="font-medium text-accent hover:underline">{inv.category.name}</Link>}
          {inv.subcategory && <><span className="text-muted">/</span><Link href={`/category/${inv.subcategory.slug}`} className="text-accent hover:underline">{inv.subcategory.name}</Link></>}
          {inv.status !== "published" && <Badge tone={inv.status === "archived" ? "neutral" : "warning"}>{humanize(inv.status)}</Badge>}
        </div>
        <h1 className="font-serif text-3xl font-bold leading-tight sm:text-4xl">{inv.title}</h1>
        <p className="text-sm text-muted">
          By <Link href={`/investigators/${inv.investigator.slug}`} className="font-semibold text-ink hover:underline">{inv.investigator.public_name}</Link>
          {inv.investigator.identity_mode === "protected_public_identity" && <span> · publishing under a protected public identity</span>}
          {inv.published_at && <> · Published <time dateTime={inv.published_at}>{formatDate(inv.published_at)}</time></>}
          {inv.version > 1 && inv.updated_at && <> · Updated <time dateTime={inv.updated_at}>{formatDate(inv.updated_at)}</time> (version {inv.version})</>}
        </p>
        {(inv.location_name || inv.period_start) && (
          <p className="text-sm text-muted">
            {inv.location_name && <>Location: {inv.location_country ? <Link href={`/location/${inv.location_country}`} className="underline">{inv.location_name}</Link> : inv.location_name}</>}
            {inv.period_start && <> · Period: {formatDate(inv.period_start)}{inv.period_end ? ` – ${formatDate(inv.period_end)}` : ""}</>}
          </p>
        )}
      </header>

      {inv.notice && (
        <Alert tone={inv.notice.type === "archived" ? "info" : "warning"} title={inv.notice.message}>
          {inv.notice.detail && <p>{inv.notice.detail}</p>}
          {inv.notice.date && <p className="text-xs text-muted">{formatDate(inv.notice.date)}</p>}
        </Alert>
      )}

      <aside aria-label="Responsibility notice" className="rounded-md border-l-4 border-accent bg-accent-soft p-4 text-sm">
        {inv.disclaimer}
      </aside>

      {!tombstone && (
        <>
          {inv.summary && <p className="font-serif text-xl leading-relaxed">{inv.summary}</p>}
          <Markdown>{inv.body}</Markdown>

          {inv.findings.length > 0 && (
            <section aria-labelledby="findings-h" className="space-y-3">
              <h2 id="findings-h" className="font-serif text-2xl font-bold">Findings</h2>
              <ol className="space-y-3">
                {inv.findings.map((f, i) => (
                  <li key={i} className="rounded-lg border border-line bg-surface p-4">
                    <h3 className="font-semibold">{i + 1}. {f.title}</h3>
                    {f.body && <Markdown className="prose-report mt-2 text-base">{f.body}</Markdown>}
                  </li>
                ))}
              </ol>
            </section>
          )}

          {inv.recommendations.length > 0 && (
            <section aria-labelledby="rec-h">
              <h2 id="rec-h" className="mb-3 font-serif text-2xl font-bold">Recommendations</h2>
              <ul className="list-disc space-y-1 pl-6">{inv.recommendations.map((r, i) => <li key={i}>{r}</li>)}</ul>
            </section>
          )}

          {inv.timeline.length > 0 && (
            <section aria-labelledby="timeline-h">
              <h2 id="timeline-h" className="mb-3 font-serif text-2xl font-bold">Timeline</h2>
              <ol className="relative space-y-4 border-l-2 border-line pl-6">
                {inv.timeline.map((t, i) => (
                  <li key={i}>
                    <span aria-hidden className="absolute -left-[7px] mt-1.5 h-3 w-3 rounded-full bg-accent" />
                    {t.event_date && <time dateTime={t.event_date} className="text-sm font-medium text-accent">{formatDate(t.event_date)}</time>}
                    <p className="font-semibold">{t.title}</p>
                    {t.description && <p className="text-sm text-muted">{t.description}</p>}
                  </li>
                ))}
              </ol>
            </section>
          )}

          {inv.entities.length > 0 && (
            <section aria-labelledby="entities-h">
              <h2 id="entities-h" className="mb-3 font-serif text-2xl font-bold">People &amp; entities referenced</h2>
              <p className="mb-2 text-xs text-muted">Listed as referenced by the investigator. Inclusion is not a finding of wrongdoing.</p>
              <ul className="flex flex-wrap gap-2">
                {inv.entities.map((e, i) => <li key={i} className="rounded-md border border-line px-3 py-1 text-sm">{e.name} <span className="text-muted">· {humanize(e.type)}{e.role ? ` · ${e.role}` : ""}</span></li>)}
              </ul>
            </section>
          )}

          <EvidenceGallery items={inv.evidence} />

          {inv.sources.length > 0 && (
            <section aria-labelledby="sources-h">
              <h2 id="sources-h" className="mb-3 font-serif text-2xl font-bold">Sources</h2>
              <ol className="list-decimal space-y-2 pl-6 text-sm">
                {inv.sources.map((s, i) => {
                  const href = safeHref(s.url);
                  return (
                    <li key={i}>
                      {href ? <a href={href} rel="noopener noreferrer nofollow ugc" target="_blank" className="font-medium text-accent underline">{s.title}</a> : <span className="font-medium">{s.title}</span>}
                      {s.publisher && <span> — {s.publisher}</span>}
                      {s.source_type && <span className="text-muted"> ({humanize(s.source_type)})</span>}
                      {s.accessed_at && <span className="text-muted">, accessed {formatDate(s.accessed_at)}</span>}
                      {s.citation_text && <p className="text-muted">{s.citation_text}</p>}
                    </li>
                  );
                })}
              </ol>
              <p className="mt-2 text-xs text-muted">A cited source does not necessarily endorse this report&apos;s conclusions.</p>
            </section>
          )}

          {(inv.tags.length > 0 || inv.locations.length > 0) && (
            <footer className="flex flex-wrap gap-2 border-t border-line pt-4">
              {inv.tags.map((t) => <Link key={t.slug} href={`/tag/${t.slug}`} className="rounded-full bg-accent-soft px-3 py-1 text-sm">#{t.name}</Link>)}
              {inv.locations.map((l, i) => l.country && (
                <Link key={i} href={`/location/${l.country}${l.district ? `?location=${encodeURIComponent(l.district)}` : ""}`} className="rounded-full border border-line px-3 py-1 text-sm">
                  {[l.area, l.city, l.district, l.region, countryName(l.country)].filter(Boolean).join(", ")}
                </Link>
              ))}
            </footer>
          )}
        </>
      )}
    </article>
  );
}
