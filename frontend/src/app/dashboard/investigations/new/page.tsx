"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { TextInput } from "@/components/ui/Field";
import { api, errorMessage } from "@/lib/api";
import type { OwnerInvestigation } from "@/lib/types";

export default function NewInvestigationPage() {
  const router = useRouter();
  const [title, setTitle] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  return (
    <div className="mx-auto max-w-xl space-y-6">
      <h1 className="font-serif text-3xl font-bold">Start a new investigation</h1>
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
        <TextInput label="Working title" value={title} onChange={(e) => setTitle(e.target.value)} required maxLength={250} hint="You can change it any time before publishing." />
        {error && <Alert tone="danger">{error}</Alert>}
        <Button type="submit" loading={busy}>Create draft</Button>
      </form>
    </div>
  );
}
