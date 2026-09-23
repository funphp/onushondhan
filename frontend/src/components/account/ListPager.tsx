"use client";

import { Button } from "@/components/ui/Button";

export function ListPager({ page, lastPage, setPage }: { page: number; lastPage: number; setPage: (p: number) => void }) {
  if (lastPage <= 1) return null;
  return (
    <div className="mt-4 flex items-center gap-2 text-sm">
      <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
      <span>Page {page} of {lastPage}</span>
      <Button size="sm" variant="secondary" disabled={page >= lastPage} onClick={() => setPage(page + 1)}>Next</Button>
    </div>
  );
}
