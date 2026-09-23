"use client";

import type { ApiErrorBody } from "./types";

/**
 * Browser API client. Same-origin requests to /api/v1 (proxied to Laravel) using
 * Sanctum SPA cookie auth: the XSRF-TOKEN cookie is echoed in X-XSRF-TOKEN.
 */
export class ApiError extends Error {
  status: number;
  code: string;
  errors: Record<string, string[]>;
  requestId?: string;

  constructor(status: number, body: Partial<ApiErrorBody>) {
    super(body.message ?? "Something went wrong. Please try again.");
    this.status = status;
    this.code = body.code ?? "http_error";
    this.errors = body.errors ?? {};
    this.requestId = body.request_id;
  }

  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0];
  }
}

let csrfReady: Promise<void> | null = null;

function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.split("; ").find((c) => c.startsWith(name + "="));
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

export async function ensureCsrf(force = false): Promise<void> {
  if (force || !readCookie("XSRF-TOKEN")) csrfReady = null;
  if (!csrfReady) {
    csrfReady = fetch("/sanctum/csrf-cookie", { credentials: "same-origin" }).then(() => undefined);
  }
  return csrfReady;
}

type Method = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

export async function api<T = unknown>(path: string, options: { method?: Method; body?: unknown; signal?: AbortSignal } = {}, retried = false): Promise<T> {
  const method = options.method ?? "GET";
  const isForm = typeof FormData !== "undefined" && options.body instanceof FormData;
  if (method !== "GET") await ensureCsrf();

  const headers: Record<string, string> = { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" };
  if (!isForm && options.body !== undefined) headers["Content-Type"] = "application/json";
  const xsrf = readCookie("XSRF-TOKEN");
  if (xsrf) headers["X-XSRF-TOKEN"] = xsrf;

  const res = await fetch(`/api/v1${path}`, {
    method,
    headers,
    credentials: "same-origin",
    body: options.body === undefined ? undefined : isForm ? (options.body as FormData) : JSON.stringify(options.body),
    signal: options.signal,
  });

  if (res.status === 419 && !retried) {
    await ensureCsrf(true);
    return api<T>(path, options, true);
  }
  if (!res.ok) {
    let body: Partial<ApiErrorBody> = {};
    try {
      body = await res.json();
    } catch {
      body = { message: res.status >= 500 ? "The server had a problem. Please try again shortly." : "Request failed." };
    }
    throw new ApiError(res.status, body);
  }
  if (res.status === 204) return undefined as T;
  return (await res.json()) as T;
}

export function errorMessage(e: unknown): string {
  if (e instanceof ApiError) {
    // For validation errors the first field message is more useful than the generic summary.
    const first = Object.values(e.errors)[0]?.[0];
    return e.code === "validation_failed" && first ? first : e.message;
  }
  return "Something went wrong. Please check your connection and try again.";
}
