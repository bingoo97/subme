                {if $page_guidance_enabled|default:false && isset($page_helper_content.items) && $page_helper_content.items|@count gt 0}
                <div class="page-guide-alert">
                    <div class="alert alert-info page-guide-alert__box">
                        <div class="page-guide-alert__heading"><span class="page-guide-alert__icon" aria-hidden="true"><i class="fa fa-lightbulb-o"></i></span><div class="page-guide-alert__title">{$page_helper_content.title|default:'Jak korzystać z tej podstrony?'|escape:'html'}</div></div>
                        <ul class="page-guide-alert__list">
                            {foreach from=$page_helper_content.items item=page_helper_item}
                                <li>{$page_helper_item|escape:'html'}</li>
                            {/foreach}
                        </ul>
                    </div>
                </div>
                {/if}
  				<div class="clr"></div>
            </div>
          </div>   
        </section>
