import type { Category } from "@/lib/types";

export const SORTS = [
  { value: "latest", label: "Latest" },
  { value: "trending", label: "Trending" },
  { value: "most_read", label: "Most read" },
  { value: "most_shared", label: "Most shared" },
  { value: "most_discussed", label: "Most discussed" },
  { value: "relevance", label: "Relevance" },
  { value: "oldest", label: "Oldest" },
];

/** Plain GET form: filters work without JavaScript and produce shareable URLs. */
export function SearchFilters({ params, categories, action = "/search" }: { params: Record<string, string | undefined>; categories: Category[]; action?: string }) {
  const control = "w-full rounded-md border border-line bg-surface px-3 py-2 text-sm";
  return (
    <form action={action} method="get" role="search" className="grid gap-3 rounded-lg border border-line bg-surface p-4 sm:grid-cols-2 lg:grid-cols-6">
      <div className="sm:col-span-2">
        <label htmlFor="f-q" className="mb-1 block text-xs font-medium">Keywords</label>
        <input id="f-q" name="q" type="search" defaultValue={params.q} className={control} placeholder="e.g. procurement, river pollution" />
      </div>
      <div>
        <label htmlFor="f-cat" className="mb-1 block text-xs font-medium">Category</label>
        <select id="f-cat" name="category" defaultValue={params.category ?? ""} className={control}>
          <option value="">All categories</option>
          {categories.map((c) => (
            <optgroup key={c.slug} label={c.name}>
              <option value={c.slug}>All in {c.name}</option>
              {c.children?.map((s) => <option key={s.slug} value={s.slug}>{s.name}</option>)}
            </optgroup>
          ))}
        </select>
      </div>
      <div>
        <label htmlFor="f-loc" className="mb-1 block text-xs font-medium">Location</label>
        <input id="f-loc" name="location" defaultValue={params.location} className={control} placeholder="District or city" />
      </div>
      <div>
        <label htmlFor="f-from" className="mb-1 block text-xs font-medium">Published from</label>
        <input id="f-from" name="from" type="date" defaultValue={params.from} className={control} />
      </div>
      <div>
        <label htmlFor="f-to" className="mb-1 block text-xs font-medium">Published to</label>
        <input id="f-to" name="to" type="date" defaultValue={params.to} className={control} />
      </div>
      <div>
        <label htmlFor="f-sort" className="mb-1 block text-xs font-medium">Sort by</label>
        <select id="f-sort" name="sort" defaultValue={params.sort ?? ""} className={control}>
          <option value="">Default</option>
          {SORTS.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
        </select>
      </div>
      {params.tag && <input type="hidden" name="tag" value={params.tag} />}
      {params.country && <input type="hidden" name="country" value={params.country} />}
      <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
        <button type="submit" className="h-10 rounded-md bg-accent px-4 text-sm font-medium text-white dark:text-paper">Apply filters</button>
        <a href={action} className="h-10 rounded-md px-4 py-2 text-sm hover:bg-accent-soft">Reset</a>
      </div>
    </form>
  );
}
