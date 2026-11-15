# Design: token-set-picker-search

The select stays the control: keyboard use, the apply dialog on `change`, and every other script
that reads `tokenSetSelect` keep working. The field only sets `hidden` on options. The selected
option is never hidden, because a select that shows a value it does not list confuses a screen
reader and the eye alike. The status line is a polite live region, so a screen reader hears the
count without losing focus. The field names the select it controls with `aria-controls`.
