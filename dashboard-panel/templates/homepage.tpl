<div class="balance">
    <div class="info">
		<p class="amount">{$user.balance_amount|default:'0.00'} {$reseller.currency_symbol|default:$reseller.currency_short}</p>
		<p class="balance-label">{$t.account_balance_label|default:'Account balance'}</p>
		{if $settings.active_sale == 1}
			{if $balance_topup_enabled|default:false}
				<button type="button" class="balance-topup-link" data-toggle="modal" data-target="#balanceTopupModal" title="{$t.top_up|default:'Top up'}">{$t.top_up|default:'Top up'}</button>
			{else}
				<a href="{$balance_topup_action_url|default:'/cryptocurrency'}" title="{$t.top_up|default:'Top up'}">{$t.top_up|default:'Top up'}</a>
			{/if}
		{else}
			<span class="balance-note text-muted">{$t.sales_disabled_notice|default:'Sales are currently unavailable.'}</span>
		{/if}
	</div>
</div>
{include file='alert.tpl'}
<div class="home_buttons">
	<div class="col-md-12">
		<a href="/news" title="{$t.home_welcome_news}">
			<div class="one_box" data-news-home-card>
				<i class="fa fa-file-text-o" aria-hidden="true"></i>
				<p class="title">{$t.home_welcome_news} <span class="btn btn-xs" data-news-home-badge style="display:none;">0</span></p>
			</div>
		</a>
    </div>
	<div class="col-sm-12">
		<a href="/orders" title="{$t.orders}">
			<div class="one_box one_box--orders-attention">
				<i class="fa fa-tasks" aria-hidden="true"></i>
				<p class="title">{$t.orders}</p>
			</div>
		</a>
    </div>
	<div class="col-sm-12">
		<a href="/instructions" title="{$t.instructions|default:'Instructions'}">
			<div class="one_box">
				<i class="fa fa-book" aria-hidden="true"></i>
				<p class="title">{$t.instructions|default:'Instructions'}</p>
			</div>
		</a>
    </div>
	{if $settings.apps_page_enabled}
	<div class="col-sm-12">
		<a href="/apps" title="{$t.menu_apps|default:'Apps'}">
			<div class="one_box">
				<i class="fa fa-television" aria-hidden="true"></i>
				<p class="title">{$t.menu_apps|default:'Apps'}</p>
			</div>
		</a>
    </div>
	{/if}
	<div class="col-sm-12">
		<a href="/history" title="{$t.history}">
			<div class="one_box">
				<i class="fa fa-history" aria-hidden="true"></i>
				<p class="title">{$t.history}</p>
			</div>
		</a>
    </div>
	{if $settings.referrals_enabled}
	<div class="col-sm-12">
		<a href="/referrals" title="{$t.menu_referrals}">
			<div class="one_box">
				<i class="fa fa-sitemap" aria-hidden="true"></i>
				<p class="title">{$t.menu_referrals}</p>
			</div>
		</a>
    </div>
	{/if}
	<div class="col-sm-12">
		<a href="/settings" title="{$t.settings}">
			<div class="one_box">
				<i class="fa fa-cog" aria-hidden="true"></i>
				<p class="title">{$t.settings}</p>
			</div>
		</a>
    </div>
	<div class="col-sm-12">
		<a href="/logout" title="{$t.logout}">
			<div class="one_box logout">
				<i class="fa fa-unlock-alt" aria-hidden="true"></i>
				<p class="title">{$t.logout}</p>
			</div>
		</a>
	</div>
