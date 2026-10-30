# Design: shell-pixel-check

Same regions and limits as the plugin's `docs/pixel-check.md`, so both products' shells are guarded
the same way. Playwright's own image comparison is used instead of a Python script, because the
visual project already runs here. A control run with the Cunningham set instead of Zuiddrecht failed
with 18% of the pixels different; two runs on Zuiddrecht in a row passed.
