# Design: menu-role

Additive in version 1 of the endpoint: a reader that knows eighteen roles ignores the new key. The
role always has a value, like the others: the defaults declare a white navigation column, which is
what Nextcloud draws, and what the plugin draws without a menu colour.

The copy to `--nldesign-color-nav-background` happens in the converter's path for a thematiq-shaped
document, which both the set file reader and the Figma reader produce. The plugin's own name is kept,
so a download carries the value back under the name the plugin reads.
