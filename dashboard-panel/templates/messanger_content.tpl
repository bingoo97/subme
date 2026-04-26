{if $user.logged}
	<div id="content_chat_profil" data-chat-last-id="{$chat_last_message_id|default:0}" data-chat-active-conversation-id="{$chat_active_conversation_id|default:0}" data-chat-active-conversation-type="{$chat_active_conversation_type|default:'live_chat'}" data-chat-active-conversation-title="{$chat_active_conversation_title|default:''|escape:'html'}" data-chat-can-send="{if $chat_active_conversation_can_send|default:true}1{else}0{/if}" data-chat-can-manage-group="{if $chat_active_conversation_can_manage|default:false}1{else}0{/if}">
        {if isset($chat_conversations) && $chat_conversations|@count gt 0}
        <div class="messenger-conversations">
            {foreach from=$chat_conversations item=chatConversation}
            <button
                type="button"
                class="messenger-conversation-chip{if $chatConversation.is_owned|default:false} is-owned-group{/if}{if $chatConversation.id == $chat_active_conversation_id && $chatConversation.type == $chat_active_conversation_type} is-active{/if}"
                data-chat-conversation-tab
                data-conversation-id="{$chatConversation.id|default:0}"
                data-conversation-type="{$chatConversation.type|default:'live_chat'}"
                title="{$chatConversation.title|escape:'html'}"
            >
                <span>{$chatConversation.title|truncate:13:"..."|escape:'html'}</span>
                {if $chatConversation.unread_count|default:0 gt 0}
                <strong>{$chatConversation.unread_count}</strong>
                {/if}
            </button>
            {/foreach}
        </div>
        {/if}
        {if $chat_active_conversation_is_group|default:false}
        <div class="messenger-conversation-meta">
            <div class="messenger-conversation-meta__main">
                <strong>{$chat_active_conversation_title|default:'Group chat'}</strong>
                {if $chat_active_conversation_is_read_only|default:false}
                <span>{$t.group_chat_read_only|default:'Read only: only admins can write in this group.'}</span>
                {else}
                <span>{$t.group_chat_active_label|default:'Group conversation'}</span>
                {/if}
            </div>
            {if $chat_active_conversation_can_leave|default:false || $chat_active_conversation_can_manage|default:false}
            <div class="messenger-conversation-meta__actions">
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
                        class="messenger-delete-button"
                        data-message-id="{$chat[i].id}"
                        data-delete-until="{$chat[i].delete_until_timestamp}"
                        data-delete-label="{$t.chat_delete|default:'Delete'}"
                    >
                        {$t.chat_delete|default:'Delete'} ({$chat[i].delete_remaining_seconds}s)
                    </button>
                    {/if}
                </li>
                {/section}
			</ul>
		</div>
	</div>
{else}
<p>Please login again...</p>
{/if}
