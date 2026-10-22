"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { TextInput } from "@/components/ui/Field";
import { useI18n } from "@/i18n/client";
import { api, errorMessage } from "@/lib/api";
import type { OwnerInvestigation } from "@/lib/types";

export default function NewInvestigationPage() {
  const { t } = useI18n();
  const router = useRouter();
  const [title, setTitle] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  return (
    <div className="mx-auto max-w-xl space-y-6">
      <h1 className="font-serif text-3xl font-bold">{t.myInvestigations.newTitle}</h1>
      <form className="space-y-4" onSubmit={async (e) => {
        e.preventDefault();
        setBusy(true);
        try {
          const r = await api<{ data: OwnerInvestigation }>("/me/investigations", { method: "POST", body: { title } });
          router.replace(`/dashboard/investigations/${r.data.id}`);
        } catch (err) {
          setError(errorMessage(err));
          setBusy(false);
        }
      }}>
        <TextInput label={t.myInvestigations.workingTitle} value={title} onChange={(e) => setTitle(e.target.value)} required maxLength={250} hint={t.myInvestigations.workingTitleHint} />
        {error && <Alert tone="danger">{error}</Alert>}
        <Button type="submit" loading={busy}>{t.myInvestigations.createDraft}</Button>
      </form>
    </div>
  );
}
