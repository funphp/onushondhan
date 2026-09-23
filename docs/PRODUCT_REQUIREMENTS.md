# Product Requirements

## 1. Product purpose
Onushondhan.com is a digital publishing platform for independent investigators to document and publish investigations, evidence, sources, findings, timelines, and related material.

The platform supports publication, discovery, community discussion, tips, following, support, moderation, and administration.

## 2. Product boundary
The platform does not itself become the investigator or author of user reports. A report is published under the investigator's account/public identity. Investigators remain responsible for their statements, evidence, source use, permissions, legality, and consequences.

Publication does not mean factual verification or endorsement by Onushondhan.

## 3. Primary actors
- Guest
- Registered User
- Investigator
- Moderator
- Admin
- Super Admin

## 4. Core journeys
### Guest
Browse homepage -> search/discover -> open investigator -> open investigation -> view public evidence -> share.

### Registered user
Register -> accept current agreements -> verify email -> browse -> comment -> bookmark -> follow investigator -> support investigator -> submit tip.

### Investigator
Register -> accept agreements -> create investigator profile -> choose public identity -> create investigation -> upload evidence -> preview -> confirm responsibility -> publish -> receive comments/tips/support/follows -> update/withdraw -> view analytics.

### Moderator
Review queue -> inspect reported content -> apply moderation action -> create audit event -> notify affected user where appropriate.

### Admin
Manage users/investigators/content/roles/settings/CMS/complaints/analytics/audit -> monitor health -> prepare reports.

## 5. Investigation fields
- public identifier
- title
- slug
- summary
- body/content
- category
- subcategory
- tags
- geographic location
- investigation period
- involved people/entities (structured references, not arbitrary claims about persons as verified facts)
- findings
- recommendations
- sources
- timeline
- evidence
- public identity mode
- status
- publication timestamps
- version number

## 6. Public investigation page
Must display:
- title
- summary
- investigator attribution/public identity
- publication/update date
- category/tags/location
- report body
- findings
- timeline
- sources
- public evidence/media
- comments
- related investigations
- share controls
- tip action
- follow investigator
- support investigator
- bookmark
- report content/complaint entry point where appropriate
- responsibility/disclaimer notice

## 7. Engagement
### Investigator support
A registered user may support an investigator once. Removing/re-adding support must not allow repeated inflation. Use unique DB constraint plus application throttling and anti-abuse checks.

The support count is a community engagement signal, not a truth or accuracy score.

### Leaderboard
Use neutral labels such as:
- Top Supported Investigators
- Rising Investigators
- Most Published
- Most Read
- Most Shared
- Most Discussed

Do not label a ranking as "most accurate" or "best investigator".

## 8. Social sharing
Support share intents for Facebook, X, LinkedIn, WhatsApp, Telegram, Messenger, email, and copy link where technically appropriate. Use Open Graph metadata and a stable canonical URL.

## 9. Comments
Registered users can comment, reply, edit/delete their own comments, upvote, and report comments. Moderators can hide/restore/delete/pin/lock/restrict.

## 10. Tips
Tips may be anonymous to the investigator. The platform must distinguish "anonymous to investigator" from "anonymous to platform". Store only what policy and architecture require, and protect tip attachments.

## 11. Analytics
Track, at minimum:
- views
- shares
- comments
- tips
- supporters
- followers
- bookmarks
- publication counts
- engagement over time

Avoid collecting unnecessary personal data.

## 12. CMS
Required pages:
- About
- How It Works
- Terms of Use
- Privacy Policy
- Content Policy
- Investigator Responsibility Agreement
- FAQ
- Contact
- Announcements

Legal/policy content is a product template and must be reviewed by qualified counsel before production adoption.
