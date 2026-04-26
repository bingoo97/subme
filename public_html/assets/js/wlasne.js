
//========================
//TOOLTIP
//========================
$(document).ready(function(){
   	$('[data-toggle="tooltip"]').tooltip()

    //Wyświetlanie podpowiedzi u góry
    $(".tip-top").tooltip({
        placement : 'top'
    });
    //Wyświetlanie podpowiedzi u góry
    $(".tip-right").tooltip({
        placement : 'right'
    });
    //Wyświetlanie podpowiedzi u góry
    $(".tip-bottom").tooltip({
        placement : 'bottom'
    });
    //Wyświetlanie podpowiedzi u góry
    $(".tip-left").tooltip({
        placement : 'left'
    });
});

$(window).load(function(){
	$('img').bind('contextmenu', function(e){
		return false;
	}); 
	$.cookieBar({}); 
//	$("#chat").scrollTop($("#chat")[0].scrollHeight);
 });


 


 
