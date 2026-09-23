"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Checkbox, TextInput } from "@/components/ui/Field";
import { api, ApiError, ensureCsrf, errorMessage } from "@/lib/api";
import type { Me } from "@/lib/types";

export function RegisterForm() {
  const { setUser } = useAuth();
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
        const res = await api<{ data: Me }>("/auth/register", { method: "POST", body: { ...f, username: f.username || undefined, accept_agreements: accept } });
        await ensureCsrf(true);
        setUser(res.data);
        router.push("/verify-email?sent=1");
      } catch (err) {
        setError(err instanceof ApiError ? err : errorMessage(err));
      } finally {
        setBusy(false);
      }
    }}>
      <TextInput label="Full name" autoComplete="name" value={f.name} onChange={set("name")} required maxLength={120} error={fe("name")}
        hint="Kept private. It is never shown publicly." />
      <TextInput label="Public username (optional)" autoComplete="username" value={f.username} onChange={set("username")} minLength={3} maxLength={40} pattern="[A-Za-z0-9_\-]+" error={fe("username")}
        hint="Shown next to your comments. Letters, numbers, - and _ only. Leave empty to get a random one." />
      <TextInput label="Email" type="email" autoComplete="email" value={f.email} onChange={set("email")} required error={fe("email")} />
      <TextInput label="Password" type="password" autoComplete="new-password" value={f.password} onChange={set("password")} required minLength={10} error={fe("password")}
        hint="At least 10 characters with upper- and lower-case letters and a number." />
      <TextInput label="Confirm password" type="password" autoComplete="new-password" value={f.password_confirmation} onChange={set("password_confirmation")} required />
      <Checkbox checked={accept} onChange={(e) => setAccept(e.target.checked)} required error={fe("accept_agreements")} label={
        <>I agree to the <Link href="/pages/terms-of-use" target="_blank" className="underline">Terms of Use</Link>, <Link href="/pages/privacy-policy" target="_blank" className="underline">Privacy Policy</Link> and <Link href="/pages/content-policy" target="_blank" className="underline">Content Policy</Link>.</>
      } />
      {error && <Alert tone="danger">{typeof error === "string" ? error : error.message}</Alert>}
      <Button type="submit" loading={busy} className="w-full">Create account</Button>
      <p className="text-sm">Already have an account? <Link href="/login" className="text-accent underline">Sign in</Link></p>
    </form>
  );
}
