"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { api, errorMessage } from "@/lib/api";

export function VerifyEmail({ params, justSent }: { params: { id: string; hash: string; expires: string; signature: string }; justSent: boolean }) {
  const { user, refresh } = useAuth();
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

  if (state === "verifying") return <p role="status">Verifying…</p>;
  if (state === "done") return <Alert tone="success" title="Email verified" live>Thank you. You can now comment, support investigators and publish. <Link href="/" className="underline">Continue</Link></Alert>;

  return (
    <div className="space-y-4">
      {state === "error" && <Alert tone="danger">{message}</Alert>}
      {justSent && <Alert live>We sent a verification link to {user?.email ?? "your email address"}. It expires in 60 minutes.</Alert>}
      {user && !user.email_verified && (
        <Button variant="secondary" onClick={async () => {
          try {
            await api("/auth/email/verification-notification", { method: "POST" });
            setMessage("A new verification link has been sent.");
          } catch (e) {
            setMessage(errorMessage(e));
          }
        }}>Send a new link</Button>
      )}
      {message && state !== "error" && <p role="status" className="text-sm">{message}</p>}
      {user?.email_verified && <Alert tone="success">Your email is already verified.</Alert>}
      {!user && <p className="text-sm"><Link href="/login?next=/verify-email" className="underline">Sign in</Link> to request a new link.</p>}
    </div>
  );
}
