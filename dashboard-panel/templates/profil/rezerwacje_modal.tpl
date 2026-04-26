  
<!-- Modal -->
<div id="modal_{$wygrane[i].id}" class="modal fade" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
          <div class="modal-body">
                  	<form class="form-horizontal" action="" method="post">
                       {if $wygrane[i].status == 1 && $wygrane[i].wysylka == 1}
                    	  <div class="form-group" id="zaplacono_{$wygrane[i].id}">
                            <h1>File {if $wygrane[i].typ_pliku == 0}M3U{else}e2{/if} - URL:</h1>
                            <textarea class="form-control" style="font-size:16px;" rows="5" name="url_link">{$wygrane[i].url_link}</textarea>
                            <hr />
                            {if $wygrane[i].url_link <> ''}
                             <div class="form-group">
                                   <a href="{$wygrane[i].url_link}" class="btn btn-blue btn-block btn-lg back" >
									   <i class="fa fa-download"></i> Download
								   </a>
								   <button type="button" class="btn btn-default btn-block btn-lg back" data-dismiss="modal">
									<i class="fa fa-angle-double-left" aria-hidden="true"></i> Close
							       </button>
                             </div>
                             {/if}
                          </div>
                       {/if}
                    </form>
          </div>
    </div>
  </div>
</div>

