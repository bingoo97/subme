# Users, profiles and global search deployment

Deploy only the six runtime files in files.txt to demo.subme.pro and dashboard.subme.pro. No database migrations, imports, credentials, uploads, sessions, vendor or environment files are included.

The deployment requires live file hashes to match the previous release (43f6bd5a9d9869e9fc2304375519ef8a94ce9ffc), backs up replaced code separately for each site and installs an exact Git revision. Hosting checkout is not reset or checked out. Protected configuration, path pointers, .htaccess and cron are verified unchanged. Existing settings and provider configuration fingerprints are checked; both provider switches stay OFF.

Local validation: PHP/JavaScript syntax, 30 isolated page renders, search/notification/messenger regressions and visual checks at 320, 390, 768 and 1440 pixels. Production checks use read-only probes and authenticated GET requests with an existing administrator session; they do not submit payments or modify orders.

Deploy demo first, run probe.php and http-check.php, then repeat for dashboard. Keep private per-site backups under ~/.subme-deploy-backups/users-search-20261018-REVISION/SITE. Record actual revision and results in RESULT.md.
