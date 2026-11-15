# Tasks: brand-form-action-colour

- [x] 1 `scripts/mapping/brand-form.json`: three rules that read `action`.
- [x] 2 `BrandFormService::derive()` and `js/lib/brandForm.js`: the optional third colour; its
      rules are left out without it.
- [x] 3 `BrandFormController::create()`: the `action` parameter, and the action text and ratio in
      the answer.
- [x] 4 `templates/settings/admin.php` and `js/admin.js`: the box, the colour, the samples, the
      readout, the post.
- [x] 5 Strings in `l10n/en.json` and `l10n/nl.json`; the other locales backfilled.
- [x] 6 The parity fixture with three action cases; `BrandFormServiceTest`, `brandForm.spec.js`,
      `admin-brand-form.spec.js`.
- [x] 7 `composer check:strict` once, `npm run lint`.
- [ ] 8 A look at the form on a running instance.
