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
import type { Me } from "@/lib/types";

export function LoginForm({ next }: { next?: string }) {
  const { setUser } = useAuth();
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
        setError(err instanceof ApiError && err.status === 429 ? "Too many attempts. Please wait a minute and try again." : errorMessage(err));
      } finally {
        setBusy(false);
      }
    }}>
      <TextInput label="Email" type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
      <TextInput label="Password" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
      <Checkbox label="Keep me signed in on this device" checked={remember} onChange={(e) => setRemember(e.target.checked)} />
      {error && <Alert tone="danger">{error}</Alert>}
      <Button type="submit" loading={busy} className="w-full">Sign in</Button>
      <p className="flex justify-between text-sm">
        <Link href="/forgot-password" className="text-accent underline">Forgot password?</Link>
        <Link href="/register" className="text-accent underline">Create an account</Link>
      </p>
    </form>
  );
}
