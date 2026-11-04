import { Editor } from "@tiptap/core";
import { Markdown } from "@tiptap/markdown";
import StarterKit from "@tiptap/starter-kit";
import { afterEach, describe, expect, it } from "vitest";
import { YouTubeVideo } from "./YouTubeNode";

let editor: Editor | null = null;
afterEach(() => editor?.destroy());

const make = (content: string) =>
  (editor = new Editor({ extensions: [StarterKit, Markdown, YouTubeVideo], content, contentType: "markdown" }));

describe("YouTube node in the report editor", () => {
  it("reads a YouTube line as a video and saves it back as a link line", () => {
    const e = make("Intro text.\n\nhttps://youtu.be/dQw4w9WgXcQ\n\nMore text.");
    expect(e.getJSON().content?.map((n) => n.type)).toEqual(["paragraph", "youtubeVideo", "paragraph"]);
    expect(e.getMarkdown()).toBe("Intro text.\n\nhttps://www.youtube.com/watch?v=dQw4w9WgXcQ\n\nMore text.");
  });

  it("inserts videos from any YouTube link and refuses other links", () => {
    const e = make("Text");
    e.commands.focus("end");
    expect(e.commands.insertYouTube("https://www.youtube.com/shorts/dQw4w9WgXcQ")).toBe(true);
    expect(e.commands.insertYouTube("https://example.org/video")).toBe(false);
    expect(e.getMarkdown()).toContain("https://www.youtube.com/watch?v=dQw4w9WgXcQ");
  });
});
