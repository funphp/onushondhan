"use client";

import { Button } from "@/components/ui/Button";
import { useI18n } from "@/i18n/client";

export function ListPager({ page, lastPage, setPage }: { page: number; lastPage: number; setPage: (p: number) => void }) {
  const { t, f } = useI18n();
  if (lastPage <= 1) return null;
  return (
    <div className="mt-4 flex items-center gap-2 text-sm">
      <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>{t.common.previous}</Button>
      <span>{t.common.pageOf(f.digits(page), f.digits(lastPage))}</span>
      <Button size="sm" variant="secondary" disabled={page >= lastPage} onClick={() => setPage(page + 1)}>{t.common.next}</Button>
    </div>
  );
}
