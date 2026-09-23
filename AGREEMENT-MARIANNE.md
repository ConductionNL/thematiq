# Marianne Font — Operator User Agreement

This agreement governs enabling the **Marianne** typeface in this app (the
`nldesign` Nextcloud theming app). It supplements, and must be read
together with, [`MARIANNE-LICENCE.md`](MARIANNE-LICENCE.md), which states
the Etalab Open Licence 2.0 terms and the Marianne restriction verbatim.

## What enabling Marianne means

`nldesign` bundles the 8 Marianne `woff2` font files under
`css/systems/lasuite/fonts/marianne/`, self-hosted, inert by default. An
admin can enable them for the `lasuite` design system via a checkbox in
Administration Settings → NL Design System Theme, labelled *"Our
organisation is a French State agency (administration de l'État)"*.

**Ticking that checkbox — thereby setting the `nldesign` /
`marianne_enabled` app configuration flag to `'1'` — is the operator's
affirmative representation that the organisation operating this Nextcloud
instance is a French State agency ("administration de l'État") lawfully
entitled to use the Marianne typeface.**

## Terms

By enabling Marianne (ticking the acknowledgement checkbox), the operator
agrees that:

1. **Eligibility.** The organisation operating this instance is a French
   State administration, within the meaning of the restriction stated in
   [`MARIANNE-LICENCE.md`](MARIANNE-LICENCE.md) — i.e. an entity that is
   part of, or acting on behalf of, the French State administration, not a
   private company, a non-French public body, a municipality, or any other
   organisation outside that scope.
2. **No misrepresentation.** Enabling Marianne on an instance operated by
   an organisation that is *not* a French State agency is a misuse of the
   restriction described in `MARIANNE-LICENCE.md`, and the operator alone
   is responsible for that misuse — not Conduction, which bundles the files
   solely to serve its lawfully-entitled French government customers.
3. **Revocable.** Eligibility can change (e.g. a deployment is repurposed
   for a non-French-State tenant). The operator is responsible for
   unticking the checkbox — which immediately reverts every subsequent
   render to the self-hosted, unrestricted Inter typeface — if it ever
   becomes inapplicable.
4. **No warranty from the licence itself.** Per the Etalab Open Licence
   2.0's own liability clause (reproduced in
   [`LICENSES/etalab-2.0.txt`](LICENSES/etalab-2.0.txt)), the font files are
   provided as-is; the French State ("le Concédant") gives no guarantee
   beyond what that licence states.

## Why Conduction bundles Marianne at all

Conduction ([conduction.nl](https://conduction.nl)) develops and operates
Nextcloud-based solutions for European government customers, including
French State agencies for whom La Suite numérique (Cunningham design
system) parity — which includes Marianne as the first font in La Suite's
own configured stack — is a legitimate requirement. Conduction bundles
Marianne, self-hosted and CSP-clean, **exclusively** to serve that lawful
use case, gated so it is never silently activated for any other customer.

## Questions

If you are unsure whether your organisation qualifies as a French State
agency for the purposes of this restriction, do not enable Marianne — Inter
remains the fully-functional default typeface for the `lasuite` design
system and every other token set in this app. Contact
[info@conduction.nl](mailto:info@conduction.nl) with questions.
