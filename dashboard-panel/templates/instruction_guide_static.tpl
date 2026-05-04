<div class="content-box info-page">
    <div class="payment-wizard__header">
        <h1><a href="/instructions"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$instruction_guide_title}</h1>
    </div>

    {$instruction_guide_body_html nofilter}

    <hr />
    <div class="d-grid gap-2">
        {$instruction_guide_support_footer_html nofilter}
        <a href="/instructions" class="btn btn-default btn-lg btn-back" title="{$t.back|default:'Back'}"><i class="fa fa-angle-double-left" aria-hidden="true"></i> {$t.back|default:'Back'}</a>
    </div>
</div>
