"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Checkbox, TextInput } from "@/components/ui/Field";
import { api, ApiError, ensureCsrf, errorMessage } from "@/lib/api";
import { safeNext } from "@/lib/nav";
import { useI18n } from "@/i18n/client";
import type { Me } from "@/lib/types";

export function LoginForm({ next }: { next?: string }) {
  const { setUser } = useAuth();
  const { t } = useI18n();
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  return (
    <form className="space-y-4" onSubmit={async (e) => {
      e.preventDefault();
      setBusy(true);
      setError(null);
      try {
        await ensureCsrf(true);
        const res = await api<{ data: Me }>("/auth/login", { method: "POST", body: { email, password, remember } });
        // Session id changed on login: refresh the CSRF token for subsequent writes.
        await ensureCsrf(true);
        setUser(res.data);
        router.push(res.data.pending_agreements.length ? "/agreements" : safeNext(next));
        router.refresh();
      } catch (err) {
        setError(err instanceof ApiError && err.status === 429 ? t.auth.tooMany : errorMessage(err));
      } finally {
        setBusy(false);
      }
    }}>
      <TextInput label={t.auth.email} type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
      <TextInput label={t.auth.password} type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
      <Checkbox label={t.auth.remember} checked={remember} onChange={(e) => setRemember(e.target.checked)} />
      {error && <Alert tone="danger">{error}</Alert>}
      <Button type="submit" loading={busy} className="w-full">{t.auth.signIn}</Button>
      <p className="flex justify-between text-sm">
        <Link href="/forgot-password" className="text-accent underline">{t.auth.forgot}</Link>
        <Link href="/register" className="text-accent underline">{t.auth.create}</Link>
      </p>
    </form>
  );
}
