"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { buttonClass } from "@/components/ui/Button";
import { ADMIN_URL } from "@/lib/site";

export function AccountNav() {
  const { user, loading, logout } = useAuth();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const onDoc = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    const onEsc = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    document.addEventListener("click", onDoc);
    document.addEventListener("keydown", onEsc);
    return () => {
      document.removeEventListener("click", onDoc);
      document.removeEventListener("keydown", onEsc);
    };
  }, []);

  if (loading) return <span className="h-8 w-20" aria-hidden />;
  if (!user) {
    return (
      <div className="flex items-center gap-2">
        <Link href="/login" className={buttonClass("ghost", "sm")}>Sign in</Link>
        <Link href="/register" className={buttonClass("primary", "sm")}>Join</Link>
      </div>
    );
  }

  const item = "block rounded px-3 py-2 text-sm hover:bg-accent-soft";
  return (
    <div className="relative" ref={ref}>
      <button type="button" aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen((o) => !o)} className={buttonClass("secondary", "sm")}>
        <span className="max-w-32 truncate">{user.username}</span>
        {user.unread_notifications > 0 && (
          <span className="rounded-full bg-danger px-1.5 text-xs text-white" aria-label={`${user.unread_notifications} unread notifications`}>{user.unread_notifications}</span>
        )}
        <span aria-hidden>▾</span>
      </button>
      {open && (
        <div role="menu" className="absolute right-0 z-50 mt-2 w-56 rounded-md border border-line bg-surface p-1 shadow-lg" onClick={() => setOpen(false)}>
          <Link role="menuitem" className={item} href={user.investigator ? "/dashboard" : "/dashboard/onboarding"}>{user.investigator ? "Investigator dashboard" : "Become an investigator"}</Link>
          <Link role="menuitem" className={item} href="/account/notifications">Notifications{user.unread_notifications > 0 ? ` (${user.unread_notifications})` : ""}</Link>
          <Link role="menuitem" className={item} href="/account/bookmarks">Bookmarks</Link>
          <Link role="menuitem" className={item} href="/account/following">Following &amp; support</Link>
          <Link role="menuitem" className={item} href="/account/tips">My tips</Link>
          <Link role="menuitem" className={item} href="/account">Account settings</Link>
          {user.is_staff && <a role="menuitem" className={item} href={ADMIN_URL}>Admin panel</a>}
          <button
            role="menuitem"
            type="button"
            className={`${item} w-full text-left`}
            onClick={async () => {
              await logout();
              // Intentional full reload: discards every piece of client state after sign-out.
              // eslint-disable-next-line @next/next/no-location-assign-relative-destination
              window.location.assign("/");
            }}
          >
            Sign out
          </button>
        </div>
      )}
    </div>
  );
}
