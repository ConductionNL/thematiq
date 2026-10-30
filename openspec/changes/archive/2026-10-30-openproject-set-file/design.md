# Design: openproject-set-file

The plugin's `Tokens::TokenSet.from_set(strict: true)` refuses a file with any key it does not
know, in the set, the logo, the dark block or the vocabulary. The shell catalogue entry was built
to that shape (`shell-catalogue`, `shell-catalogue-vocabulary`), so the download is the entry plus
the logo verdict. A custom set has no verdict and names no logo.

On upload the reader turns the file into a thematiq document whose every value sits in the
`cssOnly` map and that carries a set id. The converter then keeps every name and fills in nothing,
as it does for thematiq's own download. The reader runs first in `TokenSetConverterService::convert()`, the
one place every upload passes.

Checked once by hand with the plugin's own code (`ConductionNL/openproject-thematiq` at
`e550f1b8`, `Transfer.import`, in a throwaway Ruby container): all 63 shipped sets' files import,
none refused.
