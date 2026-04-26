<script type="text/javascript">
var url = <?php echo json_encode($ustawienia["url_strony"]."/nagrody"); ?>;
function odliczaj(n){
    n--;
    s = n%60; 
	m = Math.floor((n%3600)/60);  
    if (n == 0){
        document.getElementById('time').innerHTML = '';
		window.location = url;
    }else{
        document.getElementById('time').innerHTML = '' + ((m < 10) ? '0' + m : m)+ ':' +((s < 10) ? '0' + s : s);
        if(n >= 0)
            setTimeout("odliczaj(" + n + ")", 1000);
    }
}
window.onload=function () { odliczaj('90'); }
</script>