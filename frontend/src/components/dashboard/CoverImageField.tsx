"use client";

import { useRef, useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { useI18n } from "@/i18n/client";
import { api, errorMessage } from "@/lib/api";
import type { OwnerInvestigation } from "@/lib/types";

const ACCEPT = "image/jpeg,image/png,image/webp";
const MAX_BYTES = 8 * 1024 * 1024;

/**
 * Header image for a report. Uploaded straight away (separately from the text autosave);
 * the server strips metadata such as GPS location, and the image goes live on publish.
 */
export function CoverImageField({ investigation, disabled, onChange }: { investigation: OwnerInvestigation; disabled?: boolean; onChange: (inv: OwnerInvestigation) => void }) {
  const { t } = useI18n();
  const input = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const url = investigation.cover_url ?? null;

  const upload = async (file: File | undefined) => {
    if (!file) return;
    setError(null);
    if (!ACCEPT.split(",").includes(file.type)) return setError(t.editor.cover.typeError);
    if (file.size > MAX_BYTES) return setError(t.editor.cover.sizeError);
    setBusy(true);
    try {
      const fd = new FormData();
      fd.append("cover", file);
      onChange((await api<{ data: OwnerInvestigation }>(`/me/investigations/${investigation.id}/cover`, { method: "POST", body: fd })).data);
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setBusy(false);
      if (input.current) input.current.value = "";
    }
  };

  const remove = async () => {
    if (!window.confirm(t.editor.cover.removeConfirm)) return;
    setBusy(true);
    setError(null);
    try {
      onChange((await api<{ data: OwnerInvestigation }>(`/me/investigations/${investigation.id}/cover`, { method: "DELETE" })).data);
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-2">
      <span className="text-sm font-medium">{t.editor.cover.label}</span>
      <input ref={input} type="file" accept={ACCEPT} className="sr-only" tabIndex={-1} aria-hidden onChange={(e) => void upload(e.target.files?.[0])} />
      {url ? (
        <div className="relative overflow-hidden rounded-lg border border-line bg-accent-soft">
          {/* eslint-disable-next-line @next/next/no-img-element -- owner-only rendition served by our API */}
          <img src={url} alt={t.editor.cover.alt} className="aspect-[21/9] max-h-72 w-full object-cover" />
          {!disabled && (
            <div className="absolute bottom-2 right-2 flex gap-2">
              <Button size="sm" variant="secondary" disabled={busy} onClick={() => input.current?.click()}>{busy ? t.editor.cover.uploading : t.editor.cover.change}</Button>
              <Button size="sm" variant="secondary" disabled={busy} onClick={() => void remove()}>{t.editor.cover.remove}</Button>
            </div>
          )}
        </div>
      ) : (
        <button type="button" disabled={disabled || busy} onClick={() => input.current?.click()}
          onDragOver={(e) => e.preventDefault()} onDrop={(e) => { e.preventDefault(); if (!disabled) void upload(e.dataTransfer.files?.[0]); }}
          className="flex aspect-[21/9] max-h-56 w-full flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-line bg-surface text-muted hover:border-accent hover:text-accent disabled:opacity-60">
          <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
            <rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="9" cy="10" r="2" /><path d="m21 16-5-5-9 9" />
          </svg>
          <span className="font-medium">{busy ? t.editor.cover.uploading : t.editor.cover.add}</span>
          <span className="text-xs">{t.editor.cover.hint}</span>
        </button>
      )}
      {error && <Alert tone="danger" live>{error}</Alert>}
    </div>
  );
}
