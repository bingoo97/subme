<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Technical Maintenance</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f6f8;
            --card: rgba(255, 255, 255, 0.88);
            --text: #12161f;
            --muted: #667085;
            --border: rgba(18, 22, 31, 0.08);
            --accent: #22c55e;
            --shadow: 0 24px 70px rgba(15, 23, 42, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: "Poppins", "Segoe UI", sans-serif;
            color: var(--text);
            background: #ffffff;
        }

        .maintenance-shell {
            width: min(100%, 640px);
        }

        .maintenance-card {
            padding: 40px 34px;
            border-radius: 28px;
            background: #ffffff;
            backdrop-filter: blur(10px);
            text-align: center;
        }

        .maintenance-mark {
            width: 72px;
            height: 72px;
            margin: 0 auto 24px;
            border-radius: 22px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #101828 0%, #1f2937 100%);
            color: #fff;
            font-size: 28px;
            font-weight: 700;
        }

        .maintenance-logo {
            max-width: 150px;
            max-height: 52px;
            width: auto;
            height: auto;
            margin: 0 auto 18px;
            display: block;
        }

        .maintenance-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(34, 197, 94, 0.12);
            color: #15803d;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .maintenance-label::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 0 6px rgba(34, 197, 94, 0.12);
        }

        h1 {
            margin: 0 0 14px;
            font-size: clamp(30px, 6vw, 44px);
            line-height: 1.05;
            font-weight: 700;
        }

        p {
            margin: 0 auto;
            max-width: 42ch;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <main class="maintenance-shell">
        <section class="maintenance-card" aria-labelledby="maintenance-title">
            {if isset($reseller.logo_url) && $reseller.logo_url neq ''}
                <img src="{$reseller.logo_url}" alt="{$reseller.name|default:$settings.site_name|default:'Site'}" class="maintenance-logo">
            {elseif isset($settings.page_logo) && $settings.page_logo neq ''}
                <img src="{$settings.page_logo}" alt="{$reseller.name|default:$settings.site_name|default:'Site'}" class="maintenance-logo">
            {else}
                <div class="maintenance-mark">!</div>
            {/if}
            <div class="maintenance-label">Technical maintenance</div>
            <h1 id="maintenance-title">We’re currently performing technical maintenance.</h1>
            <p>The site is temporarily unavailable while we make improvements. It will be back online again soon.</p>
        </section>
    </main>
</body>
</html>
