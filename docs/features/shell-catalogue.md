---
sidebar_position: 32
---

# Bundle the house styles in another product

You build a product that ships with the thematiq house styles, without a live link to a Nextcloud. Take the shell catalogue: every token set as eighteen values, and a clear answer on which logos and fonts you may carry.

For the house style of one running instance, read [the shell tokens endpoint](./shell-tokens-api.md) instead.

## The file

```
docs/reference/shell-catalogue.json
```

Regenerate it after a change to any token set:

```
composer docs:shell-catalogue
```

A test fails when the committed file is stale. The same tree always gives the same file.

To get a copy for your product, with the logos you may carry beside it:

```
php scripts/generate-shell-catalogue.php --copy-to ../my-product/catalogue --from "thematiq <commit>"
```

## What is in it

```json
{
  "version": 1,
  "generated_from": "sets:f5983cc3ce3b2b62",
  "sets": [
    {
      "id": "zuiddrecht",
      "name": "Gemeente Zuiddrecht",
      "tokens": { "primary": "#3669A5", "action": "#3669A5", "radius": "8px" },
      "logo": {
        "file": "img/logos/zuiddrecht.svg",
        "inverse_file": "img/logos/zuiddrecht-dark.svg",
        "licence": "EUPL-1.2",
        "redistributable": true,
        "basis": "token-sets.json describes the set as ours or made up (\"does not exist\"); REUSE.toml: EUPL-1.2."
      }
    }
  ],
  "fonts": [
    {
      "family": "Fira Sans",
      "sets": ["zuiddrecht"],
      "shipped": true,
      "files": ["css/systems/nldesign/fonts/fira-sans-latin-400-normal.woff2"],
      "licence": "OFL-1.1",
      "licence_files": ["css/systems/nldesign/fonts/OFL.txt"],
      "redistributable": true,
      "basis": "REUSE.toml names these files OFL-1.1, and LICENSES/OFL-1.1.txt allows redistribution with its notice."
    }
  ]
}
```

A set that ships a dark variant also has a `dark` block with the same nineteen roles, as thematiq's own dark variant gives them: `"dark": { "tokens": { … } }`. A role the dark variant does not restate, such as a radius or the font, keeps its light value. The stock `nextcloud` set has no `dark` block, because Nextcloud brings its own dark theme. In a dark block `ground` is the set's dark page, or Nextcloud's own dark page (`#171717`) for a set that names none, and `primary-deep` is a colour that reads on the dark `primary-light`.

The example is shortened: `tokens` holds all nineteen roles, the same ones the endpoint answers. `generated_from` identifies the content, so two catalogues with the same value hold the same sets.

A set that declares more than the roles also has a `vocabulary` block: every `--nldesign-*` token its own file declares, by name without `--nldesign-`, with the resolved value. For example `"vocabulary": { "color-error": "#CC0000", "component-table-header-font-weight": "400" }`. It holds only what the set declares itself, not the defaults under it, so a product that reads it keeps its own value for a token the set leaves out. It leaves out the seventeen names the roles come from (they are in `tokens`), tokens that hold a picture, an address or a shape (`logo-url`, `brand-stripe-image`, `hero-image-clip-path` and the like), and values that still point at another variable. The `dark` block of a set has a `vocabulary` of its own too: the vocabulary values its dark variant file states, by the same rules. The OpenProject plugin `openproject-thematiq` reads both.

## Which files you may carry

Carry a file only when `redistributable` is `true`.

- `true`: this repository says the logo is made up or Conduction's own, or names the font under an open licence. For a font, carry the files in `licence_files` with it: the licence asks for that.
- `false`: this repository says it may not be passed on. The Rijksoverheid mark and the Marianne typeface are examples.
- `null`: this repository does not say. Most logos of real organisations are here. Ask the organisation, or leave the logo out.

`basis` tells you what each answer rests on.

Next, generate a copy and check the `null` logos your product needs with their owners.
