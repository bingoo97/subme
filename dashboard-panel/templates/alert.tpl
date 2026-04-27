{if isset($alert_page_heading) && $alert_page_heading != ''}
<div class="content-box alert-page-heading-box">
  <h1 class="alert-page-heading">
    <a href="{$alert_page_back_url|default:'/orders'}" title="{$t.back|default:'Back'}"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a>
    {$alert_page_heading}
  </h1>
</div>
{/if}
{if $alert}
    <div class="alert alert-success">
      <i class="fa fa-check" aria-hidden="true"></i> {$alert}
    </div>
{/if}
{if $alert_error}
    <div class="alert alert-dismissible alert-danger">
      <button type="button" class="close" data-dismiss="alert">x</button>
      <i class="fa fa-times" aria-hidden="true"></i> {$alert_error}
    </div>
{/if}
{if $errors}
    {section name=i loop=$errors}
        <div class="alert alert-dismissible alert-danger">
          <button type="button" class="close" data-dismiss="alert">x</button>
          <i class="fa fa-times" aria-hidden="true"></i> {$errors[i]}
        </div>
    {/section}
{/if}