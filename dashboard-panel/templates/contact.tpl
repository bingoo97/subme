<div class="content-box">
    <h1><i class="fa fa-envelope-o" style="margin-right: 10px;" aria-hidden="true"></i> {$t.contact_title|default:'Support'}</h1>
    <p>{$t.contact_intro|default:'Use the form below to contact support.'}</p>

    {if !empty($contact_form_disabled)}
    <div class="alert alert-warning">
        <i class="fa fa-ban" aria-hidden="true"></i> {$t.contact_disabled_notice|default:'The contact form is currently disabled.'}
    </div>
    {/if}

    <form action="" method="post" class="form-horizontal">
        <input type="hidden" name="_csrf" value="{$csrf_token|default:''}">
        <div class="form-group">
            <label for="contact-subject" class="col-sm-2 control-label hidden-xs">{$t.contact_subject|default:'Subject'}:</label>
            <div class="col-sm-10">
                <select id="contact-subject" name="subject" class="form-control"{if !empty($contact_form_disabled)} disabled{/if}>
                    {foreach from=$contact_subject_options key=subject_key item=subject_label}
                    <option value="{$subject_key}" {if isset($contact_form.subject) && $contact_form.subject === $subject_key}selected{/if}>{$subject_label}</option>
                    {/foreach}
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="contact-email" class="col-sm-2 control-label hidden-xs">{$t.contact_email|default:'Email'}:</label>
            <div class="col-sm-10">
                <input id="contact-email" type="email" class="form-control" name="email" value="{$contact_form.email|default:''}" placeholder="{$t.contact_email_placeholder|default:'Enter email...'}" required{if !empty($contact_form_disabled)} disabled{/if} />
            </div>
        </div>

        <div class="form-group">
            <label for="contact-message" class="col-sm-2 control-label hidden-xs">{$t.contact_message|default:'Message'}:</label>
            <div class="col-sm-10">
                <textarea id="contact-message" rows="5" class="form-control" placeholder="{$t.contact_message_placeholder|default:'Enter message...'}" name="message"{if !empty($contact_form_disabled)} disabled{/if}>{$contact_form.message|default:''}</textarea>
            </div>
        </div>

        <div class="form-group">
            <div class="col-sm-offset-2 col-sm-8">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="send_copy" {if !empty($contact_form.send_copy)}checked{/if}{if !empty($contact_form_disabled)} disabled{/if} /> {$t.contact_send_copy|default:'Send a copy to me'}
                    </label>
                </div>
            </div>
        </div>

        <hr />

        <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
                
                <a href="/" class="btn btn-default btn-lg" title="{$t.back}">
                    <i class="fa fa-angle-double-left" aria-hidden="true"></i> {$t.back}
                </a>
                <button type="submit" name="send_contact" class="btn btn-dark btn-lg"{if !empty($contact_form_disabled)} disabled{/if}>
                    {$t.contact_submit|default:'Send message'} <i class="fa fa-angle-double-right" aria-hidden="true"></i> 
                </button>
            </div>
        </div>
    </form>
</div>
