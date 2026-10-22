import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ResultsList } from "@/components/investigation/ResultsList";
import { getI18n } from "@/i18n/server";
import { pickFilters, searchInvestigations } from "@/lib/search";
import { serverGetOrNull } from "@/lib/server-api";

type Loc = { countries: { country: string; investigations_count: number }[]; districts: { country: string; district: string; investigations_count: number }[] };

export async function generateMetadata(props: PageProps<"/location/[country]">): Promise<Metadata> {
  const { country } = await props.params;
  const { t, f } = await getI18n();
  return { title: t.search.investigationsIn(f.country(country)), alternates: { canonical: `/location/${country.toUpperCase()}` } };
}

export default async function LocationPage(props: PageProps<"/location/[country]">) {
  const { country: raw } = await props.params;
  const { t, f } = await getI18n();
  if (!/^[A-Za-z]{2}$/.test(raw)) notFound();
  const country = raw.toUpperCase();
  const sp = pickFilters(await props.searchParams);
  const filters = { ...sp, country };
  const [result, locations] = await Promise.all([searchInvestigations(filters), serverGetOrNull<{ data: Loc }>("/locations", { revalidate: 300 })]);
  const districts = locations?.data.districts.filter((d) => d.country === country) ?? [];

  return (
    <div className="space-y-6">
      <nav aria-label={t.common.breadcrumb} className="text-sm text-muted"><Link href="/">{t.common.home}</Link> / <Link href="/search">{t.nav.discover}</Link> / <span aria-current="page">{f.country(country)}</span></nav>
      <h1 className="font-serif text-3xl font-bold">{f.country(country)}{sp.location ? ` — ${sp.location}` : ""}</h1>
      {districts.length > 0 && (
        <ul className="flex flex-wrap gap-2" aria-label={t.search.districts}>
          {districts.map((d) => (
            <li key={d.district}>
              <Link href={`/location/${country}?location=${encodeURIComponent(d.district)}`} aria-current={sp.location === d.district ? "page" : undefined} className="rounded-full border border-line px-3 py-1 text-sm hover:bg-accent-soft">
                {d.district} ({f.number(d.investigations_count)})
              </Link>
            </li>
          ))}
        </ul>
      )}
      <ResultsList result={result} base={`/location/${country}`} filters={{ ...filters, country: undefined }} />
    </div>
  );
}
