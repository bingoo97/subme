	$(document).ready(function(){
				$('#modal_payments').appendTo("body").modal('show');

                $('.btn-cat').click(function(e) {
                    $('.btn-cat').not(this).removeClass('active')
                        .siblings('input').prop('checked',false)
                    $(this).addClass('active')
                        .siblings('input').prop('checked',true)
                });
                $('.btn-type').click(function(e) {
                    $('.btn-type').not(this).removeClass('active')
                        .siblings('input').prop('checked',false)
                    $(this).addClass('active')
                        .siblings('input').prop('checked',true)
                });
    });