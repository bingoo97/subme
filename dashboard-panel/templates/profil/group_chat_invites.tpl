{if isset($group_chat_pending_invites) && $group_chat_pending_invites|@count gt 0}
<div class="group-chat-invites-home" id="group_chat_invites_home">
    {foreach from=$group_chat_pending_invites item=groupInvite}
        {assign var=groupInviteTitle value=$groupInvite.group_name|default:$groupInvite.subject|default:'Group chat'}
        {assign var=groupInviteSender value=$groupInvite.invited_by_customer_email|default:$groupInvite.invited_by_admin_handle|default:$groupInvite.invited_by_admin_login|default:'Support'}
        <div class="group-chat-invite-card" data-group-chat-invite-card data-conversation-id="{$groupInvite.conversation_id}">
            <div class="group-chat-invite-card__main">
                <strong>{$groupInviteTitle|escape:'html'}</strong>
                <p>{$t.group_chat_invite_message|default:'You were invited to a group chat by'} {$groupInviteSender|escape:'html'}.</p>
                <span class="group-chat-invite-card__hint">{$t.group_chat_invite_expiry_note|default:'Invitation is valid for 24 hours.'}</span>
            </div>
            <div class="group-chat-invite-card__actions">
                <button type="button" class="btn btn-dark btn-sm" data-group-chat-invite-action="accept" data-conversation-id="{$groupInvite.conversation_id}">{$t.group_chat_invite_accept|default:'Accept'}</button>
                <button type="button" class="btn btn-default btn-sm" data-group-chat-invite-action="reject" data-conversation-id="{$groupInvite.conversation_id}">{$t.group_chat_invite_reject|default:'Reject'}</button>
            </div>
        </div>
    {/foreach}
</div>
{/if}
