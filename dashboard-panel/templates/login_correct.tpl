<div class="content-box login-correct-state">
  <h1 style="border:none;"><i class="fa fa-circle-o-notch rotating" aria-hidden="true"></i> {$t.login_success_title}</h1>
  <p>{$t.home_loading}</p>
  <a href="/" class="btn btn-dark btn-lg home_link" title="{$t.home_account}">
  	 {$t.home_account} <i class="fa fa-angle-double-right" aria-hidden="true"></i>
  </a>
</div>

<script>
  setTimeout(function() {
    window.location.href = '/';
  }, 3000);
</script>
