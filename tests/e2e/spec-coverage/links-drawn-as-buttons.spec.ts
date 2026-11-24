/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A link drawn as a button, a week strip item and an action-menu link keep
 * their own ink and are never underlined, while a link in prose keeps the
 * link colour; under the Zuiddrecht set, in a real browser.
 *
 * The dossiq screens the scenarios name (Mijn werk's "Vandaag eerst" card,
 * DqMijnWerk's "Termijnen deze week", the case page's "Acties" menu) are
 * built here from the class names nextcloud-vue and Nextcloud draw them
 * with, plus the library's own colour rules at its own specificity, so the
 * test needs no other app installed and the theme's rules are what decide.
 *
 * The last test asks the stylesheet manifest endpoint for an id no set has.
 *
 * GLOBAL STATE: Zuiddrecht is made the active set with its overrides emptied
 * (activateTokenSet); restored in afterAll.
 */
import { test, expect, type Page } from '@playwright/test'
import { activateTokenSet, openThemedPage } from './_token-css'

/** The library's own colour rules, at class specificity, as nextcloud-vue ships them. */
const LIBRARY_CSS = `
.button-vue--vue-primary { background-color: var(--color-primary-element); color: var(--color-primary-element-text); }
.button-vue--vue-secondary { background-color: var(--color-primary-element-light); color: var(--color-main-text); }
.cn-week-strip__item { color: var(--color-main-text); }
.action-link, .action-button { color: var(--nldesign-color-on-surface, var(--color-main-text)); }
`

/** Add `html` to the page with the library rules, and read each matching element's style. */
async function render(
	page: Page,
	html: string,
): Promise<Record<string, { color: string; decoration: string }>> {
	return page.evaluate(
		({ html, css }) => {
			const style = document.createElement('style')
			style.textContent = css
			document.head.prepend(style)
			const host = document.createElement('div')
			host.innerHTML = html
			document.body.appendChild(host)
			const out: Record<string, { color: string; decoration: string }> = {}
			host.querySelectorAll<HTMLElement>('[data-probe]').forEach((el) => {
				const cs = getComputedStyle(el)
				out[el.dataset.probe as string] = {
					color: cs.color,
					decoration: cs.textDecorationLine,
				}
			})
			host.remove()
			style.remove()
			return out
		},
		{ html, css: LIBRARY_CSS },
	)
}

/** What `var(name)` paints as a text colour on this page. */
async function ink(page: Page, name: string): Promise<string> {
	return page.evaluate((n) => {
		const probe = document.createElement('span')
		probe.style.color = `var(${n})`
		document.body.appendChild(probe)
		const colour = getComputedStyle(probe).color
		probe.remove()
		return colour
	}, name)
}

test.describe('links drawn as buttons under Zuiddrecht', () => {
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
		// @e2e css-architecture::the-vandaag-eerst-card-on-mijn-werk
		"the card's link actions read like its button, none underlined, the primary white on its fill",
		async ({ page }) => {
			const seen = await render(
				page,
				'<div class="cn-widget-wrapper">'
					+ '<a href="#z" data-probe="open" class="button-vue button-vue--vue-secondary">Open deze zaken</a>'
					+ '<a href="#b" data-probe="board" class="button-vue button-vue--vue-secondary">Naar het werkbord</a>'
					+ '<a href="#c" data-probe="case" class="button-vue button-vue--vue-primary">Zaak openen</a>'
					+ '<button type="button" data-probe="pause" class="button-vue button-vue--vue-secondary">Termijn opschorten</button>'
					+ '<p>Zie ook <a href="#prose" data-probe="prose">de werkinstructie</a>.</p>'
					+ '</div>',
			)
			for (const key of ['open', 'board', 'case']) {
				expect(seen[key].decoration, `${key} is not underlined`).toBe('none')
			}
			expect(seen.open.color).toBe(seen.pause.color)
			expect(seen.board.color).toBe(seen.pause.color)
			expect(seen.pause.color).toBe(await ink(page, '--color-main-text'))
			expect(seen.case.color).toBe('rgb(255, 255, 255)')
			// A link in prose keeps the link colour.
			expect(seen.prose.color).toBe(
				await ink(page, '--nldesign-component-link-color'),
			)
		},
	)

	test(
		// @e2e css-architecture::dqmijnwerk-termijnen-deze-week
		"a week strip item's title reads in the body ink, not underlined",
		async ({ page }) => {
			const seen = await render(
				page,
				'<div class="cn-week-strip"><div class="cn-week-strip__day">'
					+ '<a href="#case" data-probe="item" class="cn-week-strip__item">'
					+ '<span class="cn-week-strip__item-title">Parkeervergunningen binnenstad</span></a>'
					+ '</div></div>',
			)
			expect(seen.item.decoration).toBe('none')
			expect(seen.item.color).toBe(await ink(page, '--color-main-text'))
		},
	)

	test(
		// @e2e css-architecture::the-case-page-acties-menu
		'every item of an action menu, button or link, reads in the main ink, none underlined',
		async ({ page }) => {
			const seen = await render(
				page,
				'<ul class="action-item__popper" role="menu">'
					+ '<li class="action"><button type="button" data-probe="edit" class="action-button">Bewerken</button></li>'
					+ '<li class="action"><a href="#bug" data-probe="bug" class="action-link">Een bug melden</a></li>'
					+ '<li class="action"><a href="#docs" data-probe="docs" class="action-link">Documentatie</a></li>'
					+ '</ul>',
			)
			const main = await ink(page, '--color-main-text')
			for (const key of ['edit', 'bug', 'docs']) {
				expect(seen[key].color, `${key} reads in the main ink`).toBe(main)
				expect(seen[key].decoration, `${key} is not underlined`).toBe('none')
			}
		},
	)
})

test.describe('the stylesheet manifest endpoint', () => {
	test(
		// @e2e css-architecture::unknown-set
		'an id no set has answers 404 and computes nothing',
		async ({ page }) => {
			await openThemedPage(page)
			const res = await page.evaluate(async () => {
				const OC = (
					window as unknown as {
						OC: {
							generateUrl: (p: string) => string
							requestToken: string
						}
					}
				).OC
				const r = await fetch(
					OC.generateUrl(
						'/apps/thematiq/settings/tokenset-stylesheets/no-such-set-e2e',
					),
					{ headers: { requesttoken: OC.requestToken } },
				)
				const text = await r.text()
				return { status: r.status, text }
			})
			expect(res.status).toBe(404)
			// Nothing was computed for it: no layers in the answer.
			expect(res.text).not.toContain('"layers"')
		},
	)
})
