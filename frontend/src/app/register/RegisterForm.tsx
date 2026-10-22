"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Checkbox, TextInput } from "@/components/ui/Field";
import { api, ApiError, ensureCsrf, errorMessage } from "@/lib/api";
import { useI18n } from "@/i18n/client";
import type { Me } from "@/lib/types";

export function RegisterForm() {
  const { setUser } = useAuth();
  const { t, locale } = useI18n();
  const router = useRouter();
  const [f, setF] = useState({ name: "", username: "", email: "", password: "", password_confirmation: "" });
  const [accept, setAccept] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | string | null>(null);
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement>) => setF({ ...f, [k]: e.target.value });
  const fe = (k: string) => (error instanceof ApiError ? error.fieldError(k) : undefined);

  return (
    <form className="space-y-4" onSubmit={async (e) => {
      e.preventDefault();
      setBusy(true);
      setError(null);
      try {
        await ensureCsrf(true);
        const res = await api<{ data: Me }>("/auth/register", { method: "POST", body: { ...f, username: f.username || undefined, accept_agreements: accept, locale } });
        await ensureCsrf(true);
        setUser(res.data);
        router.push("/verify-email?sent=1");
      } catch (err) {
        setError(err instanceof ApiError ? err : errorMessage(err));
      } finally {
        setBusy(false);
      }
    }}>
      <TextInput label={t.auth.fullName} autoComplete="name" value={f.name} onChange={set("name")} required maxLength={120} error={fe("name")}
        hint={t.auth.fullNameHint} />
      <TextInput label={t.auth.username} autoComplete="username" value={f.username} onChange={set("username")} minLength={3} maxLength={40} pattern="[A-Za-z0-9_\-]+" error={fe("username")}
        hint={t.auth.usernameHint} />
      <TextInput label={t.auth.email} type="email" autoComplete="email" value={f.email} onChange={set("email")} required error={fe("email")} />
      <TextInput label={t.auth.password} type="password" autoComplete="new-password" value={f.password} onChange={set("password")} required minLength={10} error={fe("password")}
        hint={t.auth.passwordHint} />
      <TextInput label={t.auth.confirmPassword} type="password" autoComplete="new-password" value={f.password_confirmation} onChange={set("password_confirmation")} required />
      <Checkbox checked={accept} onChange={(e) => setAccept(e.target.checked)} required error={fe("accept_agreements")} label={
        <>{t.auth.agreePrefix} <Link href="/pages/terms-of-use" target="_blank" className="underline">{t.footer.terms}</Link>, <Link href="/pages/privacy-policy" target="_blank" className="underline">{t.footer.privacy}</Link> {t.auth.and} <Link href="/pages/content-policy" target="_blank" className="underline">{t.footer.contentPolicy}</Link>{t.auth.agreeSuffix}</>
      } />
      {error && <Alert tone="danger">{typeof error === "string" ? error : error.message}</Alert>}
      <Button type="submit" loading={busy} className="w-full">{t.auth.createButton}</Button>
      <p className="text-sm">{t.auth.haveAccount} <Link href="/login" className="text-accent underline">{t.auth.signIn}</Link></p>
    </form>
  );
}
