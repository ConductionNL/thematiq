# Tasks: action-menu-links-keep-text-colour

- [x] 1.1 `.action-link` sets `--nldesign-color-on-surface: var(--color-main-text)` and `text-decoration: none !important`
- [x] 1.2 vitest: the link rule resolves to the main text colour on an action link and stays the link colour on a plain link
- [x] 2.1 Live check: dossiq case "Acties" menu, all items in one colour, none underlined
      Live pass 17 Nov 2026 (NC 34.0.6, dossiq 0.4.54-unstable.20261117024211, set `epe`): the 13
      action buttons and the 3 action links (Request a feature, Report a bug, Documentation) all
      read #2e2e33, the main text, none underlined; the set's link colour #01699d appears nowhere in
      the menu.
