import Link from "next/link";

/** Link-based pagination (works without JavaScript; crawlable). */
export function Pagination({ page, lastPage, hrefFor }: { page: number; lastPage: number; hrefFor: (page: number) => string }) {
  if (lastPage <= 1) return null;
  const pages = Array.from(new Set([1, page - 1, page, page + 1, lastPage])).filter((p) => p >= 1 && p <= lastPage).sort((a, b) => a - b);
  return (
    <nav aria-label="Pagination" className="mt-8 flex flex-wrap items-center justify-center gap-1">
      {page > 1 && <Link rel="prev" className="rounded px-3 py-1 hover:bg-accent-soft" href={hrefFor(page - 1)}>← Previous</Link>}
      {pages.map((p, i) => (
        <span key={p} className="flex items-center">
          {i > 0 && pages[i - 1] !== p - 1 && <span className="px-1 text-muted">…</span>}
          {p === page ? (
            <span aria-current="page" className="rounded bg-accent px-3 py-1 text-white dark:text-paper">{p}</span>
          ) : (
            <Link className="rounded px-3 py-1 hover:bg-accent-soft" href={hrefFor(p)}>{p}</Link>
          )}
        </span>
      ))}
      {page < lastPage && <Link rel="next" className="rounded px-3 py-1 hover:bg-accent-soft" href={hrefFor(page + 1)}>Next →</Link>}
    </nav>
  );
}
