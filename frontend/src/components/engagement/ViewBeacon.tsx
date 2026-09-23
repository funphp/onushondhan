"use client";

import { useEffect } from "react";

/** Counts a read client-side (so crawlers/prefetches don't inflate views). */
export function ViewBeacon({ id }: { id: string }) {
  useEffect(() => {
    const t = setTimeout(() => {
      void fetch(`/api/v1/investigations/${encodeURIComponent(id)}/view`, { method: "POST", credentials: "same-origin", headers: { Accept: "application/json" } }).catch(() => undefined);
    }, 3000);
    return () => clearTimeout(t);
  }, [id]);
  return null;
}
