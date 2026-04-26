function productPickerState() {
	var hiddenInput = $('#id_product');
	var activeButton = $('.order-product-picker__option.is-active').first();
	return {
		hiddenInput: hiddenInput,
		activeButton: activeButton,
		hasProduct: $.trim(hiddenInput.val()) !== ''
	};
}

function updateProductDescription() {
	var state = productPickerState();
	var description = state.activeButton.data('description');
	var title = state.activeButton.data('product-title');
	var price = state.activeButton.data('product-price');
	var hasProduct = state.hasProduct;
	var addForm = $('#add_product').closest('form');
	var choiceLabel = $.trim(addForm.data('choice-label') || 'Your choice:');
	var priceLabel = $.trim(addForm.data('price-label') || 'Purchase price:');

	if (typeof description !== 'string') {
		description = '';
	}
	if (typeof title !== 'string') {
		title = '';
	}
	if (typeof price !== 'string') {
		price = '';
	}

	description = $.trim(description);
	title = $.trim(title);
	price = $.trim(price);
	$('#add_product').prop('disabled', !hasProduct);

	if (!hasProduct) {
		$('#product_description_title').text('');
		$('#product_description').text('');
		$('#product_description_wrap').hide();
		$('#selected_product_price_note').text('').hide();
		return;
	}

	$('#product_description_title').text(choiceLabel + ' ' + title);
	if (description !== '') {
		$('#product_description').text(description);
		$('#product_description').show();
		$('#product_description_wrap').show();
	} else {
		$('#product_description').text('').hide();
		$('#product_description_wrap').show();
	}

	if (price !== '') {
		$('#selected_product_price_note').text(priceLabel + ' ' + price).show();
	} else {
		$('#selected_product_price_note').text('').hide();
	}
}

function selectProductOption(button) {
	var option = $(button);
	var productId = $.trim(option.data('product-id'));

	$('.order-product-picker__option').removeClass('is-active');
	option.addClass('is-active');
	$('#id_product').val(productId);
	updateProductDescription();
}

function check_product() {
	var id_provider = $("#id_provider").val();

	$.ajax({
		type: 'POST',
		url: 'check_product.php',
		data: { "id_provider": id_provider },
		success: function(result) {
			$('#select_product').html(result);
			$('#select_input').prop('disabled', true);
			$('#add_product').prop('disabled', true);
			$('#select_product').find('.order-product-picker__option').on('click', function() {
				selectProductOption(this);
			});
			updateProductDescription();
		},
		error: function() {
			$('#select_product').html('<div class="form-group"><div class="col-lg-6"><p>Unable to load products.</p></div></div>');
			$('#add_product').prop('disabled', true);
		}
	});
}

function autoLoadSingleProvider() {
	var providerSelect = $('#id_provider');
	if (!providerSelect.length) {
		return;
	}

	var providerOptions = providerSelect.find('option').filter(function() {
		return $.trim($(this).val()) !== '' && $(this).val() !== '0';
	});

	if (providerOptions.length !== 1) {
		return;
	}

	var providerId = $.trim(providerOptions.first().val());
	if (providerId === '') {
		return;
	}

	if ($.trim(providerSelect.val()) !== providerId) {
		providerSelect.val(providerId);
	}

	if ($('#select_product').find('.order-product-picker__option').length === 0) {
		check_product();
	}
}

$(document).ready(function() {
	autoLoadSingleProvider();
});
