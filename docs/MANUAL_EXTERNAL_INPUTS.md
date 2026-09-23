# Manual External Inputs

Claude should minimize this list and only populate it with items that genuinely cannot be safely fabricated.

Typical external inputs may include:
- production domain/DNS control;
- production DB credentials;
- SMTP credentials/domain verification;
- third-party OAuth credentials;
- server/SSH credentials;
- TLS certificates or certificate-management authority;
- legal counsel approval of Terms/Privacy/Content Policy/Investigator Responsibility Agreement;
- organizational branding assets.

Everything else should be implemented, tested, and documented by the agent.
