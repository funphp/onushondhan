import { afterEach, describe, expect, it, vi } from "vitest";

async function load(env: Record<string, string>) {
  vi.resetModules();
  for (const [k, v] of Object.entries(env)) vi.stubEnv(k, v);
  return import("./third-party");
}

afterEach(() => vi.unstubAllEnvs());

describe("third-party config", () => {
  it("is fully disabled without ids, adding nothing to the CSP", async () => {
    const m = await load({ NEXT_PUBLIC_GA_MEASUREMENT_ID: "", NEXT_PUBLIC_ADSENSE_CLIENT: "" });
    expect(m.THIRD_PARTY_ENABLED).toBe(false);
    expect(m.thirdPartyCsp()).toEqual({ script: [], connect: [], frame: [], img: [] });
  });

  it("rejects malformed ids instead of injecting them into script URLs", async () => {
    const m = await load({ NEXT_PUBLIC_GA_MEASUREMENT_ID: "G-ABC\"><script>", NEXT_PUBLIC_ADSENSE_CLIENT: "pub-123" });
    expect(m.ANALYTICS_ENABLED).toBe(false);
    expect(m.ADS_ENABLED).toBe(false);
  });

  it("accepts valid ids, derives the ads.txt publisher id and falls back to the display slot", async () => {
    const m = await load({
      NEXT_PUBLIC_GA_MEASUREMENT_ID: "G-AB12CD34EF",
      NEXT_PUBLIC_ADSENSE_CLIENT: "ca-pub-1234567890123456",
      NEXT_PUBLIC_ADSENSE_SLOT_DISPLAY: "1234567890",
      NEXT_PUBLIC_ADSENSE_SLOT_ARTICLE: "",
    });
    expect(m.ANALYTICS_ENABLED && m.ADS_ENABLED).toBe(true);
    expect(m.ADSENSE_PUBLISHER_ID).toBe("pub-1234567890123456");
    expect(m.ADSENSE_SLOTS.article).toBe("1234567890");
    const csp = m.thirdPartyCsp();
    expect(csp.script).toContain("https://www.googletagmanager.com");
    expect(csp.script).toContain("https://pagead2.googlesyndication.com");
  });

  it("excludes account, sign-in, tip and complaint pages", async () => {
    const m = await load({});
    for (const p of ["/dashboard", "/dashboard/investigations/x", "/tips/new", "/tips/access", "/login", "/account/security", "/report?investigation=1", "/agreements"]) {
      expect(m.isThirdPartyExcluded(p)).toBe(true);
    }
    for (const p of ["/", "/investigation/abc/slug", "/search", "/category/x", "/reports-archive", "/tipster"]) {
      expect(m.isThirdPartyExcluded(p)).toBe(false);
    }
  });

  it("strips query strings and fragments from the analytics location", async () => {
    const m = await load({});
    expect(m.analyticsLocation("https://onusondhan.com/", "/search?q=secret#x")).toBe("https://onusondhan.com/search");
  });

  it("reads only valid consent values from the cookie string", async () => {
    const m = await load({});
    expect(m.readConsentCookie("a=1; ons_consent=granted; b=2")).toBe("granted");
    expect(m.readConsentCookie("ons_consent=denied")).toBe("denied");
    expect(m.readConsentCookie("ons_consent=yes")).toBeNull();
    expect(m.readConsentCookie("")).toBeNull();
  });
});
