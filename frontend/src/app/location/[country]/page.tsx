import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ResultsList } from "@/components/investigation/ResultsList";
import { countryName } from "@/lib/format";
import { pickFilters, searchInvestigations } from "@/lib/search";
import { serverGetOrNull } from "@/lib/server-api";

type Loc = { countries: { country: string; investigations_count: number }[]; districts: { country: string; district: string; investigations_count: number }[] };

export async function generateMetadata(props: PageProps<"/location/[country]">): Promise<Metadata> {
  const { country } = await props.params;
  return { title: `Investigations in ${countryName(country)}`, alternates: { canonical: `/location/${country.toUpperCase()}` } };
}

export default async function LocationPage(props: PageProps<"/location/[country]">) {
  const { country: raw } = await props.params;
  if (!/^[A-Za-z]{2}$/.test(raw)) notFound();
  const country = raw.toUpperCase();
  const sp = pickFilters(await props.searchParams);
  const filters = { ...sp, country };
  const [result, locations] = await Promise.all([searchInvestigations(filters), serverGetOrNull<{ data: Loc }>("/locations", { revalidate: 300 })]);
  const districts = locations?.data.districts.filter((d) => d.country === country) ?? [];

  return (
    <div className="space-y-6">
      <nav aria-label="Breadcrumb" className="text-sm text-muted"><Link href="/">Home</Link> / <Link href="/search">Discover</Link> / <span aria-current="page">{countryName(country)}</span></nav>
      <h1 className="font-serif text-3xl font-bold">{countryName(country)}{sp.location ? ` — ${sp.location}` : ""}</h1>
      {districts.length > 0 && (
        <ul className="flex flex-wrap gap-2" aria-label="Districts">
          {districts.map((d) => (
            <li key={d.district}>
              <Link href={`/location/${country}?location=${encodeURIComponent(d.district)}`} aria-current={sp.location === d.district ? "page" : undefined} className="rounded-full border border-line px-3 py-1 text-sm hover:bg-accent-soft">
                {d.district} ({d.investigations_count})
              </Link>
            </li>
          ))}
        </ul>
      )}
      <ResultsList result={result} base={`/location/${country}`} filters={{ ...filters, country: undefined }} />
    </div>
  );
}
