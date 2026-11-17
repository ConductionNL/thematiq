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
- [x] 8 A look at the form on a running instance.
      Live pass 17 Nov 2026 (NC 34.0.6): the box enables the colour; with #1f883d the samples show
      the main button and its hover (#1a7434) in green with black text, the readout adds "Text on the
      main button: 4.65:1, needs 4.5:1", and the created set (`custom-lp-action-green`) declares the
      action colour: applied, the main buttons on the instance are green.
