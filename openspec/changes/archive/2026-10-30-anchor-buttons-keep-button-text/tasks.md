# Tasks: anchor-buttons-keep-button-text

- [x] 1.1 nldesign theme.css: link decoration and colour rules leave out anchor buttons; anchor buttons get `text-decoration: none !important` in every state
- [x] 1.2 nldesign element-overrides.css: the id-level link colour rule leaves out anchor buttons
- [x] 1.3 high-contrast theme.css: body link and forced-colors link rules leave out anchor buttons; anchor buttons not underlined, `ButtonText` under forced colours
- [x] 1.4 vitest anchorButtonText.spec.js: red on development, green after; highContrastDark.spec.js reads the renamed body link rule
- [x] 2.1 Live check on :8080 (served CSS swapped): banner actions no underline, primary white, secondary main ink; prose link unchanged