</div>
{if $user.logged && $settings.customer_type_switch_enabled}
    <div class="home-role-switch">
        <div class="alert alert-dismissible alert-info">
            <button type="button" class="close" data-dismiss="alert">x</button>
            <i class="fa fa-info-circle" aria-hidden="true"></i>
            {$t.customer_type_switch_info|default:'This is a test switch for your account view. OFF shows the regular client mode, while ON switches your account into reseller mode so you can preview reseller-only sections.'}
        </div>
        <form action="/" method="post" class="home-role-switch__form">
            <input type="hidden" name="_csrf" value="{$csrf_token|default:''}">
            <input type="hidden" name="customer_type_switch_submit" value="1">
            <div class="home-role-switch__card">
                <div class="home-role-switch__copy">
                    <strong>{$t.customer_type_switch_title|default:'Client / reseller test mode'}</strong>
                    <p>{$t.customer_type_switch_current|default:'Current mode'}: <span class="home-role-switch__current">{if $user.customer_type|default:'client' eq 'reseller'}{$t.customer_type_switch_mode_reseller|default:'Reseller'}{else}{$t.customer_type_switch_mode_client|default:'Client'}{/if}</span></p>
                    <div class="home-role-switch__meta">
                        <span>{$t.customer_type_switch_off_label|default:'OFF = Client'}</span>
                        <span>{$t.customer_type_switch_on_label|default:'ON = Reseller'}</span>
                    </div>
                </div>
                <label class="home-role-switch__control" for="customer_type_mode">
                    <span class="sr-only">{$t.customer_type_switch_title|default:'Client / reseller test mode'}</span>
                    <input type="checkbox" id="customer_type_mode" name="customer_type_mode" value="reseller"{if $user.customer_type|default:'client' eq 'reseller'} checked{/if}>
                    <span class="home-role-switch__toggle" aria-hidden="true">
                        <span class="home-role-switch__toggle-text home-role-switch__toggle-text--off">OFF</span>
                        <span class="home-role-switch__toggle-track">
                            <span class="home-role-switch__toggle-thumb"></span>
                        </span>
                        <span class="home-role-switch__toggle-text home-role-switch__toggle-text--on">ON</span>
                    </span>
                </label>
            </div>
        </form>
        <script>
            (function () {
                var form = document.querySelector('.home-role-switch__form');
                var toggle = document.getElementById('customer_type_mode');
                if (!form || !toggle) {
                    return;
                }

                toggle.addEventListener('change', function () {
                    form.submit();
                });
            })();
        </script>
    </div>
{/if}
{if $homepage_onboarding_enabled|default:false}
<div class="homepage-onboarding" data-homepage-onboarding data-user-id="{$user.id|default:0}" data-delay="3000" data-cooldown-ms="86400000" hidden>
    <div class="homepage-onboarding__step is-active" data-homepage-onboarding-step="0">
        <div class="homepage-onboarding__inner">
            <div class="homepage-onboarding__visual" aria-hidden="true">
                <span class="homepage-onboarding__visual-shell">
                    <i class="fa fa-credit-card" aria-hidden="true"></i>
                </span>
            </div>
            <div class="homepage-onboarding__dots" aria-hidden="true">
                <span class="is-active"></span><span></span><span></span><span></span>
            </div>
            <h2 class="homepage-onboarding__title">{$t.home_onboarding_step1_title|default:'Wybierz rodzaj subskrypcji'}</h2>
            <p class="homepage-onboarding__text">{$t.home_onboarding_step1_text|default:'Kliknij w przycisk Zamówienia, a potem Dodaj nową subskrypcję. Wybierasz rodzaj pakietu, formę płatności i przechodzisz dalej gotowym procesem.'}</p>
            <button type="button" class="btn btn-dark btn-lg homepage-onboarding__button" data-homepage-onboarding-next>{$t.home_onboarding_next|default:'Dalej'}</button>
        </div>
    </div>
    <div class="homepage-onboarding__step" data-homepage-onboarding-step="1">
        <div class="homepage-onboarding__inner">
            <div class="homepage-onboarding__visual" aria-hidden="true">
                <span class="homepage-onboarding__visual-shell">
                    <i class="fa fa-tasks" aria-hidden="true"></i>
                </span>
            </div>
            <div class="homepage-onboarding__dots" aria-hidden="true">
                <span class="is-active"></span><span class="is-active"></span><span></span><span></span>
            </div>
            <h2 class="homepage-onboarding__title">{$t.home_onboarding_step2_title|default:'Skorzystaj z doładowania salda'}</h2>
            <p class="homepage-onboarding__text">{$t.home_onboarding_step2_text|default:'Jeśli chcesz mieć środki na koncie do zakupu kilku subskrypcji, skorzystaj z doładowania salda. Dzięki temu kolejne zakupy wykonasz szybciej i bez generowania nowej płatności za każdym razem.'}</p>
            <button type="button" class="btn btn-dark btn-lg homepage-onboarding__button" data-homepage-onboarding-next>{$t.home_onboarding_next|default:'Dalej'}</button>
        </div>
    </div>
    <div class="homepage-onboarding__step" data-homepage-onboarding-step="2">
        <div class="homepage-onboarding__inner">
            <div class="homepage-onboarding__visual" aria-hidden="true">
                <span class="homepage-onboarding__visual-shell">
                    <i class="fa fa-check-square-o" aria-hidden="true"></i>
                </span>
            </div>
            <div class="homepage-onboarding__dots" aria-hidden="true">
                <span class="is-active"></span><span class="is-active"></span><span class="is-active"></span><span></span>
            </div>
            <h2 class="homepage-onboarding__title">{$t.home_onboarding_step3_title|default:'Opłać i dokończ zamówienie'}</h2>
            <p class="homepage-onboarding__text">{$t.home_onboarding_step3_text|default:'Po wygenerowaniu płatności opłać ją wybraną metodą i wróć do swojego zamówienia. Gdy płatność zostanie zatwierdzona, subskrypcja pojawi się na Twoim koncie.'}</p>
            <button type="button" class="btn btn-dark btn-lg homepage-onboarding__button" data-homepage-onboarding-next>{$t.home_onboarding_next|default:'Dalej'}</button>
        </div>
    </div>
    <div class="homepage-onboarding__step" data-homepage-onboarding-step="3">
        <div class="homepage-onboarding__inner">
            <div class="homepage-onboarding__visual" aria-hidden="true">
                <span class="homepage-onboarding__visual-shell">
                    <i class="fa fa-comments-o" aria-hidden="true"></i>
                </span>
            </div>
            <div class="homepage-onboarding__dots" aria-hidden="true">
                <span class="is-active"></span><span class="is-active"></span><span class="is-active"></span><span class="is-active"></span>
            </div>
            <h2 class="homepage-onboarding__title">{$t.home_onboarding_step4_title|default:'Masz problemy? Napisz na Live Chat!'}</h2>
            <p class="homepage-onboarding__text">{$t.home_onboarding_step4_text|default:'W przypadku problemów poniżej znajduje się komunikator do kontaktu. Jeśli coś nie działa albo nie wiesz co dalej, napisz do nas od razu na czacie.'}</p>
            <button type="button" class="btn btn-dark btn-lg homepage-onboarding__button" data-homepage-onboarding-finish>{$t.home_onboarding_finish|default:'Rozpocznij'}</button>
        </div>
    </div>
