# Design: dark-colours-from-a-file

The generator already has the rule: a hand-authored dark value (a shipped set's dark block) replaces
the derived one and is protected from repair. An upload cannot carry an `@media` block, because the
validator allows one `:root` block only and the stored file is served on every page. So the values
live in a file of their own in the runtime store, next to the generated dark variant, and the
generator merges them in before the set's own block.

The readers write the dark values into `$extensions["nl.conduction.thematiq"].dark` of the document
they hand on; the converter lifts them out for a thematiq-shaped document. A value with a brace (a
reference or a sum) is dropped by the readers, and every value passes `validateDeclarations()`.
