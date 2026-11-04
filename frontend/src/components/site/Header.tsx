import Link from "next/link";
import { getI18n } from "@/i18n/server";
import { AccountNav } from "./AccountNav";
import { LanguageSwitcher } from "./LanguageSwitcher";
import { LogoMark } from "./LogoMark";

export async function Header() {
  const { t } = await getI18n();
  return (
    <header className="border-b border-line bg-surface">
      <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3 lg:gap-x-6">
        <Link href="/" className="flex items-center gap-2" aria-label={t.brand.homeLabel}>
          <LogoMark size={34} className="shrink-0 text-accent" />
          <span className="font-serif text-2xl font-bold tracking-tight" lang="en">{t.brand.name}</span>
        </Link>
        <nav aria-label={t.nav.main} className="order-3 flex w-full gap-4 overflow-x-auto whitespace-nowrap text-sm sm:order-none sm:w-auto">
          <Link href="/search" className="hover:text-accent">{t.nav.discover}</Link>
          <Link href="/investigators" className="hover:text-accent">{t.nav.investigators}</Link>
          <Link href="/leaderboards" className="hover:text-accent">{t.nav.community}</Link>
          <Link href="/pages/how-it-works" className="hover:text-accent">{t.nav.howItWorks}</Link>
        </nav>
        <form action="/search" role="search" className="ml-auto hidden md:block">
          <label htmlFor="site-search" className="sr-only">{t.nav.searchLabel}</label>
          <input id="site-search" name="q" type="search" placeholder={t.nav.searchPlaceholder} className="w-44 rounded-md lg:w-52 border border-line bg-paper px-3 py-1.5 text-sm" />
        </form>
        <div className="ml-auto flex items-center gap-2 md:ml-0">
          <LanguageSwitcher />
          <AccountNav />
        </div>
      </div>
    </header>
  );
}
