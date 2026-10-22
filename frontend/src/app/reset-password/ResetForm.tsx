"use client";

import Link from "next/link";
import { useState } from "react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { TextInput } from "@/components/ui/Field";
import { useI18n } from "@/i18n/client";
import { api, ApiError, errorMessage } from "@/lib/api";

export function ResetForm({ token, email: initialEmail }: { token: string; email: string }) {
  const { t } = useI18n();
  const [email, setEmail] = useState(initialEmail);
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | string | null>(null);
  const [done, setDone] = useState(false);

  if (!token) return <Alert tone="warning">{t.auth.resetIncomplete} <Link href="/forgot-password" className="underline">{t.auth.requestNew}</Link></Alert>;
  if (done) return <Alert tone="success" title={t.auth.passwordUpdated} live>{t.auth.passwordUpdatedBody} <Link href="/login" className="underline">{t.auth.signIn}</Link></Alert>;

  return (
    <form className="space-y-4" onSubmit={async (e) => {
      e.preventDefault();
      setBusy(true);
      setError(null);
      try {
        await api("/auth/reset-password", { method: "POST", body: { token, email, password, password_confirmation: confirm } });
        setDone(true);
      } catch (err) {
        setError(err instanceof ApiError ? err : errorMessage(err));
      } finally {
        setBusy(false);
      }
    }}>
      <TextInput label={t.auth.email} type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
      <TextInput label={t.auth.newPassword} type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={10}
        error={error instanceof ApiError ? error.fieldError("password") : undefined} hint={t.auth.passwordHint} />
      <TextInput label={t.auth.confirmNewPassword} type="password" autoComplete="new-password" value={confirm} onChange={(e) => setConfirm(e.target.value)} required />
      {error && <Alert tone="danger">{typeof error === "string" ? error : error.message}</Alert>}
      <Button type="submit" loading={busy}>{t.auth.updatePassword}</Button>
    </form>
  );
}
