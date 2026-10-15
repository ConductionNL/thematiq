# Design: colour-notation-kept

The text is used only when it parses to exactly the colour the object holds, hex and alpha. That is
the rule the plugin follows, and it means a stale text never overrides a colour changed in Figma.
The text still passes the token set validator like any value.

`DesignTokensWriterTest::testEveryColourObjectImports` pinned the old result, every notation as hex.
It now expects the sRGB notations as written and lab() and display-p3 as hex.
