"use client";

import { useCallback, useEffect, useState } from "react";
import { api, errorMessage } from "@/lib/api";

type Meta = { current_page: number; last_page: number; total: number; unread?: number };

/** Small fetch hook for authenticated, paginated lists. */
export function useApiList<T>(path: string) {
  const [items, setItems] = useState<T[] | null>(null);
  const [meta, setMeta] = useState<Meta | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [version, setVersion] = useState(0);

  useEffect(() => {
    let cancelled = false;
    const sep = path.includes("?") ? "&" : "?";
    api<{ data: T[]; meta: Meta }>(`${path}${sep}page=${page}`)
      .then((res) => {
        if (cancelled) return;
        setItems(res.data);
        setMeta(res.meta);
        setError(null);
      })
      .catch((e) => !cancelled && setError(errorMessage(e)));
    return () => {
      cancelled = true;
    };
  }, [path, page, version]);

  const reload = useCallback(async () => setVersion((v) => v + 1), []);

  return { items, meta, error, page, setPage, reload };
}
