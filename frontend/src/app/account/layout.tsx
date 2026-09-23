import type { Metadata } from "next";
import Link from "next/link";
import { RequireAuth } from "@/components/auth/RequireAuth";

export const metadata: Metadata = { title: "Your account", robots: { index: false } };

const NAV = [
  ["/account", "Settings"],
  ["/account/notifications", "Notifications"],
  ["/account/bookmarks", "Bookmarks"],
  ["/account/following", "Following & support"],
  ["/account/tips", "My tips"],
  ["/account/complaints", "My complaints"],
];

export default function AccountLayout({ children }: LayoutProps<"/account">) {
  return (
    <RequireAuth>
      <div className="grid gap-8 md:grid-cols-[200px_minmax(0,1fr)]">
        <nav aria-label="Account" className="flex gap-1 overflow-x-auto md:flex-col">
          {NAV.map(([href, label]) => <Link key={href} href={href} className="whitespace-nowrap rounded px-3 py-2 text-sm hover:bg-accent-soft">{label}</Link>)}
        </nav>
        <div className="min-w-0">{children}</div>
      </div>
    </RequireAuth>
  );
}
