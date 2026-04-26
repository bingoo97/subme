	<script type="text/javascript">
			$(function(){
				$("#przypomnij").click(function() {
					var haslo_email = $("#haslo_email").val();
					$.post('ajax.php',{haslo_email:haslo_email},function(data){
						$('#haslo_info').html(data);
					});
				});
				
			});
			$(function(){
				$("#dodaj_uzytkownika").click(function() {
					var email = $("#email").val();
					$.post('ajax.php',{email:email},function(data){
						$('#status_uzytkownika').html(data);
					});
				});
				
			}); 
	</script>