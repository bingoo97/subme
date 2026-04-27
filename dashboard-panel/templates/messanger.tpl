{if $user.logged && $settings.support_chat_enabled}
<div class="messanger" id="messanger">
        <div class="panel">
                <div class="panel-heading" id="panel-heading" data-user-id="{$user.id}" role="button" aria-expanded="false" aria-controls="collapseOne" data-messenger-toggle onclick="return toggleMessengerPanel();">
                    <span class="messenger-heading-title"><i class="fa fa-envelope-o" aria-hidden="true"></i> {if $user.customer_type|default:'client' eq 'reseller'}Messenger{else}Technical Support{/if}</span>
                    <div class="btn-group pull-right">
                        {if $chat_customer_can_create_groups|default:false}
                        <button type="button" class="btn btn-default btn-xs messenger-group-action" data-messenger-group-open title="{$t.group_chat_create|default:'Create group'}">
                            <i class="fa fa-plus" aria-hidden="true"></i>
                        </button>
                        {/if}
                        <button type="button" class="btn btn-default btn-xs messenger-toggle-action{if $chat_nieprzeczytane > 0} is-unread is-attention{/if}" aria-expanded="false" aria-controls="collapseOne" data-messenger-toggle-button onclick="event.stopPropagation(); return toggleMessengerPanel();">
                        {if $chat_nieprzeczytane > 0}
                        	<span class="badge wow pulse messenger-unread-badge" data-wow-iteration="infinite" data-wow-duration="1500ms">{$chat_nieprzeczytane}</span>
                        {else}
                            <span class="badge messenger-unread-badge" style="display:none;">0</span>
                        {/if}
                            <i class="fa fa-angle-down messenger-toggle-icon" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            <div class="panel-collapse collapse" id="collapseOne" style="display: none;" aria-hidden="true">
                <div class="panel-body" id="chat_box">
                        {include file='messanger_content.tpl'}
                </div>
                <div class="panel-footer">
                    <script>
                        window.MESSENGER_BOOTSTRAP = window.MESSENGER_BOOTSTRAP || {};
                        window.MESSENGER_BOOTSTRAP.userId = {$user.id|default:0};
                        window.MESSENGER_BOOTSTRAP.endpoint = 'check_chat.php';
                        window.MESSENGER_BOOTSTRAP.csrfToken = '{$csrf_token|default:''|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.supportName = '{$chat_support_label|default:"Support"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.linkPreviewLoading = '{$t.chat_link_preview_loading|default:"Loading link preview..."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.linkPreviewRemove = '{$t.chat_link_preview_remove|default:"Send without preview"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.linkPreviewOpen = '{$t.chat_link_preview_open|default:"Open link"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.writeMessagePlaceholder = '{$t.chat_write_message|default:"Write message..."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupCreateEnabled = {if $chat_customer_can_create_groups|default:false}true{else}false{/if};
                        window.MESSENGER_BOOTSTRAP.groupDirectTitle = '{$t.group_chat_direct_title|default:"Start direct conversation"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupDirectSubmit = '{$t.group_chat_direct_submit|default:"Start conversation"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupCreateTitle = '{$t.group_chat_create|default:"Create group chat"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupCreateSubmit = '{$t.group_chat_create_submit|default:"Create group"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupDirectToggle = '{$t.group_chat_direct_toggle|default:"Find reseller"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupGroupToggle = '{$t.group_chat_group_toggle|default:"Create group"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupDirectEmailLabel = '{$t.group_chat_direct_email_label|default:"Add reseller by email"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupGroupEmailLabel = '{$t.group_chat_add_by_email|default:"Add participant by email"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupDirectHint = '{$t.group_chat_direct_hint|default:"Add one reseller email to start a direct conversation right away."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupGroupHint = '{$t.group_chat_invite_expiry_note|default:"Each invitation is valid for 24 hours. If nobody accepts it in time, it is removed automatically."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupDirectLimit = '{$t.group_chat_direct_limit|default:"Direct conversation allows only one reseller."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupReadOnlyPlaceholder = '{$t.group_chat_read_only_placeholder|default:"This group is read only."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupCreateError = '{$t.group_chat_create_error|default:"Unable to create the group chat."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupInviteError = '{$t.group_chat_invite_error|default:"Unable to update the invitation."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupLeaveError = '{$t.group_chat_leave_error|default:"Unable to leave the group chat."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupDeleteError = '{$t.group_chat_delete_error|default:"Unable to remove the group chat."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupNameRequired = '{$t.group_chat_name_required|default:"Group name is required."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupParticipantsRequired = '{$t.group_chat_participants_required|default:"Add at least one participant."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupEmailInvalid = '{$t.group_chat_email_invalid|default:"Enter a valid email address."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupEmailDuplicate = '{$t.group_chat_email_duplicate|default:"This invitation is already added."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupEmailChecking = '{$t.group_chat_email_checking|default:"Checking user..."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupEmailAdded = '{$t.group_chat_email_added|default:"Invitation prepared. It will expire after 24 hours if not accepted."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupEmailNotFound = '{$t.group_chat_email_not_found|default:"No reseller or admin account was found for this email."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupInviteSubmit = '{$t.group_chat_invite_submit|default:"Send invitations"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupInviteTitle = '{$t.group_chat_invite_title|default:"Add members to group"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupInviteNameLabel = '{$t.group_chat_invite_name_label|default:"Group"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupInviteSuccess = '{$t.group_chat_invite_success|default:"Invitations sent."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupDeleteConfirm = '{$t.group_chat_delete_confirm|default:"Remove this group for all participants?"|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupSettingsError = '{$t.settings_save_error|default:"Unable to save settings."|escape:'javascript'}';
                        window.MESSENGER_BOOTSTRAP.groupRetentionError = '{$t.settings_save_error|default:"Unable to save settings."|escape:'javascript'}';
                    </script>
                    <div class="messenger-alert" id="messenger_alert" style="display:none;"></div>
                    <div class="messenger-compose">
                        <div class="messenger-compose__preview" id="messenger_link_preview" style="display:none;"></div>
                        <div class="messenger-compose__row">
                            <button type="button" class="messenger-compose__action btn btn-default" data-messenger-upload-open onclick="return openMessengerUpload();" title="{$t.chat_upload|default:'Upload image'}">
                                <i class="fa fa-file-image-o" aria-hidden="true"></i>
                            </button>
                            <input type="text" class="form-control input-sm messenger-compose__input" id="tresc" name="tresc" placeholder="{$t.chat_write_message|default:'Write message...'}" autocomplete="off" />
                            <button type="button" class="messenger-compose__send btn btn-primary" id="btn-chat" data-user-id="{$user.id}">
                                <i class="fa fa-paper-plane" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div> 
                 {include file = 'messanger_upload.tpl'}
                 <div class="messenger-upload-modal" id="messenger_group_modal" aria-hidden="true">
                    <div class="messenger-upload-backdrop" data-messenger-group-close></div>
                    <div class="messenger-upload-dialog">
                        <div class="messenger-upload-card messenger-group-card">
                            <div class="messenger-upload-header">
                                <h2 id="messenger_group_modal_title">{$t.group_chat_create|default:'Create group chat'}</h2>
                                <button type="button" class="messenger-upload-close" data-messenger-group-close aria-label="{$t.close|default:'Close'}">
                                    <i class="fa fa-times" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="messenger-group-body">
                                <div class="messenger-alert" id="messenger_group_alert" style="display:none;"></div>
                                <div class="messenger-group-mode" id="messenger_group_mode_switch">
                                    <button type="button" class="messenger-group-mode__toggle is-active" data-messenger-group-kind="direct" aria-pressed="true">{$t.group_chat_direct_toggle|default:'Znajdź resellera'}</button>
                                    <button type="button" class="messenger-group-mode__toggle" data-messenger-group-kind="group" aria-pressed="false">{$t.group_chat_group_toggle|default:'Stwórz grupę'}</button>
                                </div>
                                <label class="messenger-group-field" id="messenger_group_name_field">
                                    <span>{$t.group_chat_name|default:'Group name'}</span>
                                    <input type="text" class="form-control" id="messenger_group_name" maxlength="20" placeholder="{$t.group_chat_name_placeholder|default:'Example: Team access'}">
                                </label>
                                <div class="messenger-group-field messenger-group-field--context" id="messenger_group_context" style="display:none;">
                                    <span id="messenger_group_context_label">{$t.group_chat_invite_name_label|default:'Group'}</span>
                                    <div class="messenger-group-context" id="messenger_group_context_title"></div>
                                </div>
                                <div class="messenger-group-field">
                                    <span id="messenger_group_email_label">{$t.group_chat_direct_email_label|default:'Dodaj resellera po emailu lub @nicku'}</span>
                                    <div class="messenger-group-add-row">
                                        <input type="text" class="form-control" id="messenger_group_email" placeholder="name@example.com lub @nick">
                                        <button type="button" class="btn btn-default" data-messenger-group-add>{$t.add|default:'Add'}</button>
                                    </div>
                                    <small class="messenger-group-hint" id="messenger_group_hint">{$t.group_chat_direct_hint|default:'Dodaj jeden email resellera lub wpisz @nick, aby od razu rozpocząć rozmowę 1 na 1.'}</small>
                                </div>
                                <div class="messenger-group-members" id="messenger_group_members"></div>
                                <div class="messenger-group-actions">
                                    <button type="button" class="btn btn-dark btn-block" data-messenger-group-submit id="messenger_group_submit_label">{$t.group_chat_create_submit|default:'Create group'}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                 </div>
				 <script src="/assets/js/messanger.js?v={$chat_asset_version|default:1}"></script>
            </div> 
        </div>
</div> 
{/if}