</div>
<script>
    (function () {
        var overlay = document.querySelector('[data-homepage-onboarding]');
        if (!overlay) {
            return;
        }

        var userId = overlay.getAttribute('data-user-id') || '0';
        var delay = parseInt(overlay.getAttribute('data-delay') || '3000', 10);
        var cooldownMs = parseInt(overlay.getAttribute('data-cooldown-ms') || '86400000', 10);
        var storageKey = 'homepage-onboarding-completed-' + userId;
        var steps = Array.prototype.slice.call(overlay.querySelectorAll('[data-homepage-onboarding-step]'));
        var nextButtons = overlay.querySelectorAll('[data-homepage-onboarding-next]');
        var finishButton = overlay.querySelector('[data-homepage-onboarding-finish]');
        var activeIndex = 0;

        function storageAvailable(type) {
            try {
                var storage = window[type];
                var probeKey = '__homepage_onboarding_probe__';
                storage.setItem(probeKey, '1');
                storage.removeItem(probeKey);
                return storage;
            } catch (error) {
                return null;
            }
        }

        var localStore = storageAvailable('localStorage');

        function wasCompletedRecently() {
            if (!localStore) {
                return false;
            }

            var rawValue = localStore.getItem(storageKey);
            var timestamp = rawValue ? parseInt(rawValue, 10) : 0;
            return timestamp > 0 && (Date.now() - timestamp) < cooldownMs;
        }

        function persistCompletion() {
            if (!localStore) {
                return;
            }
            localStore.setItem(storageKey, String(Date.now()));
        }

        function renderStep(index) {
            activeIndex = index;
            steps.forEach(function (step, stepIndex) {
                step.classList.toggle('is-active', stepIndex === index);
            });
            playStepAnimation(steps[index] || null);
        }

        function restartAnimation(node, animationClass, duration) {
            if (!node) {
                return;
            }

            node.classList.remove('animated', animationClass, 'homepage-onboarding__animate', 'homepage-onboarding__animate--slow');
            void node.offsetWidth;
            node.classList.add('animated', animationClass, 'homepage-onboarding__animate');
            if (duration === 'slow') {
                node.classList.add('homepage-onboarding__animate--slow');
            }
        }

        function playStepAnimation(step) {
            if (!step) {
                return;
            }

            restartAnimation(step.querySelector('.homepage-onboarding__visual-shell'), 'flipInX', 'slow');
            restartAnimation(step.querySelector('.homepage-onboarding__dots'), 'fadeIn', 'slow');
            restartAnimation(step.querySelector('.homepage-onboarding__title'), 'fadeIn', 'slow');
            restartAnimation(step.querySelector('.homepage-onboarding__text'), 'fadeIn', 'slow');
            restartAnimation(step.querySelector('.homepage-onboarding__button'), 'fadeIn', 'slow');
        }

        function openWizard() {
            overlay.hidden = false;
            window.requestAnimationFrame(function () {
                overlay.classList.add('is-visible');
                document.body.classList.add('homepage-onboarding-open');
                renderStep(0);
            });
        }

        function closeWizard(markCompleted) {
            overlay.classList.remove('is-visible');
            document.body.classList.remove('homepage-onboarding-open');
            if (markCompleted) {
                persistCompletion();
            }
            window.setTimeout(function () {
                overlay.hidden = true;
            }, 650);
        }

        nextButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var nextIndex = Math.min(activeIndex + 1, steps.length - 1);
                renderStep(nextIndex);
            });
        });

        if (finishButton) {
            finishButton.addEventListener('click', function () {
                closeWizard(true);
            });
        }

        if (!wasCompletedRecently()) {
            window.setTimeout(openWizard, Math.max(0, delay));
        }
    })();
</script>
{/if}
{if $balance_topup_enabled|default:false}
	{include file='profil/balance_topup_modal.tpl'}
{/if}
