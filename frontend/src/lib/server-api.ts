import "server-only";
import type { ApiErrorBody } from "./types";

/**
 * Server-side (RSC) access to the Laravel API. Only used for public, non-personal
 * data: requests carry no user cookies, so nothing user-specific is ever cached.
 */
const API = (process.env.API_INTERNAL_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "") + "/api/v1";

export class ServerApiError extends Error {
  constructor(public status: number, public body: ApiErrorBody | null) {
    super(body?.message ?? `API error ${status}`);
  }
}

export async function serverGet<T>(path: string, options: { revalidate?: number | false; params?: Record<string, string | number | undefined | null> } = {}): Promise<T> {
  const url = new URL(API + path);
  for (const [k, v] of Object.entries(options.params ?? {})) {
    if (v !== undefined && v !== null && v !== "") url.searchParams.set(k, String(v));
  }
  const res = await fetch(url, {
    headers: { Accept: "application/json" },
    next: options.revalidate === false ? undefined : { revalidate: options.revalidate ?? 60 },
    cache: options.revalidate === false ? "no-store" : undefined,
  });
  if (!res.ok) {
    let body: ApiErrorBody | null = null;
    try {
      body = (await res.json()) as ApiErrorBody;
    } catch {
      body = null;
    }
    throw new ServerApiError(res.status, body);
  }
  return (await res.json()) as T;
}

/** Like serverGet but resolves to null on 404 (for notFound() handling). */
export async function serverGetOrNull<T>(path: string, options: Parameters<typeof serverGet>[1] = {}): Promise<T | null> {
  try {
    return await serverGet<T>(path, options);
  } catch (e) {
    if (e instanceof ServerApiError && (e.status === 404 || e.status === 422)) return null;
    if (!(e instanceof ServerApiError)) {
      // Network failure (API unreachable, e.g. during a build): degrade gracefully.
      console.error(`[server-api] ${path} unreachable`, e instanceof Error ? e.message : e);
      return null;
    }
    throw e;
  }
}
