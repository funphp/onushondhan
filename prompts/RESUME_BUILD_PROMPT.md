# Resume Build Prompt

Continue building Onushondhan.com autonomously from the current repository state.

First read:
- `CLAUDE.md`
- `docs/BUILD_STATUS.md`
- `docs/IMPLEMENTATION_PLAN.md`
- `docs/DEFINITION_OF_DONE.md`
- the relevant module documentation

Then inspect the actual code to determine what is implemented versus merely documented.

Do not assume a task is complete because the status file says so. Verify it with code and tests.

Select the earliest incomplete dependency-safe module, implement it fully, run tests, fix failures, update documentation/status, and continue to the next module.

Do not ask for confirmation for normal engineering choices. Ask only for missing secrets, external access, legal authorization, irreversible production operations, or genuinely missing business facts.
