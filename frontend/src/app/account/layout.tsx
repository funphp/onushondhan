import type { Metadata } from "next";
import Link from "next/link";
import { RequireAuth } from "@/components/auth/RequireAuth";
import { getI18n } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getI18n();
  return { title: t.meta.account, robots: { index: false } };
}

const NAV = [
  ["/account", "settings"],
  ["/account/notifications", "notifications"],
  ["/account/bookmarks", "bookmarks"],
  ["/account/following", "followingSupport"],
  ["/account/tips", "myTips"],
  ["/account/complaints", "myComplaints"],
] as const;

export default async function AccountLayout({ children }: LayoutProps<"/account">) {
  const { t } = await getI18n();
  return (
    <RequireAuth>
      <div className="grid gap-8 md:grid-cols-[200px_minmax(0,1fr)]">
        <nav aria-label={t.account.nav} className="flex gap-1 overflow-x-auto md:flex-col">
          {NAV.map(([href, label]) => <Link key={href} href={href} className="whitespace-nowrap rounded px-3 py-2 text-sm hover:bg-accent-soft">{t.account[label]}</Link>)}
        </nav>
        <div className="min-w-0">{children}</div>
      </div>
    </RequireAuth>
  );
}
