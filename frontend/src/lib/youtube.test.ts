import { describe, expect, it } from "vitest";
import { parseYouTube, youtubeEmbedUrl, youtubeUrl } from "./youtube";

describe("parseYouTube", () => {
  it("understands the common YouTube link forms", () => {
    for (const url of [
      "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
      "https://youtube.com/watch?v=dQw4w9WgXcQ&list=x",
      "https://m.youtube.com/watch?v=dQw4w9WgXcQ",
      "https://youtu.be/dQw4w9WgXcQ",
      "https://www.youtube.com/shorts/dQw4w9WgXcQ",
      "https://www.youtube.com/embed/dQw4w9WgXcQ",
      "https://www.youtube.com/live/dQw4w9WgXcQ?si=abc",
    ]) expect(parseYouTube(url)?.id, url).toBe("dQw4w9WgXcQ");
  });

  it("keeps a start time", () => {
    expect(parseYouTube("https://youtu.be/dQw4w9WgXcQ?t=90")?.start).toBe(90);
    expect(parseYouTube("https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1m30s")?.start).toBe(90);
    expect(youtubeUrl({ id: "dQw4w9WgXcQ", start: 90 })).toBe("https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=90s");
    expect(youtubeEmbedUrl({ id: "dQw4w9WgXcQ", start: 90 })).toBe("https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0&start=90");
  });

  it("rejects look-alikes and other sites", () => {
    for (const url of [
      "https://evil.com/watch?v=dQw4w9WgXcQ",
      "https://youtube.com.evil.com/watch?v=dQw4w9WgXcQ",
      "javascript:alert(1)//youtu.be/dQw4w9WgXcQ",
      "https://www.youtube.com/watch?v=short",
      "https://www.youtube.com/@channel",
      "not a url",
    ]) expect(parseYouTube(url), url).toBeNull();
  });
});
