<?php
/* Smarty version 5.8.0, created on 2026-04-27 07:54:38
  from 'file:/var/www/html/dashboard-panel/templates/messanger_content.tpl' */

/* @var \Smarty\Template $_smarty_tpl */
if ($_smarty_tpl->getCompiled()->isFresh($_smarty_tpl, array (
  'version' => '5.8.0',
  'unifunc' => 'content_69eefa1e0f9367_38121243',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '4abfda002b98b114730a909437c9e289e6daae0d' => 
    array (
      0 => '/var/www/html/dashboard-panel/templates/messanger_content.tpl',
      1 => 1777268964,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
))) {
function content_69eefa1e0f9367_38121243 (\Smarty\Template $_smarty_tpl) {
$_smarty_current_dir = '/var/www/html/dashboard-panel/templates';
if ($_smarty_tpl->getValue('user')['logged']) {?>
	<div id="content_chat_profil" data-chat-last-id="<?php echo (($tmp = $_smarty_tpl->getValue('chat_last_message_id') ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
" data-chat-oldest-id="<?php echo (($tmp = $_smarty_tpl->getValue('chat_oldest_message_id') ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
" data-chat-message-limit="<?php echo (($tmp = $_smarty_tpl->getValue('chat_message_limit') ?? null)===null||$tmp==='' ? 10 ?? null : $tmp);?>
" data-chat-loaded-message-count="<?php echo (($tmp = $_smarty_tpl->getValue('chat_loaded_message_count') ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
" data-chat-total-message-count="<?php echo (($tmp = $_smarty_tpl->getValue('chat_total_message_count') ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
" data-chat-has-more-messages="<?php if ((($tmp = $_smarty_tpl->getValue('chat_has_more_messages') ?? null)===null||$tmp==='' ? false ?? null : $tmp)) {?>1<?php } else { ?>0<?php }?>" data-chat-active-conversation-id="<?php echo (($tmp = $_smarty_tpl->getValue('chat_active_conversation_id') ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
" data-chat-active-conversation-type="<?php echo (($tmp = $_smarty_tpl->getValue('chat_active_conversation_type') ?? null)===null||$tmp==='' ? 'live_chat' ?? null : $tmp);?>
" data-chat-active-conversation-title="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('chat_active_conversation_title') ?? null)===null||$tmp==='' ? '' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" data-chat-can-send="<?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_can_send') ?? null)===null||$tmp==='' ? true ?? null : $tmp)) {?>1<?php } else { ?>0<?php }?>" data-chat-can-manage-group="<?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_can_manage') ?? null)===null||$tmp==='' ? false ?? null : $tmp)) {?>1<?php } else { ?>0<?php }?>">
        <?php if ((($tmp = $_smarty_tpl->getValue('user')['customer_type'] ?? null)===null||$tmp==='' ? 'client' ?? null : $tmp) == 'reseller' && (true && ($_smarty_tpl->hasVariable('chat_conversations') && null !== ($_smarty_tpl->getValue('chat_conversations') ?? null))) && $_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('chat_conversations')) > 0) {?>
        <div class="admin-chat-inbox__list-view messenger-inbox__list-view" data-chat-list-view>
            <div class="admin-chat-inbox__list">
                <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('chat_conversations'), 'chatConversation');
$foreach0DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('chatConversation')->value) {
$foreach0DoElse = false;
?>
                <button
                    type="button"
                    class="admin-chat-inbox__item messenger-inbox__item<?php if ($_smarty_tpl->getValue('chatConversation')['id'] == $_smarty_tpl->getValue('chat_active_conversation_id') && $_smarty_tpl->getValue('chatConversation')['type'] == $_smarty_tpl->getValue('chat_active_conversation_type')) {?> is-active<?php }
if ((($tmp = $_smarty_tpl->getValue('chatConversation')['unread_count'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp) > 0) {?> is-unread<?php }?>"
                    data-chat-conversation-tab
                    data-conversation-id="<?php echo (($tmp = $_smarty_tpl->getValue('chatConversation')['id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
"
                    data-conversation-type="<?php echo (($tmp = $_smarty_tpl->getValue('chatConversation')['type'] ?? null)===null||$tmp==='' ? 'live_chat' ?? null : $tmp);?>
"
                    title="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('chatConversation')['title'], ENT_QUOTES, 'UTF-8', true);?>
"
                >
                    <span class="messenger-avatar-stack messenger-avatar-stack--list">
                        <span class="admin-chat-inbox__avatar <?php echo (($tmp = $_smarty_tpl->getValue('chatConversation')['avatar_theme'] ?? null)===null||$tmp==='' ? 'theme-6' ?? null : $tmp);?>
">
                            <?php if ((($tmp = $_smarty_tpl->getValue('chatConversation')['avatar_url'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '') {?>
                            <img src="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('chatConversation')['avatar_url'], ENT_QUOTES, 'UTF-8', true);?>
" alt="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('chatConversation')['title'], ENT_QUOTES, 'UTF-8', true);?>
" />
                            <?php } else { ?>
                            <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('chatConversation')['avatar_text'] ?? null)===null||$tmp==='' ? 'S' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

                            <?php }?>
                        </span>
                        <span class="<?php if ((true && (true && null !== ($_smarty_tpl->getValue('chatConversation')['presence']['class_name'] ?? null)))) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('chatConversation')['presence']['class_name'], ENT_QUOTES, 'UTF-8', true);
} else { ?>admin-chat-presence admin-chat-presence--offline<?php }?> messenger-avatar-stack__presence" title="<?php if ((true && (true && null !== ($_smarty_tpl->getValue('chatConversation')['presence']['label'] ?? null)))) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('chatConversation')['presence']['label'], ENT_QUOTES, 'UTF-8', true);
} else { ?>Offline<?php }?>" aria-label="<?php if ((true && (true && null !== ($_smarty_tpl->getValue('chatConversation')['presence']['label'] ?? null)))) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('chatConversation')['presence']['label'], ENT_QUOTES, 'UTF-8', true);
} else { ?>Offline<?php }?>"></span>
                    </span>
                    <span class="admin-chat-inbox__item-content">
                        <span class="admin-chat-inbox__item-head">
                            <span class="admin-chat-inbox__item-title">
                                <strong><?php echo htmlspecialchars((string)$_smarty_tpl->getSmarty()->getModifierCallback('truncate')($_smarty_tpl->getValue('chatConversation')['title'],26,"..."), ENT_QUOTES, 'UTF-8', true);?>
</strong>
                            </span>
                            <span><?php if ((($tmp = $_smarty_tpl->getValue('chatConversation')['updated_at'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '') {
echo $_smarty_tpl->getSmarty()->getModifierCallback('date_format')($_smarty_tpl->getValue('chatConversation')['updated_at'],"%d.%m %H:%M");
}?></span>
                        </span>
                        <span class="admin-chat-inbox__item-body">
                            <p><?php if ((($tmp = $_smarty_tpl->getValue('chatConversation')['preview'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '') {
echo htmlspecialchars((string)$_smarty_tpl->getSmarty()->getModifierCallback('truncate')($_smarty_tpl->getValue('chatConversation')['preview'],65,"..."), ENT_QUOTES, 'UTF-8', true);
} elseif ((($tmp = $_smarty_tpl->getValue('chatConversation')['type'] ?? null)===null||$tmp==='' ? 'live_chat' ?? null : $tmp) == 'live_chat') {
echo (($tmp = $_smarty_tpl->getValue('t')['support'] ?? null)===null||$tmp==='' ? 'Support' ?? null : $tmp);
} else {
echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('chatConversation')['subtitle'] ?? null)===null||$tmp==='' ? 'Messenger' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);
}?></p>
                            <span class="admin-chat-inbox__meta">
                                <span><?php if ((($tmp = $_smarty_tpl->getValue('chatConversation')['type'] ?? null)===null||$tmp==='' ? 'live_chat' ?? null : $tmp) == 'live_chat') {?>Support<?php } elseif ((($tmp = $_smarty_tpl->getValue('chatConversation')['is_direct'] ?? null)===null||$tmp==='' ? false ?? null : $tmp)) {?>1 na 1<?php } else { ?>Grupa<?php }?></span>
                                <?php if ((($tmp = $_smarty_tpl->getValue('chatConversation')['unread_count'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp) > 0) {?>
                                <span class="admin-chat-inbox__unread">Nowe: <?php echo $_smarty_tpl->getValue('chatConversation')['unread_count'];?>
</span>
                                <?php }?>
                            </span>
                        </span>
                    </span>
                </button>
                <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
            </div>
        </div>

        <div class="admin-chat-inbox__conversation-view messenger-inbox__conversation-view" data-chat-conversation-view hidden>
            <div class="admin-chat-inbox__conversation-header messenger-inbox__conversation-header">
                <button type="button" class="admin-chat-inbox__back" data-chat-back>
                    <i class="fa fa-arrow-left" aria-hidden="true"></i>
                </button>
                <div class="admin-chat-inbox__conversation-title-wrap">
                    <span class="messenger-avatar-stack messenger-avatar-stack--header">
                        <span class="messenger-conversation-meta__avatar <?php echo (($tmp = $_smarty_tpl->getValue('chat_active_conversation_avatar_theme') ?? null)===null||$tmp==='' ? 'theme-6' ?? null : $tmp);?>
">
                            <?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_avatar_url') ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '') {?>
                            <img src="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('chat_active_conversation_avatar_url'), ENT_QUOTES, 'UTF-8', true);?>
" alt="<?php echo htmlspecialchars((string)(($tmp = (($tmp = $_smarty_tpl->getValue('chat_active_conversation_title') ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('t')['support'] ?? null : $tmp) ?? null)===null||$tmp==='' ? 'Support' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" />
                            <?php } else { ?>
                            <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('chat_active_conversation_avatar_text') ?? null)===null||$tmp==='' ? 'S' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

                            <?php }?>
                        </span>
                        <span class="<?php echo (($tmp = $_smarty_tpl->getValue('chat_active_conversation_presence_class_name') ?? null)===null||$tmp==='' ? 'admin-chat-presence admin-chat-presence--offline' ?? null : $tmp);?>
 messenger-avatar-stack__presence" title="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('chat_active_conversation_presence_label') ?? null)===null||$tmp==='' ? 'Offline' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" aria-label="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('chat_active_conversation_presence_label') ?? null)===null||$tmp==='' ? 'Offline' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
"></span>
                    </span>
                    <strong><?php echo (($tmp = (($tmp = $_smarty_tpl->getValue('chat_active_conversation_title') ?? null)===null||$tmp==='' ? $_smarty_tpl->getValue('t')['support'] ?? null : $tmp) ?? null)===null||$tmp==='' ? 'Support' ?? null : $tmp);?>
</strong>
                </div>
            </div>
            <div class="messenger-conversations messenger-conversations--quick-switch">
                <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('chat_conversations'), 'chatConversation');
$foreach1DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('chatConversation')->value) {
$foreach1DoElse = false;
?>
                <button
                    type="button"
                    class="messenger-conversation-chip<?php if ((($tmp = $_smarty_tpl->getValue('chatConversation')['is_owned'] ?? null)===null||$tmp==='' ? false ?? null : $tmp)) {?> is-owned-group<?php }
if ($_smarty_tpl->getValue('chatConversation')['id'] == $_smarty_tpl->getValue('chat_active_conversation_id') && $_smarty_tpl->getValue('chatConversation')['type'] == $_smarty_tpl->getValue('chat_active_conversation_type')) {?> is-active<?php }?>"
                    data-chat-conversation-tab
                    data-conversation-id="<?php echo (($tmp = $_smarty_tpl->getValue('chatConversation')['id'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
"
                    data-conversation-type="<?php echo (($tmp = $_smarty_tpl->getValue('chatConversation')['type'] ?? null)===null||$tmp==='' ? 'live_chat' ?? null : $tmp);?>
"
                    title="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('chatConversation')['title'], ENT_QUOTES, 'UTF-8', true);?>
"
                >
                    <?php if ((($tmp = $_smarty_tpl->getValue('chatConversation')['avatar_url'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '' || (($tmp = $_smarty_tpl->getValue('chatConversation')['avatar_text'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '') {?>
                    <span class="messenger-avatar-stack messenger-avatar-stack--chip">
                        <span class="messenger-conversation-chip__avatar <?php echo (($tmp = $_smarty_tpl->getValue('chatConversation')['avatar_theme'] ?? null)===null||$tmp==='' ? 'theme-6' ?? null : $tmp);?>
">
                            <?php if ((($tmp = $_smarty_tpl->getValue('chatConversation')['avatar_url'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '') {?>
                            <img src="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('chatConversation')['avatar_url'], ENT_QUOTES, 'UTF-8', true);?>
" alt="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('chatConversation')['title'], ENT_QUOTES, 'UTF-8', true);?>
" />
                            <?php } else { ?>
                            <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('chatConversation')['avatar_text'] ?? null)===null||$tmp==='' ? 'S' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

                            <?php }?>
                        </span>
                        <span class="<?php if ((true && (true && null !== ($_smarty_tpl->getValue('chatConversation')['presence']['class_name'] ?? null)))) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('chatConversation')['presence']['class_name'], ENT_QUOTES, 'UTF-8', true);
} else { ?>admin-chat-presence admin-chat-presence--offline<?php }?> messenger-avatar-stack__presence" title="<?php if ((true && (true && null !== ($_smarty_tpl->getValue('chatConversation')['presence']['label'] ?? null)))) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('chatConversation')['presence']['label'], ENT_QUOTES, 'UTF-8', true);
} else { ?>Offline<?php }?>" aria-label="<?php if ((true && (true && null !== ($_smarty_tpl->getValue('chatConversation')['presence']['label'] ?? null)))) {
echo htmlspecialchars((string)$_smarty_tpl->getValue('chatConversation')['presence']['label'], ENT_QUOTES, 'UTF-8', true);
} else { ?>Offline<?php }?>"></span>
                    </span>
                    <?php }?>
                    <span class="messenger-conversation-chip__title"><?php echo htmlspecialchars((string)$_smarty_tpl->getSmarty()->getModifierCallback('truncate')($_smarty_tpl->getValue('chatConversation')['title'],13,"..."), ENT_QUOTES, 'UTF-8', true);?>
</span>
                    <?php if ((($tmp = $_smarty_tpl->getValue('chatConversation')['unread_count'] ?? null)===null||$tmp==='' ? 0 ?? null : $tmp) > 0) {?>
                    <strong><?php echo $_smarty_tpl->getValue('chatConversation')['unread_count'];?>
</strong>
                    <?php }?>
                </button>
                <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
            </div>
            <div class="admin-chat-inbox__conversation-body messenger-inbox__conversation-body" data-chat-conversation-body>
                <div class="messenger-conversation-transition" data-chat-conversation-transition hidden aria-live="polite" aria-busy="true">
                    <span class="messenger-conversation-transition__pulse" aria-hidden="true"></span>
                </div>
                <div class="messenger-conversation-stage" data-chat-conversation-stage>
        <?php }?>
        <?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_is_group') ?? null)===null||$tmp==='' ? false ?? null : $tmp)) {?>
        <div class="messenger-conversation-meta">
            <div class="messenger-conversation-meta__main">
                <span class="messenger-conversation-meta__avatar <?php echo (($tmp = $_smarty_tpl->getValue('chat_active_conversation_avatar_theme') ?? null)===null||$tmp==='' ? 'theme-6' ?? null : $tmp);?>
">
                    <?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_avatar_url') ?? null)===null||$tmp==='' ? '' ?? null : $tmp) != '') {?>
                    <img src="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('chat_active_conversation_avatar_url'), ENT_QUOTES, 'UTF-8', true);?>
" alt="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('chat_active_conversation_title') ?? null)===null||$tmp==='' ? 'Group chat' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
" />
                    <?php } else { ?>
                    <?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('chat_active_conversation_avatar_text') ?? null)===null||$tmp==='' ? 'G' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>

                    <?php }?>
                </span>
                <div class="messenger-conversation-meta__copy">
                    <strong><?php echo (($tmp = $_smarty_tpl->getValue('chat_active_conversation_title') ?? null)===null||$tmp==='' ? 'Group chat' ?? null : $tmp);?>
</strong>
                    <?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_is_read_only') ?? null)===null||$tmp==='' ? false ?? null : $tmp)) {?>
                    <span><?php echo (($tmp = $_smarty_tpl->getValue('t')['group_chat_read_only'] ?? null)===null||$tmp==='' ? 'Read only: only admins can write in this group.' ?? null : $tmp);?>
</span>
                    <?php } else { ?>
                    <div class="messenger-conversation-members">
                        <button type="button" class="messenger-conversation-members__toggle" data-chat-group-members-toggle aria-expanded="false">
                            <?php echo (($tmp = $_smarty_tpl->getValue('chat_active_conversation_member_count_label') ?? null)===null||$tmp==='' ? '0 Members' ?? null : $tmp);?>

                        </button>
                        <div class="messenger-conversation-members__popover" data-chat-group-members-popover>
                            <?php if ((true && ($_smarty_tpl->hasVariable('chat_active_conversation_member_emails') && null !== ($_smarty_tpl->getValue('chat_active_conversation_member_emails') ?? null))) && $_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('chat_active_conversation_member_emails')) > 0) {?>
                                <?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('chat_active_conversation_member_emails'), 'chatGroupMemberEmail');
$foreach2DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('chatGroupMemberEmail')->value) {
$foreach2DoElse = false;
?>
                                <span class="messenger-conversation-members__badge"><?php echo htmlspecialchars((string)$_smarty_tpl->getValue('chatGroupMemberEmail'), ENT_QUOTES, 'UTF-8', true);?>
</span>
                                <?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
                            <?php } else { ?>
                            <span class="messenger-conversation-members__badge">Brak danych</span>
                            <?php }?>
                        </div>
                    </div>
                    <?php }?>
                </div>
            </div>
            <?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_can_leave') ?? null)===null||$tmp==='' ? false ?? null : $tmp) || (($tmp = $_smarty_tpl->getValue('chat_active_conversation_can_manage') ?? null)===null||$tmp==='' ? false ?? null : $tmp)) {?>
            <div class="messenger-conversation-meta__actions">
                <button type="button" class="messenger-conversation-meta__menu-button" data-chat-group-settings-toggle aria-expanded="false" aria-label="<?php echo (($tmp = $_smarty_tpl->getValue('t')['settings'] ?? null)===null||$tmp==='' ? 'Ustawienia' ?? null : $tmp);?>
">
                    <i class="fa fa-sliders" aria-hidden="true"></i>
                </button>
                <div class="messenger-conversation-meta__menu messenger-conversation-meta__menu--settings" data-chat-group-settings-menu>
                    <label class="messenger-conversation-meta__setting-toggle">
                        <input type="checkbox" data-chat-email-notifications-toggle <?php if ((($tmp = $_smarty_tpl->getValue('chat_active_member_email_notifications_enabled') ?? null)===null||$tmp==='' ? true ?? null : $tmp)) {?>checked<?php }?>>
                        <span><?php echo (($tmp = $_smarty_tpl->getValue('t')['group_chat_email_notifications'] ?? null)===null||$tmp==='' ? 'Powiadomienia email dla tej rozmowy' ?? null : $tmp);?>
</span>
                    </label>
                    <?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_can_manage') ?? null)===null||$tmp==='' ? false ?? null : $tmp)) {?>
                    <label class="messenger-conversation-meta__setting-field">
                        <span><?php echo (($tmp = $_smarty_tpl->getValue('t')['group_chat_retention_label'] ?? null)===null||$tmp==='' ? 'Auto-usuwanie wiadomości' ?? null : $tmp);?>
</span>
                        <select class="form-control input-sm" data-chat-retention-select>
                            <option value="0"<?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_retention_hours') ?? null)===null||$tmp==='' ? '' ?? null : $tmp) == '') {?> selected<?php }?>><?php echo (($tmp = $_smarty_tpl->getValue('t')['group_chat_retention_off'] ?? null)===null||$tmp==='' ? 'Wyłączone' ?? null : $tmp);?>
</option>
                            <option value="1"<?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_retention_hours') ?? null)===null||$tmp==='' ? '' ?? null : $tmp) == 1) {?> selected<?php }?>>1h</option>
                            <option value="6"<?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_retention_hours') ?? null)===null||$tmp==='' ? '' ?? null : $tmp) == 6) {?> selected<?php }?>>6h</option>
                            <option value="12"<?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_retention_hours') ?? null)===null||$tmp==='' ? '' ?? null : $tmp) == 12) {?> selected<?php }?>>12h</option>
                            <option value="24"<?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_retention_hours') ?? null)===null||$tmp==='' ? '' ?? null : $tmp) == 24) {?> selected<?php }?>>24h</option>
                        </select>
                    </label>
                    <?php }?>
                </div>
                <button type="button" class="messenger-conversation-meta__menu-button" data-chat-group-menu-toggle aria-expanded="false" aria-label="<?php echo (($tmp = $_smarty_tpl->getValue('t')['group_chat_actions'] ?? null)===null||$tmp==='' ? 'Group actions' ?? null : $tmp);?>
">
                    <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
                </button>
                <div class="messenger-conversation-meta__menu" data-chat-group-menu>
                    <?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_can_manage') ?? null)===null||$tmp==='' ? false ?? null : $tmp)) {?>
                    <button type="button" class="messenger-conversation-meta__menu-item" data-messenger-group-open data-group-mode="invite" data-conversation-id="<?php echo (($tmp = $_smarty_tpl->getValue('chat_active_conversation_id') ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
" data-group-title="<?php echo htmlspecialchars((string)(($tmp = $_smarty_tpl->getValue('chat_active_conversation_title') ?? null)===null||$tmp==='' ? 'Group chat' ?? null : $tmp), ENT_QUOTES, 'UTF-8', true);?>
">
                        <?php echo (($tmp = $_smarty_tpl->getValue('t')['group_chat_add_member'] ?? null)===null||$tmp==='' ? 'Add member' ?? null : $tmp);?>

                    </button>
                    <?php }?>
                    <?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_can_leave') ?? null)===null||$tmp==='' ? false ?? null : $tmp)) {?>
                    <button type="button" class="messenger-conversation-meta__menu-item messenger-conversation-meta__menu-item--danger" data-chat-leave-group data-conversation-id="<?php echo (($tmp = $_smarty_tpl->getValue('chat_active_conversation_id') ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
">
                        <?php echo (($tmp = $_smarty_tpl->getValue('t')['group_chat_leave'] ?? null)===null||$tmp==='' ? 'Leave' ?? null : $tmp);?>

                    </button>
                    <?php }?>
                    <?php if ((($tmp = $_smarty_tpl->getValue('chat_active_conversation_can_manage') ?? null)===null||$tmp==='' ? false ?? null : $tmp)) {?>
                    <button type="button" class="messenger-conversation-meta__menu-item messenger-conversation-meta__menu-item--danger" data-chat-delete-group data-conversation-id="<?php echo (($tmp = $_smarty_tpl->getValue('chat_active_conversation_id') ?? null)===null||$tmp==='' ? 0 ?? null : $tmp);?>
">
                        <?php echo (($tmp = $_smarty_tpl->getValue('t')['group_chat_remove'] ?? null)===null||$tmp==='' ? 'Remove' ?? null : $tmp);?>

                    </button>
                    <?php }?>
                </div>
            </div>
            <?php }?>
        </div>
        <?php }?>
    	<div class="messages" id="chat_scroll">
			<ul class="messenger-list">
                <?php if (!(($tmp = $_smarty_tpl->getValue('chat_active_conversation_is_group') ?? null)===null||$tmp==='' ? false ?? null : $tmp)) {?>
				<li class="messenger-item messenger-item--received messenger-item--intro">
					<div class="messenger-bubble">
						<div class="messenger-author"><?php echo (($tmp = $_smarty_tpl->getValue('chat_support_label') ?? null)===null||$tmp==='' ? 'Support' ?? null : $tmp);?>
</div>
						<div class="messenger-text"><?php echo (($tmp = $_smarty_tpl->getValue('t')['chat_intro_message'] ?? null)===null||$tmp==='' ? 'Would you like to ask something or do you need help with your subscription?' ?? null : $tmp);?>
</div>
					</div>
					<?php if ((true && ($_smarty_tpl->hasVariable('chat_faq_prompts') && null !== ($_smarty_tpl->getValue('chat_faq_prompts') ?? null))) && $_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('chat_faq_prompts')) > 0) {?>
					<div class="messenger-faq-panel">
						<div class="messenger-faq-label"><?php echo (($tmp = $_smarty_tpl->getValue('t')['chat_quick_questions_label'] ?? null)===null||$tmp==='' ? 'Quick questions' ?? null : $tmp);?>
</div>
						<div class="messenger-faq-prompts" data-chat-faq-list>
							<?php
$_from = $_smarty_tpl->getSmarty()->getRuntime('Foreach')->init($_smarty_tpl, $_smarty_tpl->getValue('chat_faq_prompts'), 'faqPrompt');
$foreach3DoElse = true;
foreach ($_from ?? [] as $_smarty_tpl->getVariable('faqPrompt')->value) {
$foreach3DoElse = false;
?>
							<button
								type="button"
								class="messenger-faq-prompt"
								data-chat-faq-key="<?php echo htmlspecialchars((string)$_smarty_tpl->getValue('faqPrompt')['faq_key'], ENT_QUOTES, 'UTF-8', true);?>
"
								onclick="return chatFaqPrompt('<?php echo strtr((string)$_smarty_tpl->getValue('faqPrompt')['faq_key'], array("\\" => "\\\\", "'" => "\\'", "\"" => "\\\"", "\r" => "\\r", 
						"\n" => "\\n", "</" => "<\/", "<!--" => "<\!--", "<s" => "<\s", "<S" => "<\S",
						"`" => "\\`", "\${" => "\\\$\{"));?>
