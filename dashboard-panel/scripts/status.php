<script>
        var status = <?php echo $statusval; ?>
	
        // Create socket variables
        if(status < 2 && status != -2){
			var addr =  document.getElementById("address").innerHTML;
			var timestamp = Math.floor(Date.now() / 1000)-5;
		//    var wsuri2 = "wss://www.blockonomics.co/payment/"+ addr+"?timestamp="+timestamp;
			var wsuri2 = "wss://www.blockonomics.co/payment/"+ addr;
			// Create socket and monitor
			var socket = new WebSocket(wsuri2)
            socket.onmessage = function(event){
                console.log(event.data);
                response = JSON.parse(event.data);
                //Refresh page if payment moved up one status
            }
        }
        
		
	function copy_address(){
	    var copyText = document.getElementById("copy_address");
	    copyText.select();
	    document.execCommand("Copy");
		setTimeout(function() {
			copyText.setAttribute('style', 'display:none;');
		}, 50);
		
	    $("#copy_alert").show();
		setTimeout(function() {
			$("#copy_alert").hide();
		}, 5000);
		
	}
	
	function copy_price(){
	    var copyText = document.getElementById("input-price");
	    copyText.select();
	    document.execCommand("Copy");
		 setTimeout(function() {
			copyText.setAttribute('style', 'display:none;');
		}, 50);
		
		var element = document.getElementById("price_btc");
		element.classList.add("active");
		$("#copy_alert_2").show();
		setTimeout(function() {
			$("#copy_alert_2").hide();
		}, 5000);
		setTimeout(function() {
			element.classList.remove("active");
		}, 1500); 
	} 
		
    </script>