(function () {
	'use strict';

	function resolveTarget(trigger) {
		if (!trigger) {
			return null;
		}

		var selector = trigger.getAttribute('data-bs-target')
			|| trigger.getAttribute('data-target')
			|| trigger.getAttribute('href');

		if (!selector || selector === '#' || selector.indexOf('#') !== 0) {
			return null;
		}

		try {
			return document.querySelector(selector);
		} catch (error) {
			return null;
		}
	}

	function showModal(trigger) {
		var target = resolveTarget(trigger);
		if (!target) {
			return;
		}

		if (window.bootstrap && window.bootstrap.Modal) {
			window.bootstrap.Modal.getOrCreateInstance(target).show();
			return;
		}

		target.classList.add('show');
		target.style.display = 'block';
		target.removeAttribute('aria-hidden');
		document.body.classList.add('modal-open');

		if (!document.querySelector('.modal-backdrop')) {
			var backdrop = document.createElement('div');
			backdrop.className = 'modal-backdrop fade show';
			document.body.appendChild(backdrop);
		}
	}

	function hideModal(modal) {
		if (!modal) {
			return;
		}

		if (window.bootstrap && window.bootstrap.Modal) {
			var instance = window.bootstrap.Modal.getInstance(modal);
			if (instance) {
				instance.hide();
				return;
			}
		}

		modal.classList.remove('show');
		modal.style.display = 'none';
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('modal-open');
		var backdrop = document.querySelector('.modal-backdrop');
		if (backdrop) {
			backdrop.remove();
		}
	}

	function showTab(trigger) {
		if (!trigger) {
			return;
		}

		if (window.bootstrap && window.bootstrap.Tab) {
			window.bootstrap.Tab.getOrCreateInstance(trigger).show();
			return;
		}

		var target = resolveTarget(trigger);
		var tabList = trigger.closest('.nav, .nav-tabs');
		var tabContent = target ? target.closest('.tab-content') : null;

		if (tabList) {
			Array.prototype.forEach.call(tabList.querySelectorAll('[data-toggle="tab"], [data-bs-toggle="tab"]'), function (link) {
				link.parentElement && link.parentElement.classList.remove('active');
				link.classList.remove('active');
				link.setAttribute('aria-selected', 'false');
			});
		}

		if (tabContent) {
			Array.prototype.forEach.call(tabContent.querySelectorAll('.tab-pane'), function (pane) {
				pane.classList.remove('active', 'in', 'show');
			});
		}

		if (trigger.parentElement) {
			trigger.parentElement.classList.add('active');
		}
		trigger.classList.add('active');
		trigger.setAttribute('aria-selected', 'true');

		if (target) {
			target.classList.add('active', 'show', 'in');
		}
	}

	function closeAlert(trigger) {
		var alertBox = trigger ? trigger.closest('.alert') : null;
		if (!alertBox) {
			return;
		}

		if (window.bootstrap && window.bootstrap.Alert) {
			window.bootstrap.Alert.getOrCreateInstance(alertBox).close();
			return;
		}

		alertBox.remove();
	}

	function toggleCollapse(trigger) {
		var target = resolveTarget(trigger);
		if (!target) {
			return;
		}

		if (window.bootstrap && window.bootstrap.Collapse) {
			window.bootstrap.Collapse.getOrCreateInstance(target, { toggle: true });
			return;
		}

		var isOpen = target.classList.contains('show') || target.classList.contains('in') || target.style.display === 'block';
		target.classList.toggle('show', !isOpen);
		target.classList.toggle('in', !isOpen);
		target.style.display = isOpen ? 'none' : 'block';
	}

	// jQuery tooltip compatibility for Bootstrap 5
	if (window.jQuery && window.bootstrap && window.bootstrap.Tooltip) {
		jQuery.fn.tooltip = function (option) {
			return this.each(function () {
				var element = this;
				var instance = window.bootstrap.Tooltip.getInstance(element);
				if (!instance) {
					window.bootstrap.Tooltip.getOrCreateInstance(element, option);
				}
			});
		};
	}

	document.addEventListener('click', function (event) {
		var modalTrigger = event.target.closest('[data-toggle="modal"], [data-bs-toggle="modal"]');
		if (modalTrigger) {
			event.preventDefault();
			showModal(modalTrigger);
			return;
		}

		var dismissTrigger = event.target.closest('[data-dismiss="modal"], [data-bs-dismiss="modal"]');
		if (dismissTrigger) {
			event.preventDefault();
			hideModal(dismissTrigger.closest('.modal'));
			return;
		}

		var alertDismiss = event.target.closest('[data-dismiss="alert"], [data-bs-dismiss="alert"]');
		if (alertDismiss) {
			event.preventDefault();
			closeAlert(alertDismiss);
			return;
		}

		var modalBackdropClick = event.target.classList.contains('modal') ? event.target : null;
		if (modalBackdropClick) {
			hideModal(modalBackdropClick);
			return;
		}

		var tabTrigger = event.target.closest('[data-toggle="tab"], [data-bs-toggle="tab"]');
		if (tabTrigger) {
			event.preventDefault();
			showTab(tabTrigger);
			return;
		}

		var collapseTrigger = event.target.closest('[data-toggle="collapse"], [data-bs-toggle="collapse"]');
		if (collapseTrigger) {
			event.preventDefault();
			toggleCollapse(collapseTrigger);
		}
	});
})();
