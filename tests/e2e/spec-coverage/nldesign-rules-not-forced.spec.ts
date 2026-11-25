/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The small nldesign rules that hand a token to a component instead of
 * forcing it, and the Zuiddrecht values they carry, proved in a real browser.
 *
 * Each test makes Zuiddrecht the active set, opens a themed page and either
 * reads what the cascade resolved on the document root, or adds a few
 * elements to the page and reads their computed style. The added elements
 * carry the class names the rule under test is written for (`cn-status-badge`,
 * `cn-look-board`, a core primary button); where a scenario names a screen in
 * another app (DqWerkbord, the Taken board, dossiq's queue), the element that
 * screen draws is built here, so the test needs no other app installed.
 * Expected values come from css/tokens/zuiddrecht.css.
 *
 * GLOBAL STATE: the active set is switched to `zuiddrecht` and its admin
 * overrides are emptied for the run; both are restored in afterAll (see
 * activateTokenSet).
 */
import { test, expect, type Page } from '@playwright/test'
import {
	activateTokenSet,
	contrastRatio,
	normaliseCss,
	openThemedPage,
	rootVars,
} from './_token-css'

/** Turn `rgb(r, g, b)` into `#rrggbb`, the notation the token file uses. */
function rgbToHex(rgb: string): string {
	const parts = rgb.match(/\d+(\.\d+)?/g) ?? []
	return (
		'#'
		+ parts
			.slice(0, 3)
			.map((n) => Math.round(Number(n)).toString(16).padStart(2, '0'))
			.join('')
	)
}

/**
 * Add `html` and an optional stylesheet to the page, and return the computed
 * style properties `props` of the element matching `selector`.
 */
async function computedOf(
	page: Page,
	html: string,
	selector: string,
	props: string[],
	css = '',
): Promise<Record<string, string>> {
	return page.evaluate(
		({ html, selector, props, css }) => {
			const host = document.createElement('div')
			host.id = 'thematiq-e2e-host'
			host.innerHTML = html
			document.body.appendChild(host)
			if (css !== '') {
				const style = document.createElement('style')
				style.textContent = css
				document.head.appendChild(style)
			}
			const el = host.querySelector(selector)
			if (el === null) {
				throw new Error(`no element matches ${selector}`)
			}
			const cs = getComputedStyle(el)
			const out: Record<string, string> = {}
			for (const p of props) {
				out[p] = cs.getPropertyValue(p).trim()
			}
			host.remove()
			return out
		},
		{ html, selector, props, css },
	)
}

test.describe('nldesign rules under the Zuiddrecht set', () => {
	test.describe.configure({ mode: 'serial', timeout: 90_000 })

	let restore: (() => Promise<void>) | null = null

	test.beforeAll(async ({ browser }) => {
		restore = await activateTokenSet(browser, 'zuiddrecht')
	})

	test.afterAll(async () => {
		if (restore !== null) {
			await restore()
		}
	})

	test.beforeEach(async ({ page }) => {
		await openThemedPage(page)
	})

	test(
		// @e2e zuiddrecht-warning-text::a-warning-note
		"the warning text colour is the set's warning ink",
		async ({ page }) => {
			// The theme variables are redeclared on body's children (see
			// css/theme-scopes.css), so the value is read off an element in the
			// page, not off the document root.
			const note = await computedOf(
				page,
				'<span class="warning-note">Let op</span>',
				'.warning-note',
				['color'],
				'#thematiq-e2e-host .warning-note { color: var(--color-warning-text); }',
			)
			expect(rgbToHex(note.color)).toBe('#7a4f00')
		},
	)

	test(
		// @e2e error-text-ink::a-late-deadline-on-dqdagafsluiten
		'error text reads the error ink, error buttons keep the error colour',
		async ({ page }) => {
			const html =
				'<span class="late">1 dag te laat</span><span class="error-badge">Fout</span>'
			const css =
				'#thematiq-e2e-host .late { color: var(--color-text-error); }'
				+ ' #thematiq-e2e-host .error-badge { background-color: var(--color-error); }'
			// The late deadline is a line of error text in the body.
			const line = await computedOf(page, html, '.late', ['color'], css)
			expect(rgbToHex(line.color)).toBe('#a30000')
			// An error badge or button keeps the error colour.
			const badge = await computedOf(
				page,
				html,
				'.error-badge',
				['background-color'],
				css,
			)
			expect(rgbToHex(badge['background-color'])).toBe('#cc0000')
		},
	)

	test(
		// @e2e control-border-three-to-one::a-board-button-on-the-page-ground
		'the control border is 3:1 against the page ground',
		async ({ page }) => {
			const vars = await rootVars(page, ['--nldesign-color-border-dark'])
			const border = normaliseCss(vars['--nldesign-color-border-dark'])
			expect(border).toBe('#828a95')
			expect(contrastRatio(border, '#f5f6f8')).toBeGreaterThanOrEqual(3)
		},
	)

	test(
		// @e2e nldesign-select-border-control-step::the-board-views-selects
		'a select has the control border',
		async ({ page }) => {
			const select = await computedOf(
				page,
				'<label>Zaaktype: <select class="board-select"><option>Alle</option></select></label>',
				'.board-select',
				['border-top-color'],
			)
			expect(select['border-top-color']).toBe('rgb(130, 138, 149)')
		},
	)

	test(
		// @e2e status-badge-neutral-tone::a-neutral-priority-pill-on-the-taken-board
		"a neutral status pill takes the set's neutral pill tokens",
		async ({ page }) => {
			// nextcloud-vue draws the default tone from Nextcloud's own variables;
			// the status-badge scope re-points those at the set's neutral tokens.
			const pill = await computedOf(
				page,
				'<span class="cn-status-badge cn-status-badge--default">Normaal</span>',
				'.cn-status-badge--default',
				['background-color', 'color'],
				'#thematiq-e2e-host .cn-status-badge--default { background-color: var(--color-background-dark); color: var(--color-main-text); }',
			)
			expect(rgbToHex(pill['background-color'])).toBe('#f0f1f3')
			expect(rgbToHex(pill.color)).toBe('#3d4047')
		},
	)

	test(
		// @e2e body-ink-zero-specificity::muted-text-in-a-card
		'a muted line in a card keeps the muted colour',
		async ({ page }) => {
			const line = await computedOf(
				page,
				'<div class="card"><p class="empty">Niets te doen</p><span class="empty-line">Geen taken</span></div>',
				'.empty-line',
				['color'],
				'#thematiq-e2e-host .empty-line { color: var(--color-text-maxcontrast); }',
			)
			expect(line.color).toBe('rgb(94, 97, 104)')
		},
	)

	test(
		// @e2e table-header-colour-scope::a-muted-line-in-a-table-cell
		'the header colour paints header cells only',
		async ({ page }) => {
			const html =
				'<table><thead><tr><th class="head">Naam</th></tr></thead>'
				+ '<tbody><tr><td>Dossier<br><span class="muted">gewijzigd gisteren</span></td></tr></tbody></table>'
			const css =
				'#thematiq-e2e-host .muted { color: var(--color-text-maxcontrast); }'
			const head = await computedOf(page, html, '.head', ['color'], css)
			const muted = await computedOf(page, html, '.muted', ['color'], css)
			const vars = await rootVars(page, ['--nldesign-color-text'])
			expect(rgbToHex(head.color)).toBe(
				normaliseCss(vars['--nldesign-color-text']),
			)
			expect(muted.color).toBe('rgb(94, 97, 104)')
		},
	)

	test(
		// @e2e panel-background-narrow::dossiqs-queue
		'the panel catch-all leaves a nextcloud-vue page with a side panel alone',
		async ({ page }) => {
			const ground = await computedOf(
				page,
				'<div class="cn-index-page cn-index-page--with-side-panel"><div class="cn-card">Zaak</div></div>',
				'.cn-index-page--with-side-panel',
				['background-color'],
			)
			// Transparent: the workspace ground shows through around the cards.
			expect(ground['background-color']).toBe('rgba(0, 0, 0, 0)')
			// A non-library panel is still painted solid, so the rule is in force.
			const plain = await computedOf(
				page,
				'<div class="side-panel">Paneel</div>',
				'.side-panel',
				['background-color'],
			)
			expect(plain['background-color']).not.toBe('rgba(0, 0, 0, 0)')
		},
	)

	test(
		// @e2e paragraph-token::a-board-look-note
		'inside the board look a note keeps its size, a bare paragraph takes the token',
		async ({ page }) => {
			const html =
				'<div class="cn-look-board"><p class="note">Laatst bijgewerkt</p><p class="bare">Tekst</p></div>'
			const css =
				'#thematiq-e2e-host .cn-look-board .note { font-size: 13px; }'
			const note = await computedOf(page, html, '.note', ['font-size'], css)
			const bare = await computedOf(page, html, '.bare', ['font-size'], css)
			const outside = await computedOf(
				page,
				'<p class="outside">Tekst</p>',
				'.outside',
				['font-size'],
			)
			expect(note['font-size']).toBe('13px')
			// A paragraph with no size of its own takes the paragraph token, the
			// same size the forced rule gives a paragraph outside the board look.
			expect(bare['font-size']).toBe(outside['font-size'])
		},
	)

	test(
		// @e2e primary-buttons-not-forced::an-app-draws-its-own-primary-button
		"an app's own primary button padding shows",
		async ({ page }) => {
			const button = await computedOf(
				page,
				'<div class="e2e-app"><button class="button-vue button-vue--vue-primary primary">Opslaan</button></div>',
				'button',
				['padding-left', 'padding-top'],
				'#thematiq-e2e-host .e2e-app .button-vue--vue-primary { padding: 3px 21px; }',
			)
			expect(button['padding-left']).toBe('21px')
			expect(button['padding-top']).toBe('3px')
		},
	)

	test(
		// @e2e button-tokens-not-forced::a-component-draws-its-own-button-weight
		"a component's own button weight shows",
		async ({ page }) => {
			const button = await computedOf(
				page,
				'<div class="e2e-app"><button class="button-vue">Annuleren</button></div>',
				'button',
				['font-weight'],
				'#thematiq-e2e-host .e2e-app .button-vue { font-weight: 300; }',
			)
			expect(button['font-weight']).toBe('300')
		},
	)
})
