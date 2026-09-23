"use client";

import { useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { TextInput } from "@/components/ui/Field";
import { api, errorMessage } from "@/lib/api";

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [msg, setMsg] = useState<{ tone: "success" | "danger"; text: string } | null>(null);
  const [busy, setBusy] = useState(false);
  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="font-serif text-3xl font-bold">Reset your password</h1>
      <form className="space-y-4" onSubmit={async (e) => {
        e.preventDefault();
        setBusy(true);
        try {
          const r = await api<{ message: string }>("/auth/forgot-password", { method: "POST", body: { email } });
          setMsg({ tone: "success", text: r.message });
        } catch (err) {
          setMsg({ tone: "danger", text: errorMessage(err) });
        } finally {
          setBusy(false);
        }
      }}>
        <TextInput label="Email" type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        {msg && <Alert tone={msg.tone} live>{msg.text}</Alert>}
        <Button type="submit" loading={busy}>Send reset link</Button>
      </form>
    </div>
  );
}
