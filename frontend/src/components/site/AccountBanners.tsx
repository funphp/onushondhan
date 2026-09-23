"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { api, errorMessage } from "@/lib/api";

/** Nudges for unverified email and agreements that need (re-)acceptance. */
export function AccountBanners() {
  const { user } = useAuth();
  const pathname = usePathname();
  const [sent, setSent] = useState<string | null>(null);
  if (!user) return null;

  return (
    <>
      {user.pending_agreements.length > 0 && pathname !== "/agreements" && (
        <div className="border-b border-warning/30 bg-warning-soft px-4 py-2 text-center text-sm" role="status">
          Updated platform agreements need your acceptance before you can post or publish.{" "}
          <Link href="/agreements" className="font-semibold underline">Review and accept</Link>
        </div>
      )}
      {!user.email_verified && pathname !== "/verify-email" && (
        <div className="border-b border-line bg-accent-soft px-4 py-2 text-center text-sm" role="status">
          Please verify your email address ({user.email}) to comment, support and publish.{" "}
          <button
            type="button"
            className="font-semibold underline"
            onClick={async () => {
              try {
                await api("/auth/email/verification-notification", { method: "POST" });
                setSent("Verification email sent.");
              } catch (e) {
                setSent(errorMessage(e));
              }
            }}
          >
            Resend link
          </button>
          {sent && <span className="ml-2">{sent}</span>}
        </div>
      )}
    </>
  );
}
