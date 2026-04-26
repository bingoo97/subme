 	<script>
		
		function check_payment(){
		  var code = $("#code").val(); 
		  $.ajax({
			 type: 'POST',
			 url: 'check_payment.php', 
			 data:{ "code": code },
			 success: function(result){
				$('#payment_content').html(result);
			 },                  
		  })  
		} 
		
		setInterval(check_payment, 25000);  
		
	</script> 