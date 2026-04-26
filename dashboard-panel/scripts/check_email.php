<script>
		function runEmailVerificationProgress(){
			window.percent = 0;
			window.clearInterval(window.progressInterval);
			window.clearTimeout(window.verifyRedirectTimeout);
			window.verifyRedirectTimeout = window.setTimeout(function () {
				window.location.href = '/login';
			}, 1800);
			window.progressInterval = window.setInterval(function(){
				if(window.percent < 100) {
					window.percent++;
					$('.progress').addClass('progress-striped').addClass('active');
					$('.progress .bar:first')
						.removeClass().addClass('bar')
						.addClass('bar-success');
					$('.progress .bar:first').width(window.percent+'%');
					$('.progress .bar:first').text(window.percent+'%');
				} else {
					window.clearInterval(window.progressInterval);
					$('.progress').removeClass('progress-striped').removeClass('active');
					$('.progress .bar:first').text('100%');
				}
			}, 25);
		}

		function check_email(){
		  var home_email = $.trim($("#home_email").val());
		  var csrfToken = String($("#home_email_csrf").val() || '');
		  var $button = $("#home_email_login");

		  if (!home_email) {
			$('#check_email_info').html('<div class="alert-box"><p><i class="fa fa-ban red" aria-hidden="true"></i> Enter your email address.</p></div>');
			return false;
		  }

		  $button.prop('disabled', true);

		  $.ajax({
			 type: 'POST',
			 url: 'check_email.php', 
			 data:{ "home_email": home_email, "_csrf": csrfToken},
			 success: function(result){
				var isVerified = result.indexOf('data-verify-success="1"') !== -1;
				$('#check_email_info').html(result);

				if (isVerified) {
					runEmailVerificationProgress();
					return;
				}

				$button.prop('disabled', false);
			 }, 
			 error: function(){
				$('#check_email_info').html('<div class="alert-box"><p><i class="fa fa-ban red" aria-hidden="true"></i> Verification failed. Please try again.</p></div>');
				$button.prop('disabled', false);
			 }                 
		  });

		  return false;
		} 
		
		function change_check_email_info(){
			$('.alert').fadeOut(500);
		} 	
		setInterval(change_check_email_info, 15000); 
		
</script>
