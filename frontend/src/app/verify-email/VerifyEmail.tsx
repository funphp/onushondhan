"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { useI18n } from "@/i18n/client";
import { api, errorMessage } from "@/lib/api";

export function VerifyEmail({ params, justSent }: { params: { id: string; hash: string; expires: string; signature: string }; justSent: boolean }) {
  const { user, refresh } = useAuth();
  const { t } = useI18n();
  const [state, setState] = useState<"idle" | "verifying" | "done" | "error">(params.signature ? "verifying" : "idle");
  const [message, setMessage] = useState<string | null>(null);
  const started = useRef(false);

  useEffect(() => {
    if (!params.signature || started.current) return;
    started.current = true;
    api("/auth/verify-email", { method: "POST", body: { ...params, expires: Number(params.expires) } })
      .then(async () => {
        setState("done");
        await refresh();
      })
      .catch((e) => {
        setState("error");
        setMessage(errorMessage(e));
      });
  }, [params, refresh]);

  if (state === "verifying") return <p role="status">{t.auth.verifying}</p>;
  if (state === "done") return <Alert tone="success" title={t.auth.verified} live>{t.auth.verifiedBody} <Link href="/" className="underline">{t.auth.continue}</Link></Alert>;

  return (
    <div className="space-y-4">
      {state === "error" && <Alert tone="danger">{message}</Alert>}
      {justSent && <Alert live>{t.auth.linkSent(user?.email ?? t.auth.yourEmail)}</Alert>}
      {user && !user.email_verified && (
        <Button variant="secondary" onClick={async () => {
          try {
            await api("/auth/email/verification-notification", { method: "POST" });
            setMessage(t.auth.newLinkSent);
          } catch (e) {
            setMessage(errorMessage(e));
          }
        }}>{t.auth.sendNewLink}</Button>
      )}
      {message && state !== "error" && <p role="status" className="text-sm">{message}</p>}
      {user?.email_verified && <Alert tone="success">{t.auth.alreadyVerified}</Alert>}
      {!user && <p className="text-sm"><Link href="/login?next=/verify-email" className="underline">{t.auth.signIn}</Link> {t.auth.signInForLink}</p>}
    </div>
  );
}
