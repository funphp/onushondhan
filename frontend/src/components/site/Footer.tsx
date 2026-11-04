import Link from "next/link";
import { getI18n } from "@/i18n/server";
import { LogoMark } from "./LogoMark";

export async function Footer() {
  const { t, f } = await getI18n();
  return (
    <footer className="masthead mt-20 border-t-4 border-accent bg-masthead text-masthead-ink">
      <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 text-sm sm:grid-cols-3">
        <div>
          <p className="flex items-center gap-2 font-display text-xl font-bold" lang="en"><LogoMark size={26} /><span>onusondhan<span className="text-[#ff3b4e]">.com</span></span></p>
          <p className="mt-3 text-masthead-muted">{t.brand.disclaimer}</p>
        </div>
        <nav aria-label={t.footer.platform}>
          <p className="file-stamp mb-3 text-masthead-muted">{t.footer.platform}</p>
          <ul className="space-y-1.5 [&_a:hover]:text-white [&_a:hover]:underline">
            <li><Link href="/pages/about">{t.footer.about}</Link></li>
            <li><Link href="/pages/how-it-works">{t.footer.howItWorks}</Link></li>
            <li><Link href="/pages/faq">{t.footer.faq}</Link></li>
            <li><Link href="/pages/contact">{t.footer.contact}</Link></li>
            <li><Link href="/tips/access">{t.footer.tipReply}</Link></li>
          </ul>
        </nav>
        <nav aria-label={t.footer.policies}>
          <p className="file-stamp mb-3 text-masthead-muted">{t.footer.policies}</p>
          <ul className="space-y-1.5 [&_a:hover]:text-white [&_a:hover]:underline">
            <li><Link href="/pages/terms-of-use">{t.footer.terms}</Link></li>
            <li><Link href="/pages/privacy-policy">{t.footer.privacy}</Link></li>
            <li><Link href="/pages/content-policy">{t.footer.contentPolicy}</Link></li>
            <li><Link href="/pages/investigator-responsibility">{t.footer.responsibility}</Link></li>
          </ul>
        </nav>
      </div>
      <p className="border-t border-white/10 py-4 text-center text-xs text-masthead-muted">{t.footer.copyright(f.digits(new Date().getUTCFullYear()))}</p>
    </footer>
  );
}
