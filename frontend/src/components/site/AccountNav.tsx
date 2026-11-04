"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { useAuth } from "@/components/auth/AuthProvider";
import { buttonClass } from "@/components/ui/Button";
import { useI18n } from "@/i18n/client";
import { ADMIN_URL } from "@/lib/site";

export function AccountNav() {
  const { user, loading, logout } = useAuth();
  const { t, f } = useI18n();
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
        <Link href="/login" className={buttonClass("ghost", "sm", "text-masthead-ink hover:bg-white/10")}>{t.nav.signIn}</Link>
        <Link href="/register" className={buttonClass("primary", "sm")}>{t.nav.join}</Link>
      </div>
    );
  }

  const unread = user.unread_notifications;
  const item = "block rounded px-3 py-2 text-sm hover:bg-accent-soft";
  return (
    <div className="relative" ref={ref}>
      <button type="button" aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen((o) => !o)} className={buttonClass("ghost", "sm", "border border-white/20 text-masthead-ink hover:bg-white/10")}>
        <span className="max-w-32 truncate">{user.username}</span>
        {unread > 0 && <span className="rounded-full bg-danger px-1.5 text-xs text-white" aria-label={t.nav.unread(f.digits(unread))}>{f.digits(unread)}</span>}
        <span aria-hidden>▾</span>
      </button>
      {open && (
        <div role="menu" className="absolute right-0 z-50 mt-2 w-60 rounded-md border border-line bg-surface p-1 text-ink shadow-lg" onClick={() => setOpen(false)}>
          <Link role="menuitem" className={item} href={user.investigator ? "/dashboard" : "/dashboard/onboarding"}>{user.investigator ? t.nav.dashboard : t.nav.becomeInvestigator}</Link>
          <Link role="menuitem" className={item} href="/account/notifications">{t.nav.notifications}{unread > 0 ? ` (${f.digits(unread)})` : ""}</Link>
          <Link role="menuitem" className={item} href="/account/bookmarks">{t.nav.bookmarks}</Link>
          <Link role="menuitem" className={item} href="/account/following">{t.nav.followingSupport}</Link>
          <Link role="menuitem" className={item} href="/account/tips">{t.nav.myTips}</Link>
          <Link role="menuitem" className={item} href="/account">{t.nav.settings}</Link>
          {user.is_staff && <a role="menuitem" className={item} href={ADMIN_URL}>{t.nav.admin}</a>}
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
            {t.nav.signOut}
          </button>
        </div>
      )}
    </div>
  );
}