');"
							>
								<?php echo $_smarty_tpl->getValue('faqPrompt')['title'];?>

							</button>
							<?php
}
$_smarty_tpl->getSmarty()->getRuntime('Foreach')->restore($_smarty_tpl, 1);?>
						</div>
					</div>
					<?php }?>
                </li>
                <?php }?>
                <?php
$__section_i_0_loop = (is_array(@$_loop=$_smarty_tpl->getValue('chat')) ? count($_loop) : max(0, (int) $_loop));
$__section_i_0_total = $__section_i_0_loop;
$_smarty_tpl->tpl_vars['__smarty_section_i'] = new \Smarty\Variable(array());
if ($__section_i_0_total !== 0) {
for ($__section_i_0_iteration = 1, $_smarty_tpl->tpl_vars['__smarty_section_i']->value['index'] = 0; $__section_i_0_iteration <= $__section_i_0_total; $__section_i_0_iteration++, $_smarty_tpl->tpl_vars['__smarty_section_i']->value['index']++){
?>
                <?php if ($_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['time_anchor_label'] != '') {?>
                <li class="messenger-time-anchor">
                    <span><?php echo $_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['time_anchor_label'];?>
</span>
                </li>
                <?php }?>
                <li class="messenger-item messenger-item--<?php echo $_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['direction'];?>
">
					<div class="messenger-bubble">
						<?php if ($_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['direction'] == 'received') {?>
						<div class="messenger-author"><?php echo $_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['sender_label'];?>
</div>
						<?php }?>
						<?php if ($_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['attachment_path']) {?>
						<a href="<?php echo $_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['attachment_path'];?>
" class="messenger-image-link" target="_blank" rel="noopener noreferrer">
							<img src="<?php echo $_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['attachment_path'];?>
" class="messenger-image" alt="attachment" />
						</a>
						<?php }?>
						<?php if ($_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['message_html'] != '') {?>
						<div class="messenger-text"><?php echo $_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['message_html'];?>
</div>
						<?php }?>
					</div>
                    <div class="messenger-time-detail"><?php echo $_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['created_label'];?>
</div>
                    <?php if ($_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['can_delete']) {?>
                    <button
                        type="button"
                        class="messenger-delete-button<?php if ((($tmp = $_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['delete_mode'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) == 'icon') {?> messenger-delete-button--icon<?php }?>"
                        data-message-id="<?php echo $_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['id'];?>
"
                        data-delete-until="<?php echo $_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['delete_until_timestamp'];?>
"
                        data-delete-label="<?php echo (($tmp = $_smarty_tpl->getValue('t')['chat_delete'] ?? null)===null||$tmp==='' ? 'Delete' ?? null : $tmp);?>
"
                        title="<?php echo (($tmp = $_smarty_tpl->getValue('t')['chat_delete'] ?? null)===null||$tmp==='' ? 'Delete' ?? null : $tmp);?>
"
                        aria-label="<?php echo (($tmp = $_smarty_tpl->getValue('t')['chat_delete'] ?? null)===null||$tmp==='' ? 'Delete' ?? null : $tmp);?>
"
                    >
                        <?php if ((($tmp = $_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['delete_mode'] ?? null)===null||$tmp==='' ? '' ?? null : $tmp) == 'icon') {?>
                        <i class="fa fa-trash" aria-hidden="true"></i>
                        <?php } else { ?>
                        <?php echo (($tmp = $_smarty_tpl->getValue('t')['chat_delete'] ?? null)===null||$tmp==='' ? 'Delete' ?? null : $tmp);?>
 (<?php echo $_smarty_tpl->getValue('chat')[($_smarty_tpl->getValue('__smarty_section_i')['index'] ?? null)]['delete_remaining_seconds'];?>
s)
                        <?php }?>
                    </button>
                    <?php }?>
                </li>
                <?php
}
}
?>
			</ul>
		</div>
                </div>
        <?php if ((($tmp = $_smarty_tpl->getValue('user')['customer_type'] ?? null)===null||$tmp==='' ? 'client' ?? null : $tmp) == 'reseller' && (true && ($_smarty_tpl->hasVariable('chat_conversations') && null !== ($_smarty_tpl->getValue('chat_conversations') ?? null))) && $_smarty_tpl->getSmarty()->getModifierCallback('count')($_smarty_tpl->getValue('chat_conversations')) > 0) {?>
            </div>
        </div>
        <?php }?>
	</div>
<?php } else { ?>
<p>Please login again...</p>
<?php }
}
}
