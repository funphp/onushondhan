import { describe, expect, it } from "vitest";
import { decodeParam, investigationPath, safeHref } from "./format";
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

describe("paths", () => {
  it("builds investigation paths and decodes Bangla slug params", () => {
    expect(investigationPath("INV-2026-AAAA1111", "slug")).toBe("/investigation/INV-2026-AAAA1111/slug");
    expect(decodeParam(encodeURIComponent("নদী-দূষণ"))).toBe("নদী-দূষণ");
    expect(decodeParam("নদী-দূষণ")).toBe("নদী-দূষণ");
    expect(decodeParam("%E0%A6")).toBe("%E0%A6");
  });
});
