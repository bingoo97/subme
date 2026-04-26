<div class="content-box info-page">
    <div class="payment-wizard__header">
        <h1><a href="/"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.instructions_title|default:'Instructions'}</h1>
        <p>{$t.instructions_intro|default:'Choose the payment guide you want to open.'}</p>
    </div>

    <div class="home_buttons home_buttons--compact">
        {section name=i loop=$instruction_guides}
            <div class="col-sm-12">
                <a href="{$instruction_guides[i].href}" title="{$instruction_guides[i].title}">
                    <div class="one_box">
                        {if $instruction_guides[i].href == '/instruction-trust-wallet'}
                            <img src="img/crypto/exchanges/trust.png" class="exchange-logo-lg" alt="Trust Wallet" />
                        {elseif $instruction_guides[i].href == '/instruction-revolut'}
                            <img src="img/crypto/exchanges/revo.png" class="exchange-logo-lg" alt="Revolut" />
                        {elseif $instruction_guides[i].href == '/instruction-crypto-exchange'}
                            <img src="img/crypto/exchanges/cex.jpg" class="exchange-logo-lg" alt="Crypto Exchange" />
                        {elseif $instruction_guides[i].href == '/instruction-smart-iptv'}
                            <img src="/img/logo_smart.png" class="exchange-logo-lg" alt="Smart IPTV" />
                        {elseif $instruction_guides[i].href == '/instruction-ott-player'}
                            <img src="/img/ott/logo-ott.jpg" class="exchange-logo-lg" alt="OTT Player" />
                        {elseif $instruction_guides[i].href == '/instruction-newlook'}
                            <img src="/img/new_look.png" class="exchange-logo-lg" alt="NewLook" />
                        {else}
                            <i class="fa {$instruction_guides[i].icon}" aria-hidden="true"></i>
                        {/if}
                        <p class="title">{$instruction_guides[i].title}</p>
                    </div>
                </a>
            </div>
        {/section}
    </div>
</div>
