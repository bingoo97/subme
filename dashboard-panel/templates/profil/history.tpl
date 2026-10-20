<div class="content-box customer-history customer-secondary-page">
    <h1><a href="/"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.history_title}</h1>
	<div class="alert alert-dismissible alert-info">
		<i class="fa fa-info-circle" aria-hidden="true"></i> {$t.history_notice}
	</div>
	<hr/>
	{if !$history}
    <div class="customer-secondary-empty"><i class="fa fa-history" aria-hidden="true"></i><p>{$t.history_empty}</p></div>
	{else}
    <div class="table-responsive history-table-responsive">
        <table class="table customer-history-table">
            <thead>
                <tr class="center">
                    <th scope="col" class="text-left">{$t.history_column_notification}</th>
                    <th scope="col">{$t.history_column_date}</th>
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
    </div>
        {$generator}
	{/if}
</div>
