<script>
$(document).ready(function(){
    $('#navbar-search input[type="text"]').on("keyup input", function(){
        /* Get input value on change */
        var inputVal = $(this).val();
        var resultDropdown = $(this).siblings(".search_result");
        if(inputVal.length){
            $.get("search.php", {search_value: inputVal}).done(function(data){
                $(".search_result").show();
                resultDropdown.html(data);
            });
        } else{
            resultDropdown.empty();
			$(".search_result").hide();
        }
    });
    
    // Set search input value on click of result item
    $(document).on("click", ".search_result a", function(){
        $(this).parents("#navbar-search").find('input[type="text"]').val($(this).text());
        $(this).parent(".search_result").empty();
		$(".search_result").hide();
    });
});
</script>
