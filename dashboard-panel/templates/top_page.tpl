<div class="top-instruction-bar">
  {if $topbar_payment_banner && $topbar_payment_banner.mode eq 'pending_crypto'}
  <a
    href="{$topbar_payment_banner.url|default:'/orders'}"
    class="top-instruction-bar__link top-instruction-bar__link--pending"
    title="{$t.topbar_pending_payment|default:'Pending payment'}"
    data-topbar-payment-link
  >
    <span class="top-instruction-bar__logo top-instruction-bar__logo--scan">
      <img src="/img/scan.gif" alt="Scan" />
    </span>
    <span class="top-instruction-bar__content">
      <span class="top-instruction-bar__text">{$t.topbar_pending_payment|default:'Pending payment'}</span>
      <span
        class="top-instruction-bar__countdown"
        data-topbar-countdown="{$topbar_payment_banner.remaining_seconds|default:0}"
        data-topbar-expired-label="{$t.payment_countdown_expired|default:'Payment cancelled'}"
      >60:00</span>
    </span>
    <span class="top-instruction-bar__arrow" aria-hidden="true">
      <i class="fa fa-angle-double-right"></i>
    </span>
  </a>
  {else}
  <a href="/instructions" class="top-instruction-bar__link" title="{$t.topbar_instructions|default:'Instructions'}">
    <span class="top-instruction-bar__logo">
      <i class="fa fa-btc" aria-hidden="true"></i>
    </span>
    <span class="top-instruction-bar__text">{$t.topbar_instructions|default:'Instructions'}</span>
    <span class="top-instruction-bar__arrow" aria-hidden="true">
      <i class="fa fa-angle-double-right"></i>
    </span>
  </a>
  {/if}
</div>

<nav class="navbar navbar-default navbar-user" role="navigation">
  <div class="navbar-header">
    <div class="navbar-user__brand-group">
      <a class="navbar-brand" href="/">{$reseller.name|default:$t.brand_fallback}</a>
      {if $user.logged && $user.customer_type|default:'client' eq 'reseller'}
        <span class="navbar-user__reseller-badge">
          <i class="fa fa-rocket" aria-hidden="true"></i>
          <span>RESELLER</span>
        </span>
      {/if}
    </div>

    <div class="navbar-actions">
      <div class="icon_login icon_login--news-alert">
        <a
          href="/news"
          class="news news-alert-link"
          title="{$t.news}"
          data-news-nav-link
          data-news-count="{$user.news_count|default:$user.news|default:0}"
          data-news-latest-at="{$user.news_latest_at|default:''}"
          data-news-user-id="{$user.id|default:0}"
          style="display:none;"
        >
          <i class="fa fa-bell-o" aria-hidden="true"></i>
          <span id="count_messages" class="label label-danger news-alert-link__badge" data-news-nav-badge style="display:none;">0</span>
        </a>
      </div>

      <div class="icon_settings">
        <a href="/" class="settings-link" title="{$t.settings}">
          <i class="fa fa-bars" aria-hidden="true"></i>
        </a>
      </div>
    </div>
  </div>
</nav>
<script>
$(function () {
  (function () {
    var $newsLink = $('[data-news-nav-link]');
    if (!$newsLink.length) {
      return;
    }

    var userId = String($newsLink.attr('data-news-user-id') || '0');
    var newsCount = parseInt($newsLink.attr('data-news-count'), 10) || 0;
    var latestAt = String($newsLink.attr('data-news-latest-at') || '');
    var storageKey = 'reseller_news_seen_' + userId;
    var pathName = String(window.location.pathname || '');
    var isNewsPage = pathName === '/news' || pathName.indexOf('/news-') === 0;
    var seenAt = '';

    function normalizeNewsDate(value) {
      var normalized = String(value || '').trim();
      if (!normalized) {
        return '';
      }

      if (normalized.indexOf('T') === -1) {
        normalized = normalized.replace(' ', 'T');
      }

      return normalized;
    }

    function parseNewsDate(value) {
      var normalized = normalizeNewsDate(value);
      if (!normalized) {
        return NaN;
      }

      return Date.parse(normalized);
    }

    try {
      seenAt = String(window.localStorage.getItem(storageKey) || '');
    } catch (error) {
      seenAt = '';
    }

    if (isNewsPage && latestAt) {
      try {
        window.localStorage.setItem(storageKey, latestAt);
      } catch (error) {}
      seenAt = latestAt;
    }

    var seenAtTs = parseNewsDate(seenAt);
    var latestAtTs = parseNewsDate(latestAt);
    var hasUnreadNews = newsCount > 0 && latestAt !== '' && (!seenAt || isNaN(seenAtTs) || (!isNaN(latestAtTs) && seenAtTs < latestAtTs));
    var $badge = $('[data-news-nav-badge]');
    var $homeCard = $('[data-news-home-card]');
    var $homeBadge = $('[data-news-home-badge]');

    if (hasUnreadNews) {
      $newsLink.css('display', 'inline-flex').addClass('is-active');
      $badge.text(newsCount).show();
      $homeCard.addClass('color_alert has-news-attention');
      $homeBadge.text(newsCount).show();
    } else {
      $newsLink.hide().removeClass('is-active');
      $badge.text('0').hide();
      $homeCard.removeClass('color_alert has-news-attention');
      $homeBadge.text('0').hide();
    }
  })();

  $('[data-topbar-countdown]').each(function () {
    var element = this;
    var $element = $(element);
    var remainingSeconds = parseInt($element.attr('data-topbar-countdown'), 10) || 0;
    var expiredLabel = String($element.attr('data-topbar-expired-label') || 'Payment cancelled');
    var hasExpired = false;

    function renderCountdown() {
      var minutes = Math.floor(remainingSeconds / 60);
      var seconds = remainingSeconds % 60;
      $element.text(String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0'));
    }

    if (remainingSeconds <= 0) {
      $element.text(expiredLabel);
      return;
    }

    renderCountdown();

    window.setInterval(function () {
      remainingSeconds -= 1;

      if (remainingSeconds <= 0) {
        remainingSeconds = 0;
        if (!hasExpired) {
          hasExpired = true;
          $element.text(expiredLabel);
          window.setTimeout(function () {
            window.location.reload();
          }, 900);
        }
        return;
      }

      renderCountdown();
    }, 1000);
  });
});
</script>
