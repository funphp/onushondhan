import Link from "next/link";
import { AccountNav } from "./AccountNav";

export function Header() {
  return (
    <header className="border-b border-line bg-surface">
      <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3">
        <Link href="/" className="flex items-baseline gap-2" aria-label="Onushondhan home">
          <span className="font-serif text-2xl font-bold tracking-tight">Onushondhan</span>
          <span className="hidden text-xs text-muted sm:inline">অনুসন্ধান</span>
        </Link>
        <nav aria-label="Main" className="order-3 flex w-full gap-4 overflow-x-auto text-sm sm:order-none sm:w-auto">
          <Link href="/search" className="hover:text-accent">Discover</Link>
          <Link href="/investigators" className="hover:text-accent">Investigators</Link>
          <Link href="/leaderboards" className="hover:text-accent">Community</Link>
          <Link href="/pages/how-it-works" className="hover:text-accent">How it works</Link>
        </nav>
        <form action="/search" role="search" className="ml-auto hidden md:block">
          <label htmlFor="site-search" className="sr-only">Search investigations</label>
          <input id="site-search" name="q" type="search" placeholder="Search investigations" className="w-56 rounded-md border border-line bg-paper px-3 py-1.5 text-sm" />
        </form>
        <AccountNav />
      </div>
    </header>
  );
}
