# Tasks: header-buttons-follow-shape

Built in ee82d7c (css/app-menu.css, css/header-workplace.css, js/app-menu.js, vitest, e2e); verified live and archived by the done-thematiq lane: vitest appMenuSettings + headerWorkplace 21/21, e2e header-buttons-follow-shape 4/4 on NC 34.0.6 + PostgreSQL 16 + nginx/php-fpm 8.3 + APCu/Redis, eslint and stylelint clean on the changed files.

## 1. The shape on the page

- [x] 1.1 `js/app-menu.js`: write the checked `shape` on `document.documentElement` as
      `data-thematiq-shape` when the script starts with the workplace style. REQ-TQMS-001.
- [x] 1.2 vitest `appMenuSettings.spec.js`: the root carries square and round, round for a value that
      is not allowed, no attribute with the classic style.

## 2. The rules

- [x] 2.1 `css/app-menu.css`: `--thematiq-icon-radius` under `:root[data-thematiq-shape='square']`;
      the app entry's circle, the external apps' circle, the Create row's icons and the dragged pin's
      icon read it; the other icon buttons at the end of the bar take it under the square root,
      the search field excluded. REQ-TQMS-001.
- [x] 2.2 `css/header-workplace.css`: the bell, the contacts menu, the person's menu, the Create
      button and its menu's marks read it, with today's radius behind it. REQ-TQMS-001.
- [x] 2.2a `css/header-workplace.css`: the bell and the contacts menu draw the hover background on
      hover, focus-visible, active and open, and a focus outline. REQ-TQMS-001.
- [x] 2.3 vitest `headerWorkplace.spec.js`: every listed rule reads the variable with today's radius
      behind it, the variable is set under the square root only, no colour literal.

## 3. Verification

- [x] 3.1 e2e `tests/e2e/spec-coverage/header-buttons-follow-shape.spec.ts`: the scenarios of
      REQ-TQMS-001 on the Zuiddrecht set, putting the administrator's shape back.
- [x] 3.2 Diff check and lint on the changed files.
