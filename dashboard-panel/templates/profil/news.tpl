<div class="content-box">
    <h1><a href="/"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.news_title}</h1>
	<div class="alert alert-dismissible alert-info">
		<i class="fa fa-info-circle" aria-hidden="true"></i> {$t.news_notice}
	</div>
	{if $news}
	<div id="news">
		{section name=i loop=$news}
		<div class="news_inner col-md-12">
			<div class="news_title">
				<h2>
					<a href="/news-{$news[i].id}" title="Preview">
						{if ($news[i].date_s > ($smarty.now - 3600*24))}
						<span class="news-new-badge">{$t.home_new_badge}</span>
						{/if}
						{$news[i].title}
					</a>
				</h2>
				<p class="entry">{$t.news_published} <span>{$news[i].date}</span> {$t.news_by} <span>{$reseller.name}</span></p>
			</div>
			{if $news[i].text}
			<div class="news_content">
				<p>{$news[i].text}</p>
			</div>
			{/if}
		</div>
		{/section}
	</div>
	{else}
	<p>{$t.news_empty}</p>
    {/if}
</div>
