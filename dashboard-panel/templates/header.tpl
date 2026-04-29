<!DOCTYPE html>
<html lang="{$html_lang|default:'en'}">
<head>
	<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<title>{if isset($reseller.name) && $reseller.name}{$reseller.name}{else}{$settings.page_title|default:$t.brand_fallback}{/if}</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="description" content="{$settings.page_desc|default:''}" />
	<meta name="keywords" content="{$settings.page_keywords|default:''}" />
    <meta name="author" content="{$settings.page_name|default:''}">

	<meta property="og:type" content="article" />
    <meta property="og:url" content="{$settings.page_url|default:''}" />
	<meta property="og:title" content="{$settings.page_name|default:''}" />
	<meta property="og:description" content="{$settings.page_desc|default:''}" />
	<meta property="og:image" content="{if $settings.page_logo}{$settings.page_logo}{else}{$settings.page_url|default:''}/img/logo_bingo.png{/if}" />
	<meta property="og:image:secure_url" content="{if $settings.page_logo}{$settings.page_logo}{else}{$settings.page_url|default:''}/img/logo_bingo.png{/if}" />
	<meta property="og:image:type" content="image/jpg" />
	<meta property="og:image:width" content="400" />
	<meta property="og:image:height" content="300" />

	<link rel="icon" type="image/x-icon" href="/favicon/favicon.ico">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
	<link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="192x192" href="/favicon/android-chrome-192x192.png">
	<link rel="icon" type="image/png" sizes="512x512" href="/favicon/android-chrome-512x512.png">
	<link rel="manifest" href="/favicon/site.webmanifest">

    <link href="https://fonts.googleapis.com/css?family=Poppins:500,600,700" rel="stylesheet">

    <link rel="stylesheet" href="/assets/vendor/bootstrap5/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/assets/css/bootstrap5-legacy-bridge.css">
	<link rel="stylesheet" href="/assets/css/style.css?v={$main_asset_version|default:1}">
    <link rel="stylesheet" href="/assets/css/animate.css">
    <link rel="stylesheet" href="/assets/css/jquery.cookiebar.css">
    {if isset($user) && $user.logged}
    <link rel="stylesheet" href="/assets/css/messanger.css?v={$chat_asset_version|default:1}">
    {/if}

    <script type="text/javascript" src="/assets/js/jquery-1.12.4.min.js"></script>

	{if isset($user) && $user.logged}
    <script src="/assets/js/add_new.js?v={$main_asset_version|default:1}"></script>
	{/if}

    <script src="/assets/js/loader.js"></script>
    <script>
        (function () {
            var lastTouchEndAt = 0;

            function preventZoomGesture(event) {
                event.preventDefault();
            }

            function preventMultiTouchZoom(event) {
                if (event.touches && event.touches.length > 1) {
                    event.preventDefault();
                }
            }

            function preventDoubleTapZoom(event) {
                var now = Date.now();
                if (now - lastTouchEndAt <= 300) {
                    event.preventDefault();
                }
                lastTouchEndAt = now;
            }

            document.addEventListener('gesturestart', preventZoomGesture, { passive: false });
            document.addEventListener('gesturechange', preventZoomGesture, { passive: false });
            document.addEventListener('gestureend', preventZoomGesture, { passive: false });
            document.addEventListener('touchmove', preventMultiTouchZoom, { passive: false });
            document.addEventListener('touchend', preventDoubleTapZoom, { passive: false });
        })();
    </script>
</head>
<body>
