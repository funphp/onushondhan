import type { MetadataRoute } from "next";
import { serverGetOrNull } from "@/lib/server-api";
import { absoluteUrl } from "@/lib/site";

export const revalidate = 3600;

type Entry = { path: string; lastmod: string | null };

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const res = await serverGetOrNull<{ data: Record<"investigations" | "investigators" | "categories" | "pages", Entry[]> }>("/sitemap", { revalidate: 3600 });
  const toEntry = (e: Entry, priority: number): MetadataRoute.Sitemap[number] => ({ url: absoluteUrl(e.path), lastModified: e.lastmod ?? undefined, priority });
  return [
    { url: absoluteUrl("/"), changeFrequency: "hourly", priority: 1 },
    { url: absoluteUrl("/search"), priority: 0.6 },
    { url: absoluteUrl("/investigators"), priority: 0.6 },
    ...(res?.data.investigations ?? []).map((e) => toEntry(e, 0.9)),
    ...(res?.data.investigators ?? []).map((e) => toEntry(e, 0.7)),
    ...(res?.data.categories ?? []).map((e) => toEntry(e, 0.5)),
    ...(res?.data.pages ?? []).map((e) => toEntry(e, 0.3)),
  ];
}
