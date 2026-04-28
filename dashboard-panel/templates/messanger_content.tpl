{if $user.logged}
	<div id="content_chat_profil" data-chat-last-id="{$chat_last_message_id|default:0}" data-chat-oldest-id="{$chat_oldest_message_id|default:0}" data-chat-message-limit="{$chat_message_limit|default:10}" data-chat-loaded-message-count="{$chat_loaded_message_count|default:0}" data-chat-total-message-count="{$chat_total_message_count|default:0}" data-chat-has-more-messages="{if $chat_has_more_messages|default:false}1{else}0{/if}" data-chat-active-conversation-id="{$chat_active_conversation_id|default:0}" data-chat-active-conversation-type="{$chat_active_conversation_type|default:'live_chat'}" data-chat-active-conversation-title="{$chat_active_conversation_title|default:''|escape:'html'}" data-chat-can-send="{if $chat_active_conversation_can_send|default:true}1{else}0{/if}" data-chat-can-manage-group="{if $chat_active_conversation_can_manage|default:false}1{else}0{/if}">
        <div class="messenger-group-invites-slot" data-group-chat-invites-slot>
            {include file='profil/group_chat_invites.tpl'}
        </div>
        {if $user.customer_type|default:'client' eq 'reseller' && isset($chat_conversations) && $chat_conversations|@count gt 0}
        <div class="admin-chat-inbox__list-view messenger-inbox__list-view" data-chat-list-view>
            <div class="admin-chat-inbox__list">
                {foreach from=$chat_conversations item=chatConversation}
                <button
                    type="button"
                    class="admin-chat-inbox__item messenger-inbox__item{if $chatConversation.id == $chat_active_conversation_id && $chatConversation.type == $chat_active_conversation_type} is-active{/if}{if $chatConversation.unread_count|default:0 gt 0} is-unread{/if}"
                    data-chat-conversation-tab
                    data-conversation-id="{$chatConversation.id|default:0}"
                    data-conversation-type="{$chatConversation.type|default:'live_chat'}"
                    title="{$chatConversation.title|escape:'html'}"
                >
                    <span class="messenger-avatar-stack messenger-avatar-stack--list">
                        <span class="admin-chat-inbox__avatar {$chatConversation.avatar_theme|default:'theme-6'}">
                            {if $chatConversation.avatar_url|default:'' ne ''}
                            <img src="{$chatConversation.avatar_url|escape:'html'}" alt="{$chatConversation.title|escape:'html'}" />
                            {else}
                            {$chatConversation.avatar_text|default:'S'|escape:'html'}
                            {/if}
                        </span>
                        <span class="{if isset($chatConversation.presence.class_name)}{$chatConversation.presence.class_name|escape:'html'}{else}admin-chat-presence admin-chat-presence--offline{/if} messenger-avatar-stack__presence" title="{if isset($chatConversation.presence.label)}{$chatConversation.presence.label|escape:'html'}{else}Offline{/if}" aria-label="{if isset($chatConversation.presence.label)}{$chatConversation.presence.label|escape:'html'}{else}Offline{/if}"></span>
                    </span>
                    <span class="admin-chat-inbox__item-content">
                        <span class="admin-chat-inbox__item-head">
                            <span class="admin-chat-inbox__item-title">
                                <strong>{$chatConversation.title|truncate:26:"..."|escape:'html'}</strong>
                            </span>
                            <span>{if $chatConversation.updated_at|default:'' ne ''}{$chatConversation.updated_at|date_format:"%d.%m %H:%M"}{/if}</span>
                        </span>
                        <span class="admin-chat-inbox__item-body">
                            <p>{if $chatConversation.preview|default:'' ne ''}{$chatConversation.preview|truncate:65:"..."|escape:'html'}{elseif $chatConversation.type|default:'live_chat' eq 'live_chat'}{$t.support|default:'Support'}{else}{$chatConversation.subtitle|default:'Messenger'|escape:'html'}{/if}</p>
                            <span class="admin-chat-inbox__meta">
                                <span>{if $chatConversation.type|default:'live_chat' eq 'live_chat'}Support{elseif $chatConversation.is_direct|default:false}1 na 1{else}Grupa{/if}</span>
                                {if $chatConversation.unread_count|default:0 gt 0}
                                <span class="admin-chat-inbox__unread">Nowe: {$chatConversation.unread_count}</span>
                                {/if}
                            </span>
                        </span>
                    </span>
                </button>
                {/foreach}
            </div>
        </div>

        <div class="admin-chat-inbox__conversation-view messenger-inbox__conversation-view" data-chat-conversation-view hidden>
            <div class="admin-chat-inbox__conversation-header messenger-inbox__conversation-header">
                <button type="button" class="admin-chat-inbox__back" data-chat-back>
                    <i class="fa fa-arrow-left" aria-hidden="true"></i>
                </button>
                <div class="admin-chat-inbox__conversation-title-wrap">
                    <span class="messenger-avatar-stack messenger-avatar-stack--header">
                        <span class="messenger-conversation-meta__avatar {$chat_active_conversation_avatar_theme|default:'theme-6'}">
                            {if $chat_active_conversation_avatar_url|default:'' ne ''}
                            <img src="{$chat_active_conversation_avatar_url|escape:'html'}" alt="{$chat_active_conversation_title|default:$t.support|default:'Support'|escape:'html'}" />
                            {else}
                            {$chat_active_conversation_avatar_text|default:'S'|escape:'html'}
                            {/if}
                        </span>
                        <span class="{$chat_active_conversation_presence_class_name|default:'admin-chat-presence admin-chat-presence--offline'} messenger-avatar-stack__presence" title="{$chat_active_conversation_presence_label|default:'Offline'|escape:'html'}" aria-label="{$chat_active_conversation_presence_label|default:'Offline'|escape:'html'}"></span>
                    </span>
                    <strong>{$chat_active_conversation_title|default:$t.support|default:'Support'}</strong>
                </div>
            </div>
            <div class="messenger-conversations messenger-conversations--quick-switch">
                {foreach from=$chat_conversations item=chatConversation}
                <button
                    type="button"
                    class="messenger-conversation-chip{if $chatConversation.is_owned|default:false} is-owned-group{/if}{if $chatConversation.id == $chat_active_conversation_id && $chatConversation.type == $chat_active_conversation_type} is-active{/if}"
                    data-chat-conversation-tab
                    data-conversation-id="{$chatConversation.id|default:0}"
                    data-conversation-type="{$chatConversation.type|default:'live_chat'}"
                    title="{$chatConversation.title|escape:'html'}"
                >
                    {if $chatConversation.avatar_url|default:'' ne '' || $chatConversation.avatar_text|default:'' ne ''}
                    <span class="messenger-avatar-stack messenger-avatar-stack--chip">
                        <span class="messenger-conversation-chip__avatar {$chatConversation.avatar_theme|default:'theme-6'}">
                            {if $chatConversation.avatar_url|default:'' ne ''}
                            <img src="{$chatConversation.avatar_url|escape:'html'}" alt="{$chatConversation.title|escape:'html'}" />
                            {else}
                            {$chatConversation.avatar_text|default:'S'|escape:'html'}
                            {/if}
                        </span>
                        <span class="{if isset($chatConversation.presence.class_name)}{$chatConversation.presence.class_name|escape:'html'}{else}admin-chat-presence admin-chat-presence--offline{/if} messenger-avatar-stack__presence" title="{if isset($chatConversation.presence.label)}{$chatConversation.presence.label|escape:'html'}{else}Offline{/if}" aria-label="{if isset($chatConversation.presence.label)}{$chatConversation.presence.label|escape:'html'}{else}Offline{/if}"></span>
                    </span>
                    {/if}
                    {if $chatConversation.unread_count|default:0 gt 0}
                    <strong>{$chatConversation.unread_count}</strong>
                    {/if}
                </button>
                {/foreach}
            </div>
            <div class="admin-chat-inbox__conversation-body messenger-inbox__conversation-body" data-chat-conversation-body>
                <div class="messenger-conversation-transition" data-chat-conversation-transition hidden aria-live="polite" aria-busy="true">
                    <span class="messenger-conversation-transition__pulse" aria-hidden="true"></span>
                </div>
                <div class="messenger-conversation-stage" data-chat-conversation-stage>
        {/if}
        {if $chat_active_conversation_is_group|default:false}
        <div class="messenger-conversation-meta">
            <div class="messenger-conversation-meta__main">
                <span class="messenger-conversation-meta__avatar {$chat_active_conversation_avatar_theme|default:'theme-6'}">
                    {if $chat_active_conversation_avatar_url|default:'' ne ''}
                    <img src="{$chat_active_conversation_avatar_url|escape:'html'}" alt="{$chat_active_conversation_title|default:'Group chat'|escape:'html'}" />
                    {else}
                    {$chat_active_conversation_avatar_text|default:'G'|escape:'html'}
                    {/if}
                </span>
                <div class="messenger-conversation-meta__copy">
                    <strong>{$chat_active_conversation_title|default:'Group chat'}</strong>
                    {if $chat_active_conversation_is_read_only|default:false}
                    <span>{$t.group_chat_read_only|default:'Read only: only admins can write in this group.'}</span>
                    {else}
                    <div class="messenger-conversation-members">
                        <button type="button" class="messenger-conversation-members__toggle" data-chat-group-members-toggle aria-expanded="false">
                            {$chat_active_conversation_member_count_label|default:'0 Members'}
                        </button>
                        <div class="messenger-conversation-members__popover" data-chat-group-members-popover>
                            {if isset($chat_active_conversation_member_emails) && $chat_active_conversation_member_emails|@count gt 0}
                                {foreach from=$chat_active_conversation_member_emails item=chatGroupMemberEmail}
                                <span class="messenger-conversation-members__badge">{$chatGroupMemberEmail|escape:'html'}</span>
                                {/foreach}
                            {else}
                            <span class="messenger-conversation-members__badge">Brak danych</span>
                            {/if}
                        </div>
                    </div>
                    {/if}
                </div>
            </div>
            {if $chat_active_conversation_can_leave|default:false || $chat_active_conversation_can_manage|default:false}
            <div class="messenger-conversation-meta__actions">
                <button type="button" class="messenger-conversation-meta__menu-button" data-chat-group-settings-toggle aria-expanded="false" aria-label="{$t.settings|default:'Ustawienia'}">
                    <i class="fa fa-sliders" aria-hidden="true"></i>
                </button>
                <div class="messenger-conversation-meta__menu messenger-conversation-meta__menu--settings" data-chat-group-settings-menu>
                    <label class="messenger-conversation-meta__setting-toggle">
                        <input type="checkbox" data-chat-email-notifications-toggle {if $chat_active_member_email_notifications_enabled|default:true}checked{/if}>
                        <span>{$t.group_chat_email_notifications|default:'Powiadomienia email dla tej rozmowy'}</span>
                    </label>
                    {if $chat_active_conversation_can_manage|default:false}
                    <label class="messenger-conversation-meta__setting-field">
                        <span>{$t.group_chat_retention_label|default:'Auto-usuwanie wiadomości'}</span>
                        <select class="form-control input-sm" data-chat-retention-select>
                            <option value="0"{if $chat_active_conversation_retention_hours|default:'' eq ''} selected{/if}>{$t.group_chat_retention_off|default:'Wyłączone'}</option>
                            <option value="1"{if $chat_active_conversation_retention_hours|default:'' eq 1} selected{/if}>1h</option>
                            <option value="6"{if $chat_active_conversation_retention_hours|default:'' eq 6} selected{/if}>6h</option>
                            <option value="12"{if $chat_active_conversation_retention_hours|default:'' eq 12} selected{/if}>12h</option>
                            <option value="24"{if $chat_active_conversation_retention_hours|default:'' eq 24} selected{/if}>24h</option>
                        </select>
                    </label>
                    {/if}
                </div>
                <button type="button" class="messenger-conversation-meta__menu-button" data-chat-group-menu-toggle aria-expanded="false" aria-label="{$t.group_chat_actions|default:'Group actions'}">
                    <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
                </button>
                <div class="messenger-conversation-meta__menu" data-chat-group-menu>
                    {if $chat_active_conversation_can_manage|default:false}
                    <button type="button" class="messenger-conversation-meta__menu-item" data-messenger-group-open data-group-mode="invite" data-conversation-id="{$chat_active_conversation_id|default:0}" data-group-title="{$chat_active_conversation_title|default:'Group chat'|escape:'html'}">
                        {$t.group_chat_add_member|default:'Add member'}
                    </button>
                    {/if}
                    {if $chat_active_conversation_can_leave|default:false}
                    <button type="button" class="messenger-conversation-meta__menu-item messenger-conversation-meta__menu-item--danger" data-chat-leave-group data-conversation-id="{$chat_active_conversation_id|default:0}">
                        {$t.group_chat_leave|default:'Leave'}
                    </button>
                    {/if}
                    {if $chat_active_conversation_can_manage|default:false}
                    <button type="button" class="messenger-conversation-meta__menu-item messenger-conversation-meta__menu-item--danger" data-chat-delete-group data-conversation-id="{$chat_active_conversation_id|default:0}">
                        {$t.group_chat_remove|default:'Remove'}
                    </button>
                    {/if}
                </div>
            </div>
            {/if}
        </div>
        {/if}
    	<div class="messages" id="chat_scroll">
			<ul class="messenger-list">
                {if !$chat_active_conversation_is_group|default:false}
				<li class="messenger-item messenger-item--received messenger-item--intro">
					<div class="messenger-bubble">
						<div class="messenger-author">{$chat_support_label|default:'Support'}</div>
						<div class="messenger-text">{$t.chat_intro_message|default:'Would you like to ask something or do you need help with your subscription?'}</div>
					</div>
					{if isset($chat_faq_prompts) && $chat_faq_prompts|@count gt 0}
					<div class="messenger-faq-panel">
						<div class="messenger-faq-label">{$t.chat_quick_questions_label|default:'Quick questions'}</div>
						<div class="messenger-faq-prompts" data-chat-faq-list>
							{foreach from=$chat_faq_prompts item=faqPrompt}
							<button
								type="button"
								class="messenger-faq-prompt"
								data-chat-faq-key="{$faqPrompt.faq_key|escape:'html'}"
								onclick="return chatFaqPrompt('{$faqPrompt.faq_key|escape:'javascript'}');"
							>
								{$faqPrompt.title nofilter}
							</button>
							{/foreach}
						</div>
					</div>
					{/if}
                </li>
                {/if}
                {if $chat_active_conversation_type|default:'live_chat' ne 'live_chat'}
                <li class="messenger-retention-hint">
                    <span>
                        {if $chat_active_conversation_retention_hours|default:'' ne ''}
                        Auto-usuwanie po {$chat_active_conversation_retention_hours}h
                        {else}
                        Auto-usuwanie wyłączone
                        {/if}
                    </span>
                </li>
                {/if}
                {section name=i loop=$chat}
                {if $chat[i].time_anchor_label ne ''}
                <li class="messenger-time-anchor">
                    <span>{$chat[i].time_anchor_label}</span>
                </li>
                {/if}
                <li class="messenger-item messenger-item--{$chat[i].direction}">
					<div class="messenger-bubble">
						{if $chat[i].direction eq 'received'}
						<div class="messenger-author">{$chat[i].sender_label}</div>
						{/if}
						{if $chat[i].attachment_path}
						<a href="{$chat[i].attachment_path}" class="messenger-image-link" target="_blank" rel="noopener noreferrer">
							<img src="{$chat[i].attachment_path}" class="messenger-image" alt="attachment" />
						</a>
						{/if}
						{if $chat[i].message_html ne ''}
						<div class="messenger-text">{$chat[i].message_html nofilter}</div>
						{/if}
					</div>
                    <div class="messenger-time-detail">{$chat[i].created_label}</div>
                    {if $chat[i].can_delete}
                    <button
                        type="button"
                        class="messenger-delete-button{if $chat[i].delete_mode|default:'' eq 'icon'} messenger-delete-button--icon{/if}"
                        data-message-id="{$chat[i].id}"
                        data-delete-until="{$chat[i].delete_until_timestamp}"
                        data-delete-label="{$t.chat_delete|default:'Delete'}"
                        title="{$t.chat_delete|default:'Delete'}"
                        aria-label="{$t.chat_delete|default:'Delete'}"
                    >
                        {if $chat[i].delete_mode|default:'' eq 'icon'}
                        <i class="fa fa-trash" aria-hidden="true"></i>
                        {else}
                        {$t.chat_delete|default:'Delete'} ({$chat[i].delete_remaining_seconds}s)
                        {/if}
                    </button>
                    {/if}
                </li>
                {/section}
			</ul>
		</div>
                </div>
        {if $user.customer_type|default:'client' eq 'reseller' && isset($chat_conversations) && $chat_conversations|@count gt 0}
            </div>
        </div>
        {/if}
	</div>
{else}
<p>Please login again...</p>
{/if}
