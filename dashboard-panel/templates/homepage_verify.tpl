 		<div id="page_verify" class="auth-shell">
        	<div class="email_homepage auth-card auth-card-verify center">
                {if $settings.page_logo}
                <div class="auth-logo-wrap">
        			<img src="{$settings.page_logo}" class="img-responsive auth-logo" alt="{$settings.page_name|default:$t.brand_fallback}" />
                </div>
                {/if}
                <h2 class="auth-title">Verification</h2>
                <div id="check_email_info"></div>
				<div class="clr"></div>
                <form class="form-horizontal" action="" method="post" onsubmit="check_email(); return false;">
                    <input type="hidden" name="_csrf" id="home_email_csrf" value="{$csrf_token|default:''}">
                    <div class="form-group">
                        <label for="inputEmail" class="control-label col-sm-4 hidden-xs"></label>
                        <div class="col-sm-12">
                           <input type="email" class="form-control" name="home_email" id="home_email" placeholder="Enter email..." value="" autocomplete="email" required="required">
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-dark btn-lg btn-block" id="home_email_login">
                           Enter <i class="fa fa-angle-double-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>
                <script>
					function typeWriter(element, text, speed) {
						let i = 0;
						function type() {
							if (i < text.length) {
								element.textContent += text.charAt(i);
								i++;
								setTimeout(type, speed);
							}
						}
						type();
					}

					// Override check_email function to use typing effect for error messages
					function check_email(){
						var home_email = $.trim($("#home_email").val());
						var csrfToken = String($("#home_email_csrf").val() || '');
						var $button = $("#home_email_login");
						var $checkEmailInfo = $('#check_email_info');

						if (!home_email) {
							$checkEmailInfo.html('<div class="alert-box"><p><i class="fa fa-ban red" aria-hidden="true"></i> <span class="typing-target"></span></p></div>');
							typeWriter($checkEmailInfo.find('.typing-target')[0], 'Enter your email address.', 2000 / 26);
							return false;
						}

						$button.prop('disabled', true);

						$.ajax({
							type: 'POST',
							url: 'check_email.php',
							data:{ "home_email": home_email, "_csrf": csrfToken},
							success: function(result){
								var isVerified = result.indexOf('data-verify-success="1"') !== -1;
								$checkEmailInfo.html(result);

								if (isVerified) {
									if (typeof runEmailVerificationProgress !== 'undefined') {
										runEmailVerificationProgress();
									}
								} else {
									// Apply typing effect to error message
									var $alertBox = $checkEmailInfo.find('.alert-box');
									var $firstParagraph = $alertBox.find('p').first();
									if ($firstParagraph.length && $firstParagraph.find('i').length) {
										var $icon = $firstParagraph.find('i').first().clone();
										var text = $firstParagraph.text().replace(/^\s*/, '').trim();
										$firstParagraph.html('').append($icon).append(' <span class="typing-target"></span>');
										typeWriter($checkEmailInfo.find('.typing-target')[0], text, 2000 / text.length);
									}
								}
								$button.prop('disabled', false);
							},
							error: function(){
								$checkEmailInfo.html('<div class="alert-box"><p class="typing-target"></p></div>');
								typeWriter($checkEmailInfo.find('.typing-target')[0], 'An error occurred. Please try again.', 2000 / 34);
								$button.prop('disabled', false);
							}
						});
					}

					$('#home_email').on('keydown', function(e){
						if(e.keyCode === 13) {
							e.preventDefault();
							check_email();
						}
					});
				</script>
            </div>
        </div>

        
        
