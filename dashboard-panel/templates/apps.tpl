<div class="content-box">
    <h1><a href="/"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.apps_title|default:'Apps'}</h1>
    <p>{$t.apps_intro|default:'Choose an app for your device and open its official download page. After installation, use your login details or stream link from the subscription modal.'}</p>
    {if $apps_fallback_to_global_url && $apps_url neq ''}
    <p>{$t.apps_download_from|default:'You can download apps directly from'}: <a href="{$apps_url}" target="_blank" rel="noopener noreferrer">{$apps_url}</a></p>
    {/if}
    <hr/>
    <table class="table table-bordered table-striped">
        <thead>
            <tr class="center">
                <td style="width: 50px;"></td>
                <td class="text-left">Name</td>
                <td></td>
            </tr>
        </thead>
        <tbody>
        {foreach from=$apps item=app}
            <tr align="center">
                <td style="width: 50px;"><img src="{$app.logo}" alt="{$app.name}" class="rounded" width="32" height="32"></td>
                <td class="text-left">
                    {if $app.instruction_url neq ''}
                        <a href="{$app.instruction_url}">{$app.name}</a>
                    {else}
                        {$app.name}
                    {/if}
                </td>
                <td>
                    {if $app.url neq ''}
                    <a href="{$app.url}" target="_blank" rel="noopener noreferrer" class="text-dark">
                        <i class="fa fa-download" aria-hidden="true"></i>
                    </a>
                    {else}
                    <span class="text-muted">
                        <i class="fa fa-download" aria-hidden="true"></i>
                    </span>
                    {/if}
                </td>
            </tr>
        {/foreach}
        </tbody>
    </table>
</div>
