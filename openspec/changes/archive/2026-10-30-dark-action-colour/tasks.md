# Tasks: dark-action-colour

- [x] 1 `DarkPaletteService`: the brand exception for the action tokens, on their own pair.
- [x] 2 `DarkPaletteService::withActionText()`: a text for an action colour declared without one.
- [x] 3 `tests/Unit/Service/DarkPaletteActionColourTest.php`: a set without the colour is
      untouched; a readable colour is kept; a colour without a text gets one; an unreadable colour
      is repaired. Two of these error and one fails without the change.
- [x] 4 Forced regeneration of every dark variant: no file changed.
- [x] 5 `composer check:strict`, once, before the push.
