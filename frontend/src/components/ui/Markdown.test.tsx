import { render } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { Markdown } from "./Markdown";

describe("Markdown (user-authored content)", () => {
  it("renders formatting but never raw HTML", () => {
    const { container } = render(<Markdown>{"## Heading\n\n**bold** <script>alert(1)</script><img src=x onerror=alert(1)>"}</Markdown>);
    expect(container.querySelector("h2")?.textContent).toBe("Heading");
    expect(container.querySelector("strong")?.textContent).toBe("bold");
    expect(container.querySelector("script")).toBeNull();
    expect(container.querySelector("img")).toBeNull();
    expect(container.innerHTML).not.toContain("onerror");
  });

  it("neutralises javascript: links and hardens external links", () => {
    const { container } = render(<Markdown>{"[bad](javascript:alert(1)) [good](https://example.org)"}</Markdown>);
    const links = container.querySelectorAll("a");
    expect(links).toHaveLength(1);
    expect(links[0].getAttribute("href")).toBe("https://example.org/");
    expect(links[0].getAttribute("rel")).toContain("noopener");
    expect(links[0].getAttribute("rel")).toContain("nofollow");
  });

  it("does not embed remote images", () => {
    const { container } = render(<Markdown>{"![tracker](https://evil.example/pixel.png)"}</Markdown>);
    expect(container.querySelector("img")).toBeNull();
    expect(container.textContent).toContain("[🖼 tracker]");
  });
});

describe("Markdown YouTube videos", () => {
  it("turns a YouTube link on its own line into a click-to-play video", async () => {
    const { container, getByRole } = render(<Markdown>{"Before.\n\nhttps://youtu.be/dQw4w9WgXcQ?t=5\n\nAfter with an inline https://youtu.be/dQw4w9WgXcQ link."}</Markdown>);
    // Only the thumbnail is loaded until play is pressed.
    expect(container.querySelector("iframe")).toBeNull();
    expect(container.querySelector("img")?.getAttribute("src")).toBe("https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg");
    getByRole("button").click();
    await new Promise((r) => setTimeout(r, 0));
    expect(container.querySelector("iframe")?.getAttribute("src")).toBe("https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0&start=5");
    // A link inside a sentence stays a normal link.
    expect(container.querySelectorAll("figure")).toHaveLength(1);
  });
});
