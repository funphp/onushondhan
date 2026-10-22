"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/Button";
import { Card, Spinner } from "@/components/ui/Card";
import { api, errorMessage } from "@/lib/api";
import { labelFor } from "@/i18n";
import { useI18n } from "@/i18n/client";

type Version = { version: number; title: string; change_reason: string | null; created_at: string };
type Diff = { from: number; to: number; fields: Record<string, { changed: boolean; lines?: { op: string; text: string }[] }> };

export default function VersionsPage() {
  const { t, f } = useI18n();
  const { id } = useParams<{ id: string }>();
  const [versions, setVersions] = useState<Version[] | null>(null);
  const [from, setFrom] = useState(0);
  const [to, setTo] = useState(0);
  const [diff, setDiff] = useState<Diff | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api<{ data: Version[] }>(`/me/investigations/${id}/versions`).then((r) => {
      setVersions(r.data);
      if (r.data.length >= 2) { setTo(r.data[0].version); setFrom(r.data[1].version); }
    }).catch((e) => setError(errorMessage(e)));
  }, [id]);

  return (
    <div className="space-y-5">
      <Link href={`/dashboard/investigations/${id}`} className="text-sm underline">← {t.editor.backToEditor}</Link>
      <h1 className="font-serif text-3xl font-bold">{t.editor.publishedVersions}</h1>
      {error && <p role="alert" className="text-danger">{error}</p>}
      {!versions ? <Spinner /> : (
        <>
          <ol className="space-y-2">
            {versions.map((v) => <li key={v.version} className="rounded-md border border-line bg-surface p-3 text-sm"><strong>{t.investigation.versionN(f.digits(v.version))}</strong> · {f.dateTime(v.created_at)} · {v.title}{v.change_reason && <span className="text-muted"> — {v.change_reason}</span>}</li>)}
          </ol>
          {versions.length >= 2 && (
            <Card className="space-y-4">
              <h2 className="text-lg font-semibold">{t.editor.compare}</h2>
              <div className="flex flex-wrap items-end gap-3 text-sm">
                <label>{t.editor.from} <select value={from} onChange={(e) => setFrom(Number(e.target.value))} className="rounded border border-line bg-surface px-2 py-1">{versions.map((v) => <option key={v.version} value={v.version}>{f.digits(v.version)}</option>)}</select></label>
                <label>{t.editor.to} <select value={to} onChange={(e) => setTo(Number(e.target.value))} className="rounded border border-line bg-surface px-2 py-1">{versions.map((v) => <option key={v.version} value={v.version}>{f.digits(v.version)}</option>)}</select></label>
                <Button size="sm" onClick={async () => { try { setDiff((await api<{ data: Diff }>(`/me/investigations/${id}/diff?from=${from}&to=${to}`)).data); } catch (e) { setError(errorMessage(e)); } }}>{t.editor.compareButton}</Button>
              </div>
              {diff && Object.entries(diff.fields).map(([field, d]) => (
                <section key={field}>
                  <h3 className="font-medium">{labelFor(t.editor.diffFields, field)} {d.changed ? <span className="text-warning">{t.editor.changed}</span> : <span className="text-muted">{t.editor.unchanged}</span>}</h3>
                  {d.changed && d.lines && (
                    <pre className="mt-1 max-h-96 overflow-auto rounded border border-line p-2 text-xs">
                      {d.lines.map((l, i) => <div key={i} className={l.op === "add" ? "bg-success-soft" : l.op === "remove" ? "bg-danger-soft line-through" : ""}>{l.op === "add" ? "+ " : l.op === "remove" ? "- " : "  "}{l.text}</div>)}
                    </pre>
                  )}
                </section>
              ))}
            </Card>
          )}
        </>
      )}
    </div>
  );
}
