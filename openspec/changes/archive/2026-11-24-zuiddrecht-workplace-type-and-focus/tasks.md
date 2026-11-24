# Tasks: zuiddrecht-workplace-type-and-focus

## 1. Type scale

- [x] 1.1 `css/tokens/zuiddrecht.css`: `--nldesign-component-heading-1-font-size: 28px`, `--nldesign-component-heading-3-font-size: 17px`.
- [x] 1.2 vitest: read the set and assert both sizes, and that the body and small text sizes are left alone.
- [x] 1.3 Run the vocabulary audit (`npm run audit:token-sets:check`) and the golden tests; re-record a golden only when the change is the new sizes.

## 2. Focus ring

- [x] 2.1 A focus token in `css/tokens/zuiddrecht.css` with the opaque action blue, and the rule that draws a 2 px ring from it on `:focus-visible` in the light workplace, with Nextcloud's own ring as fallback.
- [x] 2.2 vitest: the ring rule reads the token, and #3669A5 reaches 3:1 on #FFFFFF and on #F5F6F8.
- [x] 2.3 Look at it live on the dashboard and in Files with the keyboard (not on the demo stack at :8076). Written as `tests/e2e/spec-coverage/workplace-live-look.spec.ts` (Tab, then the 2px #3669A5 ring), runs in nightly; not yet verified live.
