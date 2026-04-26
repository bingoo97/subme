$(function () {
	$(document).off('click.userOrderModalOpen', '[data-order-modal-open]');
	$(document).on('click.userOrderModalOpen', '[data-order-modal-open]', function (event) {
		event.preventDefault();

		var target = $(this).attr('data-order-modal-open');
		if (!target) {
			return;
		}

		var $modal = $(target);
		if (!$modal.length) {
			return;
		}

		if (!$modal.parent().is('body')) {
			$modal.appendTo('body');
		}

		if (window.bootstrap && window.bootstrap.Modal) {
			window.bootstrap.Modal.getOrCreateInstance($modal[0]).show();
		} else if (typeof $modal.modal === 'function') {
			$modal.modal('show');
		} else {
			$modal.addClass('in show').show().attr('aria-hidden', 'false');
			$('body').addClass('modal-open');

			if (!$('.modal-backdrop').length) {
				$('<div class="modal-backdrop fade in show"></div>').appendTo('body');
			}
		}
	});

	$(document).off('click.userOrderModalClose', '.user-order-modal [data-dismiss="modal"], .user-order-modal');
	$(document).on('click.userOrderModalClose', '.user-order-modal [data-dismiss="modal"], .user-order-modal', function (event) {
		if ($(event.target).closest('.modal-dialog').length && !$(event.target).is('[data-dismiss="modal"]')) {
			return;
		}

		var $modal = $(this).closest('.user-order-modal');
		if (!$modal.length) {
			$modal = $(this);
		}

		if (window.bootstrap && window.bootstrap.Modal) {
			var instance = window.bootstrap.Modal.getInstance($modal[0]);
			if (instance) {
				instance.hide();
			} else {
				$modal.removeClass('in show').hide().attr('aria-hidden', 'true');
			}
		} else if (typeof $modal.modal === 'function') {
			$modal.modal('hide');
		} else {
			$modal.removeClass('in show').hide().attr('aria-hidden', 'true');
			$('body').removeClass('modal-open');
			$('.modal-backdrop').remove();
		}
	});

	$(document).off('click.ordersMenu', '.orders-menu-btn');
	$(document).on('click.ordersMenu', '.orders-menu-btn', function (event) {
		event.preventDefault();
		event.stopPropagation();

		var $menu = $(this).closest('.orders-mobile-menu');
		var isOpen = $menu.hasClass('is-open');

		$('.orders-mobile-menu').removeClass('is-open');
		$('.orders-menu-btn').attr('aria-expanded', 'false');

		if (!isOpen) {
			$menu.addClass('is-open');
			$(this).attr('aria-expanded', 'true');
		}
	});

	$(document).off('click.ordersMenuClose');
	$(document).on('click.ordersMenuClose', function () {
		$('.orders-mobile-menu').removeClass('is-open');
		$('.orders-menu-btn').attr('aria-expanded', 'false');
	});

	$(document).off('click.ordersMenuInner', '.orders-dropdown-menu');
	$(document).on('click.ordersMenuInner', '.orders-dropdown-menu', function (event) {
		event.stopPropagation();
	});
});
