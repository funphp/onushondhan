"use client";

import { useState } from "react";
import { TipThread } from "@/components/engagement/TipThread";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { TextInput } from "@/components/ui/Field";
import { api, errorMessage } from "@/lib/api";
import type { Tip } from "@/lib/types";

export function GuestTipThread() {
  const [code, setCode] = useState("");
  const [tip, setTip] = useState<Tip | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  if (tip) {
    return (
      <div className="space-y-4">
        <TipThread tip={tip} perspective="tipster" onReply={async (body) => {
          const res = await api<{ data: Tip }>("/tips/access/messages", { method: "POST", body: { access_code: code, body } });
          setTip(res.data);
        }} />
        <Button variant="ghost" onClick={() => { setTip(null); setCode(""); }}>Close conversation</Button>
      </div>
    );
  }

  return (
    <form className="space-y-4" onSubmit={async (e) => {
      e.preventDefault();
      setBusy(true);
      setError(null);
      try {
        setTip((await api<{ data: Tip }>("/tips/access", { method: "POST", body: { access_code: code } })).data);
      } catch (err) {
        setError(errorMessage(err));
      } finally {
        setBusy(false);
      }
    }}>
      <TextInput label="Access code" value={code} onChange={(e) => setCode(e.target.value)} placeholder="TIP-XXXXX-XXXXX-XXXXX-XXXXX" autoComplete="off" spellCheck={false} required maxLength={40} />
      {error && <Alert tone="danger">{error}</Alert>}
      <Button type="submit" loading={busy}>Open</Button>
    </form>
  );
}
