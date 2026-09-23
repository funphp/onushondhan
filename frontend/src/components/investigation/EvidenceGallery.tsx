import { formatBytes, formatDate } from "@/lib/format";
import type { Evidence } from "@/lib/types";

/**
 * Public evidence. Images show the metadata-stripped rendition; audio/video stream
 * inline; everything else is a download. Files are always served by the API with
 * safe headers and every access is logged.
 */
export function EvidenceGallery({ items }: { items: Evidence[] }) {
  if (items.length === 0) return null;
  return (
    <section aria-labelledby="evidence-h" className="space-y-4">
      <h2 id="evidence-h" className="font-serif text-2xl font-bold">Evidence</h2>
      <ul className="grid gap-4 sm:grid-cols-2">
        {items.map((e) => {
          const stream = `/api/v1/evidence/${e.id}/preview`;
          return (
            <li key={e.id} className="rounded-lg border border-line bg-surface p-3">
              <figure>
                {e.kind === "image" && e.preview_url && (
                  // eslint-disable-next-line @next/next/no-img-element -- sanitised rendition from our API
                  <img src={e.preview_url} alt={e.caption ?? "Evidence image"} loading="lazy" className="max-h-80 w-full rounded object-contain" width={e.metadata.width} height={e.metadata.height} />
                )}
                {e.kind === "video" && <video controls preload="none" className="w-full rounded" src={stream}><track kind="captions" /></video>}
                {e.kind === "audio" && <audio controls preload="none" className="w-full" src={stream} />}
                <figcaption className="mt-2 text-sm">
                  {e.caption && <p className="mb-1">{e.caption}</p>}
                  <p className="text-xs text-muted">
                    {e.display_name} · {formatBytes(e.size_bytes)} · added {formatDate(e.uploaded_at)}
                    {e.metadata.pages ? ` · ${e.metadata.pages} pages` : ""}
                  </p>
                  <p className="mt-1 break-all font-mono text-[11px] text-muted" title="SHA-256 fingerprint of the stored file">SHA-256: {e.sha256}</p>
                  <a href={e.download_url} className="mt-2 inline-block text-sm font-medium text-accent underline">Download</a>
                </figcaption>
              </figure>
            </li>
          );
        })}
      </ul>
      <p className="text-xs text-muted">Evidence is supplied by the investigator. Onushondhan scans files where possible but does not verify their authenticity.</p>
    </section>
  );
}
