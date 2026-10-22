import Link from "next/link";
import { getI18n } from "@/i18n/server";

export default async function NotFound() {
  const { t } = await getI18n();
  return (
    <div className="mx-auto max-w-xl py-16 text-center">
      <h1 className="font-serif text-3xl font-bold">{t.errors.notFoundTitle}</h1>
      <p className="mt-3 text-muted">{t.errors.notFoundBody}</p>
      <p className="mt-6"><Link href="/" className="text-accent underline">{t.errors.goHome}</Link> {t.errors.or} <Link href="/search" className="text-accent underline">{t.errors.searchInvestigations}</Link></p>
    </div>
  );
}
