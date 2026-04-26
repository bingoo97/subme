<div class="content-box info-page">
    <div class="payment-wizard__header">
        <h1><a href="/"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.how_to_pay_title|default:'How to pay?'}</h1>
        <p>{$t.how_to_pay_intro|default:'Choose one of the help sections below. Detailed instructions can be expanded later.'}</p>
    </div>

    <div class="home_buttons home_buttons--compact">
        {section name=i loop=$payment_help_links}
            <div class="col-sm-12">
                <a href="{$payment_help_links[i].href}" title="{$payment_help_links[i].title}">
                    <div class="one_box">
                        <i class="fa {$payment_help_links[i].icon}" aria-hidden="true"></i>
                        <p class="title">{$payment_help_links[i].title}</p>
                    </div>
                </a>
            </div>
        {/section}
    </div>
</div>
