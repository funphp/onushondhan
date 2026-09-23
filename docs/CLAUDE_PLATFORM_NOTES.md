# Claude Desktop / Claude Code Integration Notes

Claude's current desktop workflow supports local folders, project instructions, project files/context, and agentic code/file work on supported paid plans. Claude Code automatically reads a repository-level `CLAUDE.md` when working in that directory.

For this project, `CLAUDE.md` is therefore the primary persistent agent instruction file, while `prompts/MASTER_BUILD_PROMPT.md` is the initial task prompt.

Current official references used while preparing this package:
- https://support.claude.com/en/articles/14116274-organize-your-tasks-with-projects-in-claude-cowork
- https://support.claude.com/en/articles/14553240-give-claude-context-claude-md-and-better-prompts
- https://support.claude.com/en/articles/12111783-create-and-edit-files-with-claude
- https://support.claude.com/en/articles/10065433-install-claude-desktop

Important: exact Claude Desktop/Cowork UI and feature availability can change. The repository files and implementation instructions are designed to remain useful even if the UI changes.
