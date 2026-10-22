import { describe, expect, it } from "vitest";
import { DEFAULT_LOCALE, getMessages, isLocale, labelFor } from "./index";
import { makeFormat } from "./format";
import { bn } from "./messages/bn";
import { en } from "./messages/en";

/** Every key in the English dictionary, as dotted paths (functions count as leaves). */
function paths(obj: Record<string, unknown>, prefix = ""): string[] {
  return Object.entries(obj).flatMap(([k, v]) =>
    v && typeof v === "object" ? paths(v as Record<string, unknown>, `${prefix}${k}.`) : [`${prefix}${k}`],
  );
}

describe("dictionaries", () => {
  it("defaults to Bangla and only accepts supported locales", () => {
    expect(DEFAULT_LOCALE).toBe("bn");
    expect(isLocale("bn")).toBe(true);
    expect(isLocale("en")).toBe(true);
    expect(isLocale("fr")).toBe(false);
    expect(isLocale(undefined)).toBe(false);
  });

  it("has the same keys and kinds in Bangla and English", () => {
    expect(paths(bn)).toEqual(paths(en));
    for (const path of paths(en)) {
      const pick = (d: unknown) => path.split(".").reduce((o, k) => (o as Record<string, unknown>)[k], d);
      expect(typeof pick(bn), path).toBe(typeof pick(en));
    }
  });

  it("actually translates interface text into Bangla", () => {
    const t = getMessages("bn");
    expect(t.nav.discover).not.toBe(en.nav.discover);
    expect(t.auth.signIn).toMatch(/[ঀ-৿]/);
    expect(t.brand.disclaimer).toMatch(/[ঀ-৿]/);
    // Language names are always shown in their own script.
    expect(t.language.bn).toBe("বাংলা");
    expect(t.language.en).toBe("English");
  });

  it("labels API enum values with a readable fallback", () => {
    expect(labelFor(getMessages("en").statuses, "action_requested")).toBe("Action requested");
    expect(labelFor(getMessages("bn").statuses, "published")).toBe(bn.statuses.published);
    expect(labelFor(en.statuses, "some_new_state")).toBe("some new state");
    expect(labelFor(en.statuses, null)).toBe("");
  });
});

describe("formatting", () => {
  it("uses Bengali digits for Bangla and Latin digits for English", () => {
    expect(makeFormat("bn").number(1234)).toBe("১,২৩৪");
    expect(makeFormat("en").number(1234)).toBe("1,234");
    expect(makeFormat("bn").digits(2026)).toBe("২০২৬");
  });

  it("formats bytes and dates per locale", () => {
    expect(makeFormat("en").bytes(512)).toBe("512 B");
    expect(makeFormat("en").bytes(2048)).toBe("2.0 KB");
    expect(makeFormat("bn").bytes(5 * 1024 * 1024)).toBe("৫.০ MB");
    expect(makeFormat("en").date("2026-03-05T10:00:00Z")).toBe("5 Mar 2026");
    expect(makeFormat("bn").date("2026-03-05T10:00:00Z")).toMatch(/৫.*২০২৬/);
    expect(makeFormat("en").date(null)).toBe("");
  });
});
