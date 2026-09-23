"use client";

import { useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { SelectInput, TextInput } from "@/components/ui/Field";
import { api, errorMessage } from "@/lib/api";
import { formatBytes, formatDateTime, humanize } from "@/lib/format";
import type { Evidence } from "@/lib/types";

const VIS = [
  { value: "private", label: "Private — only you" },
  { value: "restricted", label: "Restricted — you and platform reviewers" },
  { value: "public", label: "Public — shown with the published investigation" },
];

const ACCEPT = ".pdf,.jpg,.jpeg,.png,.gif,.webp,.mp4,.m4v,.webm,.mov,.mp3,.m4a,.wav,.ogg,.oga,.txt,.csv,.doc,.docx,.xls,.xlsx,.odt,.ods";

export function EvidenceManager({ investigationId, initial, disabled }: { investigationId: string; initial: Evidence[]; disabled?: boolean }) {
  const [items, setItems] = useState<Evidence[]>(initial);
  const [file, setFile] = useState<File | null>(null);
  const [caption, setCaption] = useState("");
  const [visibility, setVisibility] = useState("private");
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<{ tone: "success" | "danger"; text: string } | null>(null);

  const reload = async () => setItems((await api<{ data: Evidence[] }>(`/me/investigations/${investigationId}/evidence`)).data);
  const patch = async (e: Evidence, body: Record<string, unknown>) => {
    try {
      await api(`/me/evidence/${e.id}`, { method: "PATCH", body });
      await reload();
    } catch (x) {
      setMsg({ tone: "danger", text: errorMessage(x) });
    }
  };

  return (
    <div className="space-y-6">
      <Alert tone="warning" title="Before uploading">
        Files can contain hidden metadata (GPS location, author names, device details). Public images are re-encoded without metadata, but videos, audio, PDFs and documents are published as uploaded — remove sensitive metadata first. Only upload material you have the right to publish.
      </Alert>
      {!disabled && (
        <form className="grid gap-4 rounded-lg border border-line bg-paper p-4 sm:grid-cols-2" onSubmit={async (e) => {
          e.preventDefault();
          if (!file) return;
          setBusy(true);
          setMsg(null);
          const fd = new FormData();
          fd.append("file", file);
          fd.append("caption", caption);
          fd.append("visibility", visibility);
          try {
            await api(`/me/investigations/${investigationId}/evidence`, { method: "POST", body: fd });
            setFile(null);
            setCaption("");
            // Safe default: every new upload starts private.
            setVisibility("private");
            (e.target as HTMLFormElement).reset();
            await reload();
            setMsg({ tone: "success", text: "Uploaded. The file is hashed and scanned before it can be shown publicly." });
          } catch (x) {
            setMsg({ tone: "danger", text: errorMessage(x) });
          } finally {
            setBusy(false);
          }
        }}>
          <div className="flex flex-col gap-1 sm:col-span-2">
            <label htmlFor="ev-file" className="text-sm font-medium">File</label>
            <input id="ev-file" type="file" accept={ACCEPT} required onChange={(e) => setFile(e.target.files?.[0] ?? null)} className="text-sm" aria-describedby="ev-hint" />
            <p id="ev-hint" className="text-xs text-muted">PDF, images, video, audio, text/CSV and office documents. Executables, scripts, HTML and SVG are not accepted.</p>
          </div>
          <TextInput label="Caption" value={caption} onChange={(e) => setCaption(e.target.value)} maxLength={1000} />
          <SelectInput label="Visibility" value={visibility} onChange={(e) => setVisibility(e.target.value)} options={VIS} />
          <div><Button type="submit" loading={busy} disabled={!file}>Upload evidence</Button></div>
        </form>
      )}
      {msg && <Alert tone={msg.tone} live>{msg.text}</Alert>}
      {items.length === 0 ? <p className="text-sm text-muted">No evidence yet.</p> : (
        <ul className="space-y-3">
          {items.map((e) => (
            <li key={e.id} className="rounded-lg border border-line bg-surface p-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="truncate font-medium">{e.original_name}</p>
                  <p className="text-xs text-muted">{humanize(e.kind)} · {formatBytes(e.size_bytes)} · v{e.version} · uploaded {formatDateTime(e.uploaded_at)}</p>
                  <p className="break-all font-mono text-[11px] text-muted">SHA-256 {e.sha256}</p>
                </div>
                <div className="flex flex-wrap gap-1">
                  <Badge tone={e.scan_status === "clean" ? "success" : e.scan_status === "infected" ? "danger" : "neutral"}>Scan: {humanize(e.scan_status)}</Badge>
                  {e.moderation_status !== "visible" && <Badge tone="danger">{humanize(e.moderation_status)}</Badge>}
                </div>
              </div>
              {e.scan_status === "infected" && <Alert tone="danger" className="mt-2">This file was flagged as malicious and quarantined. It will never be served.</Alert>}
              <div className="mt-3 grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                <TextInput label="Caption" defaultValue={e.caption ?? ""} disabled={disabled} onBlur={(ev) => ev.target.value !== (e.caption ?? "") && patch(e, { caption: ev.target.value })} />
                <SelectInput label="Visibility" value={e.visibility} disabled={disabled || e.scan_status === "infected"} onChange={(ev) => patch(e, { visibility: ev.target.value })} options={VIS} />
                <div className="flex gap-2">
                  {e.scan_status !== "infected" && <a href={e.download_url} className="rounded-md border border-line px-3 py-2 text-sm hover:bg-accent-soft">Download</a>}
                  {!disabled && (
                    <Button type="button" size="md" variant="ghost" onClick={async () => {
                      if (!window.confirm("Delete this evidence? It will be removed from the investigation (an audit record is kept).")) return;
                      try {
                        await api(`/me/evidence/${e.id}`, { method: "DELETE" });
                        await reload();
                      } catch (x) {
                        setMsg({ tone: "danger", text: errorMessage(x) });
                      }
                    }}>Delete</Button>
                  )}
                </div>
              </div>
              {!disabled && (
                <details className="mt-3 text-sm">
                  <summary className="cursor-pointer text-accent">Replace with a new version</summary>
                  <input type="file" accept={ACCEPT} aria-label={`Replace ${e.original_name}`} className="mt-2 text-sm" onChange={async (ev) => {
                    const f = ev.target.files?.[0];
                    if (!f) return;
                    const fd = new FormData();
                    fd.append("file", f);
                    try {
                      await api(`/me/evidence/${e.id}/versions`, { method: "POST", body: fd });
                      await reload();
                      setMsg({ tone: "success", text: "New version uploaded. Previous versions are kept on record." });
                    } catch (x) {
                      setMsg({ tone: "danger", text: errorMessage(x) });
                    }
                  }} />
                </details>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
