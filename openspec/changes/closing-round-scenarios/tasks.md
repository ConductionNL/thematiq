# Tasks: closing-round-scenarios

## 1. Tests that keep the rows true
- [ ] 1.1 Extend the admin settings template test in `tests/Unit/`: the App view of the preview renders `.nl-mini__navbar` and a `.nl-mini__menu` with labelled entries, one of them `--active` (app-live-preview-shell)
- [ ] 1.2 Extend `tests/Unit/Controller/FontControllerTest.php`: `list()` returns every stored font with its name, role and size, and `js/admin.js` renders a delete action per entry in `#nldesign-font-list` (typ-font-list-delete)
- [ ] 1.3 Extend `tests/Unit/Service/CssInjectionServiceTest.php` or the overrides service test: with `utrecht` active and one override saved, the override wins and every other token keeps the set's value, and `css/tokens/utrecht.css` is unchanged (cat-starter-templates)
- [ ] 1.4 Extend `tests/Unit/Service/ThemeGalleryServiceTest.php`: an index entry for a water board is listed like a province entry (cat-provincial-sets)

## 2. Matrix
- [ ] 2.1 When the tasks are done, archive this change; the four rows keep their refs to the main spec headings
