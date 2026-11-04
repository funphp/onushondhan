import { describe, expect, it } from "vitest";
import { leadRepeatsBody } from "./InvestigationArticle";

describe("leadRepeatsBody", () => {
  it("detects a summary generated from the opening of the report", () => {
    const body = "## পটভূমি\n\nতৈরি পোশাকশিল্পের **শ্রমিকদের** নতুন নিম্নতম মজুরি [ডিসেম্বরে](https://example.org) চূড়ান্ত হবে। আরও লেখা।";
    expect(leadRepeatsBody("তৈরি পোশাকশিল্পের শ্রমিকদের নতুন নিম্নতম মজুরি ডিসেম্বরে চূড়ান্ত হবে।", body)).toBe(true);
    expect(leadRepeatsBody("তৈরি পোশাকশিল্পের শ্রমিকদের নতুন…", body)).toBe(true);
  });

  it("keeps a hand-written summary", () => {
    expect(leadRepeatsBody("An audit of ferry subsidies.", "Subsidy records were obtained.")).toBe(false);
    expect(leadRepeatsBody("Anything", null)).toBe(false);
  });
});
