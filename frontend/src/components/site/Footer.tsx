import Link from "next/link";
import { getI18n } from "@/i18n/server";

export async function Footer() {
  const { t, f } = await getI18n();
  return (
    <footer className="mt-16 border-t border-line bg-surface">
      <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 text-sm sm:grid-cols-3">
        <div>
          <p className="font-serif text-lg font-bold">{t.brand.name}</p>
          <p className="mt-2 text-muted">{t.brand.disclaimer}</p>
        </div>
        <nav aria-label={t.footer.platform}>
          <p className="mb-2 font-semibold">{t.footer.platform}</p>
          <ul className="space-y-1">
            <li><Link href="/pages/about">{t.footer.about}</Link></li>
            <li><Link href="/pages/how-it-works">{t.footer.howItWorks}</Link></li>
            <li><Link href="/pages/faq">{t.footer.faq}</Link></li>
            <li><Link href="/pages/contact">{t.footer.contact}</Link></li>
            <li><Link href="/tips/access">{t.footer.tipReply}</Link></li>
          </ul>
        </nav>
        <nav aria-label={t.footer.policies}>
          <p className="mb-2 font-semibold">{t.footer.policies}</p>
          <ul className="space-y-1">
            <li><Link href="/pages/terms-of-use">{t.footer.terms}</Link></li>
            <li><Link href="/pages/privacy-policy">{t.footer.privacy}</Link></li>
            <li><Link href="/pages/content-policy">{t.footer.contentPolicy}</Link></li>
            <li><Link href="/pages/investigator-responsibility">{t.footer.responsibility}</Link></li>
          </ul>
        </nav>
      </div>
      <p className="border-t border-line py-4 text-center text-xs text-muted">{t.footer.copyright(f.digits(new Date().getUTCFullYear()))}</p>
    </footer>
  );
}
