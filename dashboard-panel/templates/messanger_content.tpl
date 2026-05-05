{if $user.logged}
	<div id="content_chat_profil" data-chat-last-id="{$chat_last_message_id|default:0}" data-chat-oldest-id="{$chat_oldest_message_id|default:0}" data-chat-message-limit="{$chat_message_limit|default:10}" data-chat-loaded-message-count="{$chat_loaded_message_count|default:0}" data-chat-total-message-count="{$chat_total_message_count|default:0}" data-chat-has-more-messages="{if $chat_has_more_messages|default:false}1{else}0{/if}" data-chat-active-conversation-id="{$chat_active_conversation_id|default:0}" data-chat-active-conversation-type="{$chat_active_conversation_type|default:'live_chat'}" data-chat-active-conversation-title="{$chat_active_conversation_title|default:''|escape:'html'}" data-chat-active-conversation-subtitle="{$chat_active_conversation_subtitle|default:''|escape:'html'}" data-chat-active-conversation-is-direct="{if $chat_active_conversation_is_direct|default:false}1{else}0{/if}" data-chat-active-conversation-direct-status="{$chat_active_conversation_direct_status|default:'none'|escape:'html'}" data-chat-active-conversation-direct-target-customer-id="{$chat_active_conversation_direct_target_customer_id|default:0}" data-chat-active-conversation-pending-member-count="{$chat_active_conversation_pending_member_count|default:0}" data-chat-can-send="{if $chat_active_conversation_can_send|default:true}1{else}0{/if}" data-chat-can-manage-group="{if $chat_active_conversation_can_manage|default:false}1{else}0{/if}" data-chat-is-global-group="{if $chat_active_conversation_is_global_group|default:false}1{else}0{/if}" data-chat-customer-is-blocked="{if $chat_customer_is_blocked|default:false}1{else}0{/if}">
        <div class="messenger-group-invites-slot" data-group-chat-invites-slot>
            {include file='profil/group_chat_invites.tpl'}
        </div>
        {if $chat_customer_full_messenger_enabled|default:false && isset($chat_conversations) && $chat_conversations|@count gt 0}
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
                                {if $chatConversation.unread_count|default:0 gt 0}
                                <span class="messenger-inbox__type-badge messenger-inbox__type-badge--new">Nowe: {$chatConversation.unread_count}</span>
                                {elseif $chatConversation.type|default:'live_chat' eq 'live_chat'}
                                <span class="messenger-inbox__type-badge messenger-inbox__type-badge--support">{$t.chat_admin_badge|default:'Admin'}</span>
                                {elseif !($chatConversation.is_direct|default:false)}
                                <span class="messenger-inbox__type-badge messenger-inbox__type-badge--group">{$t.group_chat_badge|default:'Grupa'}</span>
                                {/if}
                            </span>
                            <span class="admin-chat-inbox__item-time">
                                {if $chatConversation.updated_at|default:'' ne ''}
                                    {if $chatConversation.updated_at|date_format:"%Y-%m-%d" eq $smarty.now|date_format:"%Y-%m-%d"}
                                        {$chatConversation.updated_at|date_format:"%H:%M"}
                                    {else}
                                        {$chatConversation.updated_at|date_format:"%d.%m"}
                                    {/if}
                                {/if}
                            </span>
                        </span>
                        <span class="admin-chat-inbox__item-body">
                            <p>{if $chatConversation.preview|default:'' ne ''}{$chatConversation.preview|truncate:65:"..."|escape:'html'}{elseif $chatConversation.has_pending_invite|default:false}{$t.group_chat_direct_pending_notice|default:'Ta rozmowa czeka na zaakceptowanie zaproszenia.'|escape:'html'}{elseif $chatConversation.type|default:'live_chat' eq 'live_chat'}{$t.support|default:'Support'}{else}{$chatConversation.subtitle|default:'Messenger'|escape:'html'}{/if}</p>
                            <span class="admin-chat-inbox__meta"></span>
                        </span>
                    </span>
                </button>
                {/foreach}
            </div>
        </div>

        <div class="admin-chat-inbox__conversation-view messenger-inbox__conversation-view" data-chat-conversation-view hidden>
            <div class="admin-chat-inbox__conversation-header messenger-inbox__conversation-header">
                <button type="button" class="admin-chat-inbox__back{if $chat_nieprzeczytane|default:0 gt 0} has-unread{/if}" data-chat-back>
                    <i class="fa fa-arrow-left admin-chat-inbox__back-icon" aria-hidden="true"></i>
                    <span class="admin-chat-inbox__back-unread messenger-unread-badge-back"{if $chat_nieprzeczytane|default:0 lte 0} style="display:none;"{/if}>{$chat_nieprzeczytane|default:0}</span>
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
                    <div class="messenger-conversation-header__copy">
                        {if ($chat_active_conversation_is_group|default:false) && ($chat_active_conversation_is_direct|default:false) && $chat_active_conversation_members|@count gt 0 && !$chat_active_conversation_is_global_group|default:false}
                        {assign var="chatProfileTitleRendered" value=false}
                        {foreach from=$chat_active_conversation_members item=chatProfileMember}
                            {if $chatProfileMember.participant_type|default:'' eq 'customer' && $chatProfileMember.customer_id|default:0 ne $user.id}
                            {assign var="chatProfileTitleRendered" value=true}
                            <button
                                type="button"
                                class="messenger-conversation-title-button messenger-conversation-title-button--handle"
                                data-chat-profile-open
                                data-chat-profile-context="direct-title"
                                data-participant-type="customer"
                                data-target-customer-id="{$chatProfileMember.customer_id|default:0}"
                                title="{$chat_active_conversation_title|default:$t.support|default:'Support'|escape:'html'}"
                            >
                                <strong>{$chat_active_conversation_title|default:$t.support|default:'Support'}</strong>
                            </button>
                            {break}
                            {/if}
                        {/foreach}
                        {if !$chatProfileTitleRendered}
                        <strong>{$chat_active_conversation_title|default:$t.support|default:'Support'}</strong>
                        {/if}
                        {else}
                        <strong>{$chat_active_conversation_title|default:$t.support|default:'Support'}</strong>
                        {/if}
                        {if $chat_customer_is_blocked|default:false}
                        <span class="messenger-inbox__type-badge messenger-inbox__type-badge--blocked">{$t.referrals_status_blocked|default:'Blocked'}</span>
                        {/if}
                        {if $chat_active_conversation_is_group|default:false}
                            {if $chat_active_conversation_is_read_only|default:false}
                            <span class="messenger-conversation-header__subline">{$t.group_chat_read_only|default:'Read only: only admins can write in this group.'}</span>
                            {elseif ($chat_active_conversation_is_direct|default:false) && ($chat_active_conversation_has_pending_invite|default:false)}
                            {* keep pending invite notice inside the chat stream instead of the header *}
                            {elseif $chat_active_conversation_is_direct|default:false}
                            {* keep the header clean for 1:1 conversations *}
                            {elseif $chat_active_conversation_is_global_group|default:false}
                            <span class="messenger-conversation-header__subline">{$chat_active_conversation_member_count_label|default:'0 active users'}</span>
                            {else}
                            <div class="messenger-conversation-members">
                                <button type="button" class="messenger-conversation-members__toggle" data-chat-group-members-toggle aria-expanded="false">
                                    {$chat_active_conversation_member_count_label|default:'0 Members'}
                                </button>
                                <div class="messenger-conversation-members__popover" data-chat-group-members-popover>
                                    {if isset($chat_active_conversation_members) && $chat_active_conversation_members|@count gt 0}
                                        {foreach from=$chat_active_conversation_members item=chatGroupMember}
                                        <button
                                            type="button"
                                            class="messenger-conversation-members__badge{if $chatGroupMember.participant_type|default:'' eq 'customer'} is-customer{/if}"
                                            {if $chatGroupMember.participant_type|default:'' eq 'customer' && $chatGroupMember.customer_id|default:0 ne $user.id}
                                            data-chat-profile-open
                                            data-chat-profile-context="group-member"
                                            data-participant-type="customer"
                                            data-target-customer-id="{$chatGroupMember.customer_id|default:0}"
                                            {/if}
                                        >
                                            {$chatGroupMember.label|default:'User'|escape:'html'}
                                        </button>
                                        {/foreach}
                                    {else}
                                    <span class="messenger-conversation-members__badge">Brak danych</span>
                                    {/if}
                                </div>
                            </div>
                            {/if}
                        {/if}
                    </div>
                </div>
                {if $chat_active_conversation_is_group|default:false && (($chat_active_conversation_can_leave|default:false) || ($chat_active_conversation_can_manage|default:false) || ($chat_active_conversation_type|default:'' eq 'group_chat'))}
                <div class="messenger-conversation-header__actions" data-chat-header-actions>
                    <button type="button" class="messenger-conversation-meta__menu-button" data-chat-group-settings-toggle aria-expanded="false" aria-label="{$t.settings|default:'Ustawienia'}">
                        <i class="fa fa-sliders" aria-hidden="true"></i>
                    </button>
                    <div class="messenger-conversation-meta__menu messenger-conversation-meta__menu--settings" data-chat-group-settings-menu>
                        {if !$chat_active_conversation_is_global_group|default:false}
                        <label class="messenger-conversation-meta__setting-row messenger-conversation-meta__setting-row--toggle">
                            <span class="messenger-conversation-meta__setting-copy">
                                <strong>{$t.group_chat_email_notifications|default:'Powiadomienia email dla tej rozmowy'}</strong>
                                <small>{$t.group_chat_email_notifications_hint|default:'Tylko nowe wiadomości w tej rozmowie.'}</small>
                            </span>
                            <span class="messenger-conversation-meta__switch">
                                <input class="settings-toggle__input" type="checkbox" data-chat-email-notifications-toggle {if $chat_active_member_email_notifications_enabled|default:true}checked{/if}>
                                <span class="settings-toggle__slider" aria-hidden="true"></span>
                            </span>
                        </label>
                        {/if}
                        {if ($chat_active_conversation_can_manage|default:false) || ($chat_active_conversation_type|default:'' eq 'group_chat')}
                        <label class="messenger-conversation-meta__setting-row messenger-conversation-meta__setting-row--select">
                            <span class="messenger-conversation-meta__setting-copy">
                                <strong>{$t.group_chat_retention_label|default:'Auto-usuwanie wiadomości'}</strong>
                                <small>{$t.group_chat_retention_hint|default:'Nowe wiadomości znikną po wybranym czasie.'}</small>
                            </span>
                            <select class="form-control input-sm" data-chat-retention-select>
                                <option value="5m"{if $chat_active_conversation_retention_input_value|default:'' eq '5m'} selected{/if}>5 min</option>
                                <option value="15m"{if $chat_active_conversation_retention_input_value|default:'' eq '15m'} selected{/if}>15 min</option>
                                <option value="30m"{if $chat_active_conversation_retention_input_value|default:'' eq '30m'} selected{/if}>30 min</option>
                                <option value="1h"{if $chat_active_conversation_retention_input_value|default:'1h' eq '1h'} selected{/if}>1h</option>
                                <option value="12h"{if $chat_active_conversation_retention_input_value|default:'' eq '12h'} selected{/if}>12h</option>
                                <option value="24h"{if $chat_active_conversation_retention_input_value|default:'' eq '24h'} selected{/if}>24h</option>
                            </select>
                        </label>
                        {/if}
                    </div>
                    <button type="button" class="messenger-conversation-meta__menu-button" data-chat-group-menu-toggle aria-expanded="false" aria-label="{$t.group_chat_actions|default:'Group actions'}">
                        <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
                    </button>
                    <div class="messenger-conversation-meta__menu" data-chat-group-menu>
                        {if ($chat_active_conversation_can_manage|default:false) && !($chat_active_conversation_is_direct|default:false) && ($chat_customer_can_create_named_groups|default:false)}
                        <button type="button" class="messenger-conversation-meta__menu-item" data-messenger-group-open data-group-mode="invite" data-conversation-id="{$chat_active_conversation_id|default:0}" data-group-title="{$chat_active_conversation_title|default:'Group chat'|escape:'html'}">
                            {$t.group_chat_add_member|default:'Add member'}
                        </button>
                        {/if}
                        {if $chat_active_conversation_can_leave|default:false}
                        <button
                            type="button"
                            class="messenger-conversation-meta__menu-item{if !($chat_active_conversation_is_direct|default:false)} messenger-conversation-meta__menu-item--danger{/if}"
                            data-chat-leave-group
                            data-conversation-id="{$chat_active_conversation_id|default:0}"
                            title="{if $chat_active_conversation_is_direct|default:false}{$t.group_chat_leave_direct_tooltip|default:'Opuść tę rozmowę tylko po swojej stronie. Druga osoba nadal będzie ją widzieć u siebie.'}{else}{$t.group_chat_leave_tooltip|default:'Opuść tę rozmowę tylko po swojej stronie. Pozostali uczestnicy nadal będą ją widzieć.'}{/if}"
                            aria-label="{if $chat_active_conversation_is_direct|default:false}{$t.group_chat_leave_direct_tooltip|default:'Opuść tę rozmowę tylko po swojej stronie. Druga osoba nadal będzie ją widzieć u siebie.'}{else}{$t.group_chat_leave_tooltip|default:'Opuść tę rozmowę tylko po swojej stronie. Pozostali uczestnicy nadal będą ją widzieć.'}{/if}"
                        >
                            {if $chat_active_conversation_is_direct|default:false}{$t.group_chat_leave_direct|default:'Opuść rozmowę'}{else}{$t.group_chat_leave|default:'Opuść grupę'}{/if}
                        </button>
                        {/if}
                        {if !$chat_active_conversation_is_global_group|default:false && (!($chat_active_conversation_is_owned|default:false) || ($chat_active_conversation_is_direct|default:false))}
                        <button
                            type="button"
                            class="messenger-conversation-meta__menu-item"
                            data-chat-remove-group
                            data-conversation-id="{$chat_active_conversation_id|default:0}"
                            title="{if $chat_active_conversation_is_direct|default:false}{$t.group_chat_remove_direct_tooltip|default:'Usuń tę rozmowę tylko ze swojego inboxu. Druga osoba nadal będzie ją widzieć u siebie.'}{else}{$t.group_chat_remove_local_tooltip|default:'Usuń tę grupę tylko ze swojego inboxu. Pozostali uczestnicy nadal będą ją widzieć.'}{/if}"
                            aria-label="{if $chat_active_conversation_is_direct|default:false}{$t.group_chat_remove_direct_tooltip|default:'Usuń tę rozmowę tylko ze swojego inboxu. Druga osoba nadal będzie ją widzieć u siebie.'}{else}{$t.group_chat_remove_local_tooltip|default:'Usuń tę grupę tylko ze swojego inboxu. Pozostali uczestnicy nadal będą ją widzieć.'}{/if}"
                        >
                            {if $chat_active_conversation_is_direct|default:false}{$t.group_chat_remove_direct|default:'Usuń rozmowę'}{else}{$t.group_chat_remove_local|default:'Usuń z inboxu'}{/if}
                        </button>
                        {/if}
                        {if ($chat_active_conversation_can_manage|default:false) && !($chat_active_conversation_is_direct|default:false)}
                        <button
                            type="button"
                            class="messenger-conversation-meta__menu-item messenger-conversation-meta__menu-item--danger"
                            data-chat-delete-group
                            data-conversation-id="{$chat_active_conversation_id|default:0}"
                            title="{$t.group_chat_remove_tooltip|default:'Usuń całą rozmowę dla wszystkich uczestników razem z jej historią.'}"
                            aria-label="{$t.group_chat_remove_tooltip|default:'Usuń całą rozmowę dla wszystkich uczestników razem z jej historią.'}"
                        >
                            {$t.group_chat_remove|default:'Usuń grupę wszystkim'}
                        </button>
                        {/if}
                    </div>
                </div>
                {/if}
            </div>
            <div class="admin-chat-inbox__conversation-body messenger-inbox__conversation-body" data-chat-conversation-body>
                <div class="messenger-conversation-transition" data-chat-conversation-transition hidden aria-live="polite" aria-busy="true">
                    <span class="messenger-conversation-transition__pulse" aria-hidden="true"></span>
                </div>
                <div class="messenger-conversation-stage" data-chat-conversation-stage>
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
                        Auto-usuwanie po {$chat_active_conversation_retention_label|default:'1h'}
                    </span>
                </li>
                {/if}
                {if ($chat_active_conversation_is_direct|default:false) && (($chat_active_conversation_has_pending_invite|default:false) || ($chat_active_conversation_direct_status|default:'' eq 'pending'))}
                <li class="messenger-retention-hint messenger-retention-hint--pending">
                    <span>{$t.group_chat_direct_pending_hint|default:'Zaproszenie oczekuje na potwierdzenie. Auto-usuwanie po 24h.'}</span>
                </li>
                {elseif ($chat_active_conversation_is_direct|default:false) && ($chat_active_conversation_direct_status|default:'' eq 'rejected')}
                <li class="messenger-retention-hint messenger-retention-hint--pending messenger-retention-hint--action">
                    <span>{$t.group_chat_direct_rejected_hint|default:'Zaproszenie zostało odrzucone. Możesz wysłać je ponownie.'}</span>
                    {if $chat_active_conversation_direct_target_customer_id|default:0 gt 0}
                    <button type="button" class="messenger-retention-hint__action" data-chat-direct-reinvite data-target-customer-id="{$chat_active_conversation_direct_target_customer_id|default:0}">
                        {$t.group_chat_reinvite_action|default:'Wyślij zaproszenie ponownie'}
                    </button>
                    {/if}
                </li>
                {/if}
                {if ($chat_active_conversation_is_direct|default:false) && (($chat_active_conversation_has_pending_invite|default:false) || ($chat_active_conversation_direct_status|default:'' eq 'pending'))}
                <li class="messenger-item messenger-item--system">
                    <div class="messenger-system-note__text">{$t.group_chat_direct_pending_notice|default:'Oczekiwanie na zatwierdzenie zaproszenia do rozmowy.'}</div>
                </li>
                {elseif ($chat_active_conversation_is_direct|default:false) && ($chat_active_conversation_direct_status|default:'' eq 'rejected')}
                <li class="messenger-item messenger-item--system">
                    <div class="messenger-system-note__text">{$t.group_chat_direct_rejected_notice|default:'Zaproszenie do rozmowy zostało odrzucone.'}</div>
                </li>
                {elseif ($chat_active_conversation_is_group|default:false) && !($chat_active_conversation_is_direct|default:false) && ($chat_active_conversation_pending_member_count|default:0 gt 0) && !($chat_active_conversation_is_global_group|default:false)}
                <li class="messenger-item messenger-item--system">
                    <div class="messenger-system-note__text">
                        {if $chat_active_conversation_pending_member_count|default:0 eq 1}
                            {$t.group_chat_group_pending_notice_single|default:'Zaproszenie zostało wysłane i czeka na potwierdzenie.'}
                        {else}
                            {$t.group_chat_group_pending_notice_many|default:'Zaproszenia zostały wysłane i czekają na potwierdzenie.'}
                        {/if}
                    </div>
                </li>
                {/if}
                {section name=i loop=$chat}
                {if $chat[i].time_anchor_label ne ''}
                <li class="messenger-time-anchor">
                    <span>{$chat[i].time_anchor_label}</span>
                </li>
                {/if}
				<li class="messenger-item messenger-item--{$chat[i].direction}{if $chat[i].is_audio_message|default:false} messenger-item--audio{/if}{if $chat[i].can_interact|default:false} messenger-item--interactive{/if}" data-message-id="{$chat[i].id}"{if $chat[i].can_interact|default:false} data-chat-message-item{/if}>
					<div class="messenger-bubble{if $chat[i].is_audio_message|default:false} messenger-bubble--audio{/if}"{if $chat[i].can_interact|default:false} data-chat-message-bubble{/if}>
						{if $chat[i].direction eq 'received'}
                            {assign var="chatCanOpenAuthorProfile" value=false}
                            {if ($chat_active_conversation_is_group|default:false) && !($chat_active_conversation_is_direct|default:false) && !($chat[i].sender_is_admin|default:false) && ($chat[i].sender_customer_id|default:0 gt 0) && ($chat[i].sender_customer_id|default:0 ne $user.id)}
                                {assign var="chatCanOpenAuthorProfile" value=true}
                            {/if}
                            {if $chatCanOpenAuthorProfile}
                            <button
                                type="button"
                                class="messenger-author messenger-author-button"
                                data-chat-profile-open
                                data-chat-profile-context="message-author"
                                data-participant-type="customer"
                                data-target-customer-id="{$chat[i].sender_customer_id|default:0}"
                            >
                                {$chat[i].sender_label}
                            </button>
                            {else}
						    <div class="messenger-author{if $chat_active_conversation_is_group|default:false && !($chat_active_conversation_is_direct|default:false) && $chat[i].sender_is_admin|default:false} messenger-author--admin{/if}">{if $chat_active_conversation_is_group|default:false && !($chat_active_conversation_is_direct|default:false) && $chat[i].sender_is_admin|default:false}<span class="messenger-author__admin-badge" aria-hidden="true">★</span>{/if}{$chat[i].sender_label}</div>
                            {/if}
						{/if}
						{if $chat[i].reply_to_message_id|default:0 gt 0 && $chat[i].reply_preview_text|default:'' ne ''}
						<button type="button" class="messenger-reply-preview" data-chat-scroll-to-message="{$chat[i].reply_to_message_id}">
							<span class="messenger-reply-preview__sender">{$chat[i].reply_preview_sender|default:$t.chat_reply_unknown|default:'Wiadomość'|escape:'html'}</span>
							<span class="messenger-reply-preview__text">{$chat[i].reply_preview_text|escape:'html'}</span>
						</button>
						{/if}
						{if $chat[i].attachment_path}
						<a href="{$chat[i].attachment_path}" class="messenger-image-link" target="_blank" rel="noopener noreferrer">
							<img src="{$chat[i].attachment_path}" class="messenger-image" alt="attachment" />
						</a>
						{/if}
						{if $chat[i].is_audio_message|default:false && $chat[i].audio_path|default:'' ne ''}
						<div class="messenger-audio">
							<audio controls preload="metadata" class="messenger-audio__player" data-chat-audio-player>
								<source src="{if $chat[i].audio_stream_url|default:'' ne ''}{$chat[i].audio_stream_url|escape:'html'}{else}{$chat[i].audio_path|escape:'html'}{/if}"{if $chat[i].audio_mime_type|default:'' ne ''} type="{$chat[i].audio_mime_type|escape:'html'}"{/if}>
							</audio>
							{if $chat[i].audio_duration_label|default:'' ne ''}
							<span class="messenger-audio__duration">{$chat[i].audio_duration_label|escape:'html'}</span>
							{/if}
						</div>
						{elseif $chat[i].is_audio_expired|default:false}
						<div class="messenger-audio__expired">{$t.chat_voice_message_expired|default:'Wiadomość głosowa wygasła.'}</div>
						{/if}
						{if $chat[i].message_html ne ''}
						<div class="messenger-text{if $chat[i].is_emoji_only|default:false} messenger-text--emoji-only{/if}">{$chat[i].message_html nofilter}</div>
						{/if}
                        {if $chat[i].reactions|@count gt 0}
                        <div class="messenger-reactions">
                            {foreach from=$chat[i].reactions item=chatReaction}
                            <button
                                type="button"
                                class="messenger-reaction{if $chatReaction.is_selected|default:false} is-selected{/if}"
                                data-chat-reaction-toggle="{$chatReaction.code|escape:'html'}"
                                data-message-id="{$chat[i].id}">
                                <span class="messenger-reaction__emoji">{$chatReaction.emoji|escape:'html'}</span>
                                {if $chatReaction.count|default:0 gt 1}
                                <span class="messenger-reaction__count">{$chatReaction.count|default:0}</span>
                                {/if}
                            </button>
                            {/foreach}
                        </div>
                        {/if}
                        {if $chat[i].can_interact|default:false}
                        <div class="messenger-message-actions" data-chat-message-actions>
                            <button type="button" class="messenger-message-actions__button" data-chat-reply-open data-message-id="{$chat[i].id}" data-reply-sender="{$chat[i].sender_label|escape:'html'}" data-reply-text="{if $chat[i].message_html ne ''}{$chat[i].message_html|strip_tags|truncate:90:'...'|escape:'html'}{elseif $chat[i].is_audio_message|default:false}{$t.chat_voice_message_label|default:'Wiadomość głosowa'|escape:'html'}{elseif $chat[i].is_audio_expired|default:false}{$t.chat_voice_message_expired|default:'Wiadomość głosowa wygasła.'|escape:'html'}{elseif $chat[i].attachment_path ne ''}{$t.chat_attachment|default:'Załącznik'|escape:'html'}{else}{$t.chat_reply_unknown|default:'Wiadomość'|escape:'html'}{/if}">
                                <i class="fa fa-reply" aria-hidden="true"></i>
                            </button>
                            {foreach from=['thumbs_up'=>'👍','heart'=>'❤️','joy'=>'😂','wow'=>'😮','sad'=>'😢'] key=chatReactionCode item=chatReactionEmoji}
                            <button type="button" class="messenger-message-actions__button messenger-message-actions__button--emoji" data-chat-reaction-toggle="{$chatReactionCode}" data-message-id="{$chat[i].id}">
                                {$chatReactionEmoji}
                            </button>
                            {/foreach}
                        </div>
                        {/if}
                        {if $chat[i].direction eq 'sent' && ($chat[i].is_read_receipt|default:false || $chat[i].can_delete)}
                        <div class="messenger-bubble__meta">
                            <span
                                class="admin-chat-read-receipt{if $chat[i].is_read_receipt|default:false} is-read{else} is-pending{/if}"
                                title="{if $chat[i].is_read_receipt|default:false}{$t.chat_read_receipt_read|default:'Przeczytano'}{else}{$t.chat_read_receipt_sent|default:'Wysłano'}{/if}"
                                aria-label="{if $chat[i].is_read_receipt|default:false}{$t.chat_read_receipt_read|default:'Przeczytano'}{else}{$t.chat_read_receipt_sent|default:'Wysłano'}{/if}"
                            >
                                {if $chat[i].is_read_receipt|default:false}
                                    <i class="bi bi-check2-all" aria-hidden="true"></i>
                                {else}
                                    <i class="fa fa-check" aria-hidden="true"></i>
                                {/if}
                            </span>
                            {if $chat[i].can_delete}
                            <button
                                type="button"
                                class="messenger-delete-button messenger-delete-button--icon"
                                data-message-id="{$chat[i].id}"
                                data-delete-until="{$chat[i].delete_until_timestamp}"
                                data-delete-label="{$t.chat_delete|default:'Delete'}"
                                title="{$t.chat_delete|default:'Delete'}"
                                aria-label="{$t.chat_delete|default:'Delete'}"
                            >
                                <i class="fa fa-trash" aria-hidden="true"></i>
                            </button>
                            {/if}
                        </div>
                        {/if}
					</div>
                    <div class="messenger-time-detail">{$chat[i].created_label}</div>
				</li>
                {/section}
			</ul>
            <div class="messenger-profile-modal" id="messenger_profile_modal" aria-hidden="true">
                <div class="messenger-profile-modal__backdrop" data-chat-profile-close></div>
                <div class="messenger-profile-modal__dialog">
                    <div class="messenger-profile-modal__card">
                        <button type="button" class="messenger-profile-modal__close" data-chat-profile-close aria-label="{$t.close|default:'Close'}">
                            <i class="fa fa-times" aria-hidden="true"></i>
                        </button>
                        <div class="messenger-profile-modal__avatar" id="messenger_profile_avatar"></div>
                        <div class="messenger-profile-modal__handle" id="messenger_profile_handle">@user</div>
                        <div class="messenger-profile-modal__last-seen" id="messenger_profile_last_seen"></div>
                        <div class="messenger-profile-modal__note" id="messenger_profile_note" style="display:none;"></div>
                        <div class="messenger-profile-modal__actions" id="messenger_profile_actions">
                            <button type="button" class="btn btn-default messenger-profile-modal__action messenger-profile-modal__action--secondary" id="messenger_profile_action_secondary" data-target-customer-id="0" data-action-kind="">{$t.group_chat_invite_reject|default:'Reject'}</button>
                            <button type="button" class="btn btn-dark messenger-profile-modal__action" id="messenger_profile_action" data-target-customer-id="0" data-action-kind="invite">{$t.group_chat_direct_submit|default:'Start conversation'}</button>
                        </div>
                    </div>
                </div>
            </div>
		</div>
                </div>
        {if $chat_customer_full_messenger_enabled|default:false && isset($chat_conversations) && $chat_conversations|@count gt 0}
            </div>
        </div>
        {/if}
	</div>
{else}
<p>Please login again...</p>
{/if}
