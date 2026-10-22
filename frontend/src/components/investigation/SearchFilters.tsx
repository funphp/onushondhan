import { getI18n } from "@/i18n/server";
import type { Category } from "@/lib/types";

export const SORT_KEYS = ["latest", "trending", "most_read", "most_shared", "most_discussed", "relevance", "oldest"] as const;

/** Plain GET form: filters work without JavaScript and produce shareable URLs. */
export async function SearchFilters({ params, categories, action = "/search" }: { params: Record<string, string | undefined>; categories: Category[]; action?: string }) {
  const { t } = await getI18n();
  const control = "w-full rounded-md border border-line bg-surface px-3 py-2 text-sm";
  return (
    <form action={action} method="get" role="search" className="grid gap-3 rounded-lg border border-line bg-surface p-4 sm:grid-cols-2 lg:grid-cols-6">
      <div className="sm:col-span-2">
        <label htmlFor="f-q" className="mb-1 block text-xs font-medium">{t.search.keywords}</label>
        <input id="f-q" name="q" type="search" defaultValue={params.q} className={control} placeholder={t.search.keywordsPlaceholder} />
      </div>
      <div>
        <label htmlFor="f-cat" className="mb-1 block text-xs font-medium">{t.search.category}</label>
        <select id="f-cat" name="category" defaultValue={params.category ?? ""} className={control}>
          <option value="">{t.search.allCategories}</option>
          {categories.map((c) => (
            <optgroup key={c.slug} label={c.name}>
              <option value={c.slug}>{t.search.allIn(c.name)}</option>
              {c.children?.map((s) => <option key={s.slug} value={s.slug}>{s.name}</option>)}
            </optgroup>
          ))}
        </select>
      </div>
      <div>
        <label htmlFor="f-loc" className="mb-1 block text-xs font-medium">{t.search.location}</label>
        <input id="f-loc" name="location" defaultValue={params.location} className={control} placeholder={t.search.locationPlaceholder} />
      </div>
      <div>
        <label htmlFor="f-from" className="mb-1 block text-xs font-medium">{t.search.from}</label>
        <input id="f-from" name="from" type="date" defaultValue={params.from} className={control} />
      </div>
      <div>
        <label htmlFor="f-to" className="mb-1 block text-xs font-medium">{t.search.to}</label>
        <input id="f-to" name="to" type="date" defaultValue={params.to} className={control} />
      </div>
      <div>
        <label htmlFor="f-sort" className="mb-1 block text-xs font-medium">{t.search.sortBy}</label>
        <select id="f-sort" name="sort" defaultValue={params.sort ?? ""} className={control}>
          <option value="">{t.search.default}</option>
          {SORT_KEYS.map((s) => <option key={s} value={s}>{t.sort[s]}</option>)}
        </select>
      </div>
      {params.tag && <input type="hidden" name="tag" value={params.tag} />}
      {params.country && <input type="hidden" name="country" value={params.country} />}
      <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
        <button type="submit" className="h-10 rounded-md bg-accent px-4 text-sm font-medium text-white dark:text-paper">{t.search.applyFilters}</button>
        <a href={action} className="h-10 rounded-md px-4 py-2 text-sm hover:bg-accent-soft">{t.common.reset}</a>
      </div>
    </form>
  );
}
