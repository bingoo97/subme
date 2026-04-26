<div class="content-box">
    <h1><a href="/"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.history_title}</h1>
	<div class="alert alert-dismissible alert-info">
		<i class="fa fa-info-circle" aria-hidden="true"></i> {$t.history_notice}
	</div>
	<hr/>
	{if !$history}
    <p>{$t.history_empty}</p>
	{else}
    <table class="table table-bordered table-striped">
        <thead>
            <tr class="center">
                <td class="text-left">{$t.history_column_notification}</td>
                <td>{$t.history_column_date}</td>
            </tr>
        </thead>
        <tbody>
        {section name=i loop=$history}
            <tr align="center">
                <td class="text-left">{if $history[i].is_html}{$history[i].desc nofilter}{else}{$history[i].desc|escape}{/if}</td>
                <td>{$history[i].date}</td>
            </tr>
        {/section}
        </tbody>
    </table>
        {$generator}
	{/if}
</div>
