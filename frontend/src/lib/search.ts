import { serverGet } from "./server-api";
import type { Category, InvestigationCard, Paginated } from "./types";

export const FILTER_KEYS = ["q", "category", "tag", "country", "location", "from", "to", "sort", "page", "investigator"] as const;

export function pickFilters(sp: Record<string, string | string[] | undefined>): Record<string, string | undefined> {
  const out: Record<string, string | undefined> = {};
  for (const k of FILTER_KEYS) {
    const v = sp[k];
    out[k] = Array.isArray(v) ? v[0] : v;
  }
  return out;
}

export async function searchInvestigations(filters: Record<string, string | undefined>, perPage = 12) {
  // Validation errors (e.g. malformed dates) fall back to an empty result, not a crash.
  try {
    return await serverGet<Paginated<InvestigationCard>>("/investigations", { params: { ...filters, per_page: perPage }, revalidate: 30 });
  } catch {
    return { data: [], meta: { current_page: 1, last_page: 1, total: 0 } } as Paginated<InvestigationCard>;
  }
}

export async function getCategories(): Promise<Category[]> {
  try {
    return (await serverGet<{ data: Category[] }>("/categories", { revalidate: 300 })).data;
  } catch {
    return [];
  }
}

export function hrefWith(base: string, filters: Record<string, string | undefined>, overrides: Record<string, string | number | undefined>): string {
  const p = new URLSearchParams();
  for (const [k, v] of Object.entries({ ...filters, ...overrides })) {
    if (v !== undefined && v !== "" && v !== null) p.set(k, String(v));
  }
  const q = p.toString();
  return q ? `${base}?${q}` : base;
}
