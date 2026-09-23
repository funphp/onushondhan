import { afterEach, describe, expect, it, vi } from "vitest";
import { api, ApiError, errorMessage } from "./api";

afterEach(() => vi.unstubAllGlobals());

describe("api client", () => {
  it("sends the XSRF token and same-origin credentials, and parses the error envelope", async () => {
    document.cookie = "XSRF-TOKEN=abc%3D%3D";
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ message: "The submitted data is invalid.", code: "validation_failed", errors: { body: ["Too short."] }, request_id: "r1" }), { status: 422 }));
    vi.stubGlobal("fetch", fetchMock);

    const err = (await api("/comments/x", { method: "PATCH", body: { body: "a" } }).catch((e: unknown) => e)) as ApiError;
    const [url, init] = fetchMock.mock.calls.at(-1)!;
    expect(url).toBe("/api/v1/comments/x");
    expect(init.credentials).toBe("same-origin");
    expect(init.headers["X-XSRF-TOKEN"]).toBe("abc==");
    expect(err).toBeInstanceOf(ApiError);
    expect(err.code).toBe("validation_failed");
    expect(err.fieldError("body")).toBe("Too short.");
    expect(errorMessage(err)).toBe("Too short.");
  });

  it("retries once after a CSRF mismatch (419)", async () => {
    document.cookie = "XSRF-TOKEN=t";
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(new Response("{}", { status: 419 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ ok: true }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);
    await expect(api("/x", { method: "POST" })).resolves.toEqual({ ok: true });
    expect(fetchMock.mock.calls.map((c) => c[0])).toEqual(["/api/v1/x", "/sanctum/csrf-cookie", "/api/v1/x"]);
  });
});
