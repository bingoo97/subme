# Live Chat Overview

## Scope

This project uses one live chat system for:
- direct customer <-> admin conversations
- reseller/admin group chats
- shared chat rendering for the user panel and the admin panel

## Direct Chat

- Every logged user can use direct support chat when `support_chat_enabled` is ON.
- Direct chat is a `live_chat` conversation in `support_conversations`.
- Users write as `sender_type = customer`.
- Admins write as `sender_type = admin`.
- When multiple admins reply in the same conversation, the sender label above the bubble shows which admin wrote the message.
- Link preview detection works on both sides.
- Old live chat messages are removed by the retention setting and maintenance flow.

## Group Chats

- Group chats use `conversation_type = group_chat`.
- Only reseller users and admins can participate in group chats.
- Normal client accounts are excluded from group chat invites.
- Reseller users can create group chats from the user messenger only if:
  - their account type is `reseller`
  - support chat is enabled
  - `reseller_group_chat_limit` in settings is greater than `0`
  - their created group count is still below the configured limit
- Admins can create reseller/admin group chats from the admin live chat inbox.

## Reseller Group Limit

- Setting: `app_settings.reseller_group_chat_limit`
- Allowed range: `0` to `10`
- Meaning:
  - `0` = reseller users cannot create group chats
  - `1-10` = maximum number of group chats a reseller can create
- The limit affects only reseller-created groups.
- Admin-created groups are not blocked by this limit.
- If the reseller reaches the limit, the create-group action is hidden in the user UI and the backend also blocks manual requests.

## Invitations

- Adding a participant by email validates:
  - correct email format
  - existing reseller or active admin account
  - no duplicate invite entry in the same modal
  - no self-invite
- Invitations are stored as pending members in `support_conversation_members`.
- A pending invitation is valid for 24 hours.
- Pending invitations older than 24 hours are removed automatically during chat operations.
- Invite cards are shown:
  - for reseller users on homepage under the balance section
  - for admins at the top of the admin chat inbox
- Invitees can accept or reject without page reload.

## Group Permissions

- Any accepted group participant can leave the group at any time.
- Admins can enable read-only mode for a group.
- In read-only mode:
  - admins can still write
  - reseller participants can only read
- Existing group conversations remain visible even if reseller creation is later disabled.

## History Logging

- Group-related activity is logged into `customer_activity_logs` and shown in `/history`.
- Logged events include:
  - reseller-created group chat
  - invitation received
  - invite accepted
  - invite rejected
  - participant joined
  - participant left

## Main Runtime Tables

- `support_conversations`
- `support_messages`
- `support_conversation_members`
- `customer_activity_logs`

## Key Files

- `dashboard-panel/bootstrap/chat.php`
- `dashboard-panel/bootstrap/chat_groups.php`
- `dashboard-panel/check_chat.php`
- `dashboard-panel/admin/chat.php`
- `dashboard-panel/config/chat_config.php`
- `dashboard-panel/templates/messanger.tpl`
- `dashboard-panel/templates/messanger_content.tpl`
- `dashboard-panel/templates/profil/group_chat_invites.tpl`
- `public_html/assets/js/messanger.js`
- `public_html/assets/js/admin.js`
- `public_html/assets/css/messanger.css`
- `public_html/assets/css/admin.css`

