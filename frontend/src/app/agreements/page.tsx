"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { RequireAuth } from "@/components/auth/RequireAuth";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Field";
import { Markdown } from "@/components/ui/Markdown";
import { useI18n } from "@/i18n/client";
import { api, errorMessage } from "@/lib/api";
import type { Agreement, Me } from "@/lib/types";

function AgreementsInner() {
  const { user, setUser } = useAuth();
  const { t } = useI18n();
  const router = useRouter();
  const [docs, setDocs] = useState<Agreement[]>([]);
  const [checked, setChecked] = useState<Record<number, boolean>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const pending = user?.pending_agreements ?? [];

  useEffect(() => {
    Promise.all(pending.map((p) => api<{ data: Agreement }>(`/agreements/${p.agreement_type}`).then((r) => r.data))).then(setDocs).catch((e) => setError(errorMessage(e)));
    // eslint-disable-next-line react-hooks/exhaustive-deps -- load once per pending set
  }, [pending.map((p) => p.id).join(",")]);

  if (pending.length === 0) return <Alert tone="success">{t.agreements.allAccepted}</Alert>;

  return (
    <div className="space-y-6">
      <p>{t.agreements.intro}</p>
      {docs.map((d) => (
        <section key={d.id} className="space-y-3 rounded-lg border border-line bg-surface p-5">
          <h2 className="text-xl font-semibold">{d.title} <span className="text-sm font-normal text-muted">{t.agreements.version(d.version)}</span></h2>
          <div className="max-h-80 overflow-y-auto rounded border border-line p-4" tabIndex={0} aria-label={t.agreements.textLabel(d.title)}>
            <Markdown className="prose-report text-base">{d.content}</Markdown>
          </div>
          <Checkbox label={t.agreements.accept(d.title, d.version)} checked={!!checked[d.id]} onChange={(e) => setChecked({ ...checked, [d.id]: e.target.checked })} />
        </section>
      ))}
      {error && <Alert tone="danger">{error}</Alert>}
      <Button loading={busy} disabled={docs.length === 0 || docs.some((d) => !checked[d.id])} onClick={async () => {
        setBusy(true);
        try {
          const r = await api<{ data: Me }>("/agreements/accept", { method: "POST", body: { terms_version_ids: docs.map((d) => d.id) } });
          setUser(r.data);
          router.push("/");
        } catch (e) {
          setError(errorMessage(e));
        } finally {
          setBusy(false);
        }
      }}>{t.agreements.acceptContinue}</Button>
    </div>
  );
}

export default function AgreementsPage() {
  const { t } = useI18n();
  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <h1 className="font-serif text-3xl font-bold">{t.agreements.title}</h1>
      <RequireAuth><AgreementsInner /></RequireAuth>
    </div>
  );
}
