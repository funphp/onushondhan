import { describe, expect, it } from "vitest";
import { formatBytes, humanize, investigationPath, safeHref } from "./format";
import { safeNext } from "./nav";

describe("safeHref", () => {
  it("allows http(s) only", () => {
    expect(safeHref("https://example.org/a")).toBe("https://example.org/a");
    expect(safeHref("http://example.org")).toBe("http://example.org/");
    expect(safeHref("javascript:alert(1)")).toBeNull();
    expect(safeHref("data:text/html,<script>")).toBeNull();
    expect(safeHref("/relative")).toBeNull();
    expect(safeHref(null)).toBeNull();
  });
});

describe("safeNext (open-redirect protection)", () => {
  it("keeps same-site paths and rejects everything else", () => {
    expect(safeNext("/dashboard")).toBe("/dashboard");
    expect(safeNext("//evil.example")).toBe("/");
    expect(safeNext("/\\evil.example")).toBe("/");
    expect(safeNext("https://evil.example")).toBe("/");
    expect(safeNext(undefined)).toBe("/");
  });
});

describe("formatting", () => {
  it("formats bytes, labels and paths", () => {
    expect(formatBytes(512)).toBe("512 B");
    expect(formatBytes(2048)).toBe("2.0 KB");
    expect(formatBytes(5 * 1024 * 1024)).toBe("5.0 MB");
    expect(humanize("action_requested")).toBe("Action requested");
    expect(investigationPath("INV-2026-AAAA1111", "slug")).toBe("/investigation/INV-2026-AAAA1111/slug");
  });
});
