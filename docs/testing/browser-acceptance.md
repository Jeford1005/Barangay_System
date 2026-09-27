# Browser and accessibility acceptance

The repository includes a Playwright smoke/accessibility gate for the public shell:

```bash
npm run test:browser
```

The suite starts `php artisan serve` when `http://127.0.0.1:8000/up` is not already available. Set `BROWSER_BASE_URL` to test another environment. Browser binaries must be installed separately with `npx playwright install chromium`.

The current automated gate covers the public login shell, landmark presence, a serious/critical axe scan, and the liveness endpoint. Authenticated acceptance cases for account linking, household/head forms, resident self-service, archive behavior, print output, and mobile overflow should be run against a disposable seeded database before deployment. Do not run the suite against production data.
