"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { ReactNode } from "react";
import { RequireAuth } from "@/components/auth/RequireAuth";
import { useAuth } from "@/components/auth/AuthProvider";
import { cn } from "@/components/ui/cn";
import { useI18n } from "@/i18n/client";

const NAV = [
  ["/dashboard", "overview"],
  ["/dashboard/investigations", "investigations"],
  ["/dashboard/tips", "tipInbox"],
  ["/dashboard/audience", "audience"],
  ["/dashboard/profile", "profile"],
] as const;

export function DashboardShell({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const { user } = useAuth();
  const { t } = useI18n();
  return (
    <RequireAuth investigator>
      {pathname === "/dashboard/onboarding" ? children : (
        <div className="grid gap-8 md:grid-cols-[220px_minmax(0,1fr)]">
          <nav aria-label={t.dashboard.nav} className="flex gap-1 overflow-x-auto md:flex-col">
            <p className="mb-2 hidden px-3 text-xs text-muted md:block">{t.dashboard.publishingAs} <strong className="text-ink">{user?.investigator?.public_name}</strong></p>
            {NAV.map(([href, label]) => {
              const active = href === "/dashboard" ? pathname === href : pathname.startsWith(href);
              return <Link key={href} href={href} aria-current={active ? "page" : undefined} className={cn("whitespace-nowrap rounded px-3 py-2 text-sm", active ? "bg-accent-soft font-semibold text-accent" : "hover:bg-accent-soft")}>{t.dashboard[label]}</Link>;
            })}
          </nav>
          <div className="min-w-0">{children}</div>
        </div>
      )}
    </RequireAuth>
  );
}
