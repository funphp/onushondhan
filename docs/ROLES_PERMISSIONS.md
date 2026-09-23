# Roles & Permissions

## Roles

### Guest
Can browse published public content and use public search/share.

### Registered User
Can comment, bookmark, follow, support, react, submit tips, manage own profile, and manage own reports if they are granted investigator role.

### Investigator
All Registered User permissions plus investigation/evidence/source/finding/timeline/tip-inbox/analytics functions for owned resources.

### Moderator
Moderation queues, comments, reports, complaints, restricted content, and related audit visibility. No access to protected investigator identity unless explicitly granted by a separate least-privilege permission.

### Admin
System administration, users, content, roles, analytics, CMS, settings, complaints, and operational oversight.

### Super Admin
Full administration including role/permission configuration and high-risk system settings.

## Permission naming
Use granular permissions such as:

```text
users.view
users.manage
investigators.view
investigators.manage_own
investigators.manage_any
identity.view_private
identity.manage_private
investigations.view_public
investigations.create
investigations.manage_own
investigations.manage_any
investigations.publish_own
evidence.manage_own
evidence.view_private_owned
evidence.view_restricted
comments.create
comments.manage_own
comments.moderate
tips.create
tips.view_own
tips.manage_own
complaints.create
complaints.manage
support.create
support.revoke
analytics.view_own
analytics.view_any
audit.view
roles.manage
settings.manage
cms.manage
terms.manage
```

## Sensitive identity access
`identity.view_private` is separate from investigator management and should be granted only where operationally justified. Every use should create an audit log.

## Authorization principle
Every object mutation must verify ownership or an explicit admin/moderator permission at the policy/service layer. Never trust IDs supplied by the browser.
