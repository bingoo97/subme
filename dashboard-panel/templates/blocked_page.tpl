<div id="blocked_page" class="container">
    <div class="col-md-12 bg_red padding-lg">
        	<div class="col-sm-1 hidden-xs wow pulse" data-wow-iteration="infinite" data-wow-duration="1500ms">
                <img src="/img/alert-warning.png" class="img-responsive" style="margin: 4px auto 0 10px; max-height: 50px; " alt="alert" />
            </div>
            <div class="col-sm-11">
            	<!--<p>Prace techniczne u dostawcy - prosimy o cierpliwość.</p>
                <p>Trwa naprawa problemu...</p> -->
                <p>This website is not available in your country...</p>
                <p>Technical Support: <a href="mailto:{$ustawienia.admin_email}" class="yellow">{$ustawienia.admin_email}</a></p>
            </div>
            {if !$sended_email}
            <div class="col-sm-12" style="margin-top:20px;"> 
                        <form action="" method="post" class="form-horizontal">
                          <div class="form-group">
                                  <label class="col-sm-2 control-label">E-mail</label>
                                <div class="col-sm-10">
                                  <input type="email" class="form-control" name="email" value="{$user.email}" placeholder="Enter your e-mail..." required />
                                </div>
                          </div>
                          <div class="form-group">
                                  <label class="col-sm-2 control-label">Message</label>
                                <div class="col-sm-10">
                                  <textarea rows="4" class="form-control" name="tresc"></textarea>
                                </div>
                          </div>
                          <div class="form-group">
                            <div class="col-sm-offset-2 col-sm-10">
                              <button type="submit" name="send_email" class="btn btn-warning">
                                 Send
                              </button>
                            </div>
                          </div>
               </form>
           </div>
           {/if}
    </div>
    <div class="clr"></div>
        <div class="container">
           <div class="ip_content">
            <p><i class="fa fa-plug" aria-hidden="true"></i> <span>{$ip_info.ip}</span> - {$ip_info.country_code} - {$ip_info.country}</p>
           </div>
    </div> 
</div>