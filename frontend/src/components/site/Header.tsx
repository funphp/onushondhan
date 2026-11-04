import Link from "next/link";
import { getI18n } from "@/i18n/server";
import { AccountNav } from "./AccountNav";
import { LanguageSwitcher } from "./LanguageSwitcher";
import { LogoMark } from "./LogoMark";

/** Black masthead with the red ".com" — the site's signature. Same in light and dark mode. */
export async function Header() {
  const { t } = await getI18n();
  return (
    <header className="masthead border-b-4 border-accent bg-masthead text-masthead-ink">
      <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 lg:gap-x-8">
        <Link href="/" className="flex items-center gap-2.5" aria-label={t.brand.homeLabel}>
          <LogoMark size={32} className="shrink-0 text-masthead-ink" />
          <span className="font-display text-[1.6rem] font-bold leading-none tracking-tight" lang="en">
            onusondhan<span className="text-[#ff3b4e]">.com</span>
          </span>
        </Link>
        <nav aria-label={t.nav.main} className="order-3 -mx-4 flex w-[calc(100%+2rem)] gap-5 overflow-x-auto whitespace-nowrap border-t border-white/10 px-4 pt-2 text-sm font-medium sm:order-none sm:mx-0 sm:w-auto sm:border-0 sm:p-0">
          <Link href="/search" className="text-masthead-muted hover:text-masthead-ink">{t.nav.discover}</Link>
          <Link href="/investigators" className="text-masthead-muted hover:text-masthead-ink">{t.nav.investigators}</Link>
          <Link href="/leaderboards" className="text-masthead-muted hover:text-masthead-ink">{t.nav.community}</Link>
          <Link href="/pages/how-it-works" className="text-masthead-muted hover:text-masthead-ink">{t.nav.howItWorks}</Link>
        </nav>
        <form action="/search" role="search" className="ml-auto hidden md:block">
          <label htmlFor="site-search" className="sr-only">{t.nav.searchLabel}</label>
          <input id="site-search" name="q" type="search" placeholder={t.nav.searchPlaceholder}
            className="w-44 border border-white/15 bg-white/5 px-3 py-1.5 text-sm text-masthead-ink placeholder:text-masthead-muted focus:border-white/40 lg:w-52" />
        </form>
        <div className="ml-auto flex items-center gap-2 md:ml-0">
          <LanguageSwitcher onDark />
          <AccountNav />
        </div>
      </div>
    </header>
  );
}
