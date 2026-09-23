import Link from "next/link";
import { PLATFORM_DISCLAIMER } from "@/lib/site";

export function Footer() {
  return (
    <footer className="mt-16 border-t border-line bg-surface">
      <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 text-sm sm:grid-cols-3">
        <div>
          <p className="font-serif text-lg font-bold">Onushondhan</p>
          <p className="mt-2 text-muted">{PLATFORM_DISCLAIMER}</p>
        </div>
        <nav aria-label="Platform">
          <p className="mb-2 font-semibold">Platform</p>
          <ul className="space-y-1">
            <li><Link href="/pages/about">About</Link></li>
            <li><Link href="/pages/how-it-works">How it works</Link></li>
            <li><Link href="/pages/faq">FAQ</Link></li>
            <li><Link href="/pages/contact">Contact</Link></li>
            <li><Link href="/tips/access">Check a tip reply</Link></li>
          </ul>
        </nav>
        <nav aria-label="Policies">
          <p className="mb-2 font-semibold">Policies</p>
          <ul className="space-y-1">
            <li><Link href="/pages/terms-of-use">Terms of Use</Link></li>
            <li><Link href="/pages/privacy-policy">Privacy Policy</Link></li>
            <li><Link href="/pages/content-policy">Content Policy</Link></li>
            <li><Link href="/pages/investigator-responsibility">Investigator Responsibility Agreement</Link></li>
          </ul>
        </nav>
      </div>
      <p className="border-t border-line py-4 text-center text-xs text-muted">© {new Date().getUTCFullYear()} Onushondhan. Reports are the responsibility of their investigators.</p>
    </footer>
  );
}
