	<div class="content-box login-correct-state text-center">
		  <h2>{$t.logout|default:'Logout'}</h2>
		  <hr/>
		  <p>{$t.logout_message|default:'You have been logged out of the site.'}</p>
		  <hr />
		  <a class="btn btn-default btn-md" href="/">
			{$t.go_to_homepage|default:'Go to Homepage'} <i class="fa fa-angle-double-right" aria-hidden="true"></i>
		  </a>
	</div>

	<script>
	  setTimeout(function() {
	    window.location.href = '/';
	  }, 3000);
	</script>

