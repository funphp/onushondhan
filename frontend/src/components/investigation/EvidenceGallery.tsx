"use client";

import { useI18n } from "@/i18n/client";
import type { Evidence } from "@/lib/types";

/**
 * Public evidence. Images show the metadata-stripped rendition; audio/video stream
 * inline; everything else is a download. Files are always served by the API with
 * safe headers and every access is logged.
 */
export function EvidenceGallery({ items }: { items: Evidence[] }) {
  const { t, f } = useI18n();
  if (items.length === 0) return null;
  return (
    <section aria-labelledby="evidence-h" className="space-y-4">
      <h2 id="evidence-h" className="font-display text-2xl font-bold">{t.evidence.title}</h2>
      <ul className="grid gap-4 sm:grid-cols-2">
        {items.map((e) => {
          const stream = `/api/v1/evidence/${e.id}/preview`;
          return (
            <li key={e.id} className="rounded-lg border border-line bg-surface p-3">
              <figure>
                {e.kind === "image" && e.preview_url && (
                  // eslint-disable-next-line @next/next/no-img-element -- sanitised rendition from our API
                  <img src={e.preview_url} alt={e.caption ?? t.evidence.imageAlt} loading="lazy" className="max-h-80 w-full rounded object-contain" width={e.metadata.width} height={e.metadata.height} />
                )}
                {e.kind === "video" && <video controls preload="none" className="w-full rounded" src={stream}><track kind="captions" /></video>}
                {e.kind === "audio" && <audio controls preload="none" className="w-full" src={stream} />}
                <figcaption className="mt-2 text-sm">
                  {e.caption && <p className="mb-1">{e.caption}</p>}
                  <p className="text-xs text-muted">
                    {e.display_name} · {f.bytes(e.size_bytes)} · {t.evidence.added(f.date(e.uploaded_at))}
                    {e.metadata.pages ? ` · ${t.evidence.pages(f.digits(e.metadata.pages))}` : ""}
                  </p>
                  <p className="mt-1 break-all font-mono text-[11px] text-muted" title={t.evidence.fingerprint}>SHA-256: {e.sha256}</p>
                  <a href={e.download_url} className="mt-2 inline-block text-sm font-medium text-accent underline">{t.common.download}</a>
                </figcaption>
              </figure>
            </li>
          );
        })}
      </ul>
      <p className="text-xs text-muted">{t.evidence.note}</p>
    </section>
  );
}
