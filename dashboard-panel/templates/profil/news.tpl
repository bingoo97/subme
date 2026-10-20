<div class="content-box customer-news">
    <h1><a href="/"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.news_title}</h1>
	{if $news|default:[]}
	<div id="news">
		{section name=i loop=$news}
		<article class="news_inner {if ($news[i].date_s > ($smarty.now - 3600*24))}active{/if}" aria-labelledby="newsTitle{$news[i].id|escape:'html'}">
			<header class="news_title">
				<div class="customer-news__meta">
					{if ($news[i].date_s > ($smarty.now - 3600*24))}<span class="news-new-badge">{$t.home_new_badge|escape:'html'}</span>{/if}
					<span class="customer-news__date"><i class="fa fa-calendar-o" aria-hidden="true"></i><time datetime="{$news[i].date_iso|escape:'html'}">{$news[i].date|escape:'html'}</time></span>
					{if $news[i].author_label}<span class="customer-news__author"><i class="fa fa-pencil" aria-hidden="true"></i><span>{$news[i].author_label|escape:'html'}</span></span>{/if}
				</div>
				<h2 id="newsTitle{$news[i].id|escape:'html'}">
					<a href="{if $news[i].url}{$news[i].url|escape:'html'}{else}/news-{$news[i].id}{/if}">
						{$news[i].title|escape:'html'}
					</a>
				</h2>
			</header>
			{if $news[i].text}
			<div class="news_content">
				{$news[i].text nofilter}
			</div>
			{/if}
		</article>
		{/section}
	</div>
	{else}
	<div class="customer-news__empty"><i class="fa fa-newspaper-o" aria-hidden="true"></i><p>{$t.news_empty|escape:'html'}</p></div>
    {/if}
</div>
