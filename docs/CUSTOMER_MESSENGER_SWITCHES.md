# Customer Messenger Switches

## Goal

This project keeps the current reseller messenger flow unchanged, but allows normal client accounts to receive the same expanded messenger UI and selected conversation features through explicit feature switches in `app_settings`.

The rollout is intentionally staged to avoid regressions in the existing support chat, reseller group chats, and admin inbox flows.

## Core Rules

### Reseller accounts

- Resellers keep the current behavior and UI.
- They can use the expanded messenger view.
- They can start direct conversations.
- They can create group chats.
- They still use the existing reseller group limit from `reseller_group_chat_limit`.
- They can never invite customer accounts that do not have messenger access.

### Client accounts

- By default, clients keep the current support-only chat.
- When `customer_messenger_enabled = OFF`, the client sees only:
  - support chat with admin
  - no create-conversation flow
  - no group inbox behavior
  - no public messenger identity editing
- When `customer_messenger_enabled = ON`, the client gets the expanded messenger shell like a reseller:
  - inbox view
  - messenger identity editing in profile settings
  - visibility of eligible direct/group/global conversations

### Admin accounts

- Admins stay outside customer-created direct conversations unless they are explicitly part of support chat or the global group.
- Clients must not be able to invite admins into private direct conversations or private groups.
- Admins remain available only in:
  - support chat
  - admin-created chat contexts
  - the optional global group

## Feature Switches

### `customer_messenger_enabled`

Controls whether client accounts can use the expanded messenger experience.

When `OFF`:
- client accounts stay in support-chat mode only

When `ON`:
- client accounts can use the full messenger shell
- further abilities are still controlled by the switches below

### `customer_direct_chat_enabled`

Controls whether client accounts can start direct `1:1` conversations.

When `OFF`:
- clients can still have the expanded messenger shell if `customer_messenger_enabled = ON`
- but they cannot create new private conversations

When `ON`:
- clients can start direct conversations with other eligible client accounts
- clients cannot invite admins

### `customer_group_chat_enabled`

Controls whether client accounts can create multi-member group conversations and invite other eligible clients.

When `OFF`:
- clients cannot create named groups
- existing groups remain visible if the account is already a member

When `ON`:
- clients can create group chats
- clients can invite other eligible client accounts
- clients cannot invite admins

### `customer_global_group_enabled`

Controls a permanent global group visible to all active accounts that have messenger access plus active admins.

When `OFF`:
- no global group is shown

When `ON`:
- a special global conversation must exist
- active admins are members
- active resellers are members
- active clients with messenger access are members
- members can post messages
- the conversation cannot be deleted or permanently left by normal users

## Eligibility Model

### Messenger-enabled clients

A client is treated as messenger-enabled when all of the following are true:

- the account is a normal client account
- `support_chat_enabled = ON`
- `customer_messenger_enabled = ON`

### Eligible invitees

Invite lookups must return only participants allowed by the new rules:

- resellers remain eligible as today
- clients are eligible only when they have messenger access
- admins are never valid invitees for customer-created direct/group conversations

This means the invite search and validation layer must not rely only on role names. It must also check messenger eligibility.

## Conversation Types

### Support chat

- Type: `live_chat`
- Always available when `support_chat_enabled = ON`
- Includes customer <-> admin support flow
- Unchanged by the new client messenger switches

### Direct conversation

- Reuses the current compact conversation model based on `group_chat`
- Has exactly one invited participant besides the creator
- Controlled for clients by `customer_direct_chat_enabled`

### Named group conversation

- Reuses the current `group_chat` model
- Has multiple members
- Controlled for clients by `customer_group_chat_enabled`

### Global group

- Should be treated as a special system-managed conversation
- Recommended implementation:
  - `conversation_type = 'global_group'`
  - not creator-owned in the normal customer sense
  - not deletable by standard customer actions
  - membership synchronized automatically

## Membership Rules

### Direct and private groups

- Client creators can invite only other eligible client accounts.
- Client creators cannot invite admins.
- Client creators cannot invite themselves.
- Existing duplicate/pending membership rules continue to apply.

### Global group

- Membership is derived from account state, not manual invites.
- Required members:
  - active admins
  - active resellers
  - active clients with messenger access

Recommended sync triggers:
- login
- settings save for messenger flags
- admin status/account changes
- maintenance runner fallback

## Profile Identity

When a client has expanded messenger access:

- the profile settings page should expose:
  - avatar upload
  - public handle editing

When a client does not have expanded messenger access:

- those fields stay hidden

Resellers keep the current behavior unchanged.

## UI Expectations

### Client with support-only mode

- title remains support-oriented
- no create-conversation button
- no multi-conversation inbox

### Client with full messenger enabled

- messenger title should behave like the reseller view
- inbox view is visible
- support conversation remains included
- direct/group/global conversations appear according to membership and feature switches

### Admin settings

Feature settings should expose separate switches for:

- customer full messenger
- customer direct chat
- customer group chat
- customer global group

These switches belong next to existing support chat / reseller group chat settings.

## Safety Constraints

- Admins must not become inviteable by clients in private conversations.
- Turning a switch OFF must not delete existing conversations automatically.
- Existing conversations should remain readable to their members unless explicit cleanup is required.
- Reseller flows must stay backward compatible.

## Recommended Rollout

### Stage 1

- Add settings columns and admin UI
- Add central permission helpers
- Allow clients with `customer_messenger_enabled = ON` to see the expanded messenger shell
- Allow those clients to edit handle/avatar

### Stage 2

- Enable direct client-to-client conversations behind `customer_direct_chat_enabled`
- Keep admins blocked from client invite flow

### Stage 3

- Enable client-created named groups behind `customer_group_chat_enabled`
- Keep invite validation limited to eligible clients

### Stage 4

- Add global group behind `customer_global_group_enabled`
- Synchronize membership automatically
- Prevent delete/leave misuse for the system conversation

## Files Expected To Change

- `dashboard-panel/bootstrap/application.php`
- `dashboard-panel/bootstrap/chat_groups.php`
- `dashboard-panel/check_chat.php`
- `dashboard-panel/config/chat_config.php`
- `dashboard-panel/pages/homepage.php`
- `dashboard-panel/pages/profil/settings.php`
- `dashboard-panel/templates/messanger.tpl`
- `dashboard-panel/templates/messanger_content.tpl`
- `dashboard-panel/templates/profil/settings.tpl`
- `dashboard-panel/admin/bootstrap.php`
- `dashboard-panel/admin/index.php`
- `dashboard-panel/admin/locales/pl.php`
- `dashboard-panel/admin/locales/en.php`
- `dashboard-panel/locales/pl.php`
- `dashboard-panel/locales/en.php`

## Current Implementation Status

At the moment of writing this document, the foundation work is intended to cover:

- new settings flags
- central permission helpers
- admin settings controls
- basic client messenger shell gating
- client messenger identity gating

Direct conversations, client-created groups, and the global group should be completed in the following stages using the same rules defined above.
