/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/menu-labels/spec.md
 *
 * Show menu labels: the setting is toggled through the app's own API and
 * restored in a `finally` (withThemeState), the stored value is read back
 * through the capability, and the stylesheet is measured on a real page.
 *
 * WHICH HEADER THE RULES ARE MEASURED ON. css/show-menu-labels.css targets
 * the Nextcloud 32 app menu: `nav.app-menu` with one `li.app-menu-entry` per
 * app, each holding an icon and a `.app-menu-entry__label`. Nextcloud 33 and
 * newer render a waffle button and a popover grid instead (core
 * AppMenu.vue), so on the stable35 server CI runs no header element carries
 * those classes; selector-liveness.spec.ts records the same in its ALLOWED
 * list. The layout and typography tests therefore mount the NC 32 entry
 * markup inside the real `#header` of a real, fully themed page and read the
 * computed styles there. That proves what the rules do to the markup they
 * were written for, under the whole cascade.
 *
 * The NC 33+ half of the stylesheet (#895) is measured on the server's own
 * header instead, in the two tests at the end of this file: the current-app
 * name next to the waffle on a narrow screen, and the labels of the popover
 * grid. tests/vitest/menuLabelsWaffle.spec.js checks the same rules without a
 * server.
 */
import { test, expect, type Browser, type Page } from '@playwright/test'

import {
	api,
	ensureNonAdminUser,
	loginAs,
	NONADMIN_PASS,
	NONADMIN_USER,
} from './_fixtures'
import {
	anonymousPage,
	bodyVar,
	openLoginPage,
	PROBE_URL,
	readToggles,
	resolveColor,
	rootVar,
	servedCss,
	stripComments,
	thematiqLayers,
	withThemeState,
} from './_theme-state'
import { openTheming } from '../workflows/_helpers'

const LABELS_ENDPOINT = '/index.php/apps/thematiq/settings/menulabels'

const APPS = [
	'Dashboard',
	'Files',
	'Photos',
	'Activity',
	'Mail',
	'Contacts',
	'Calendar',
	'Zaakafhandelcomponent',
]

/**
 * Mount the Nextcloud 32 app-menu markup (core AppMenu.vue / AppMenuEntry.vue
 * / AppMenuIcon.vue of stable32) inside the real header.
 *
 * The list's `display: flex` and the fixed width stand in for core's own
 * `.app-menu__list` rule, which NC 33+ no longer ships.
 */
async function mountNc32Menu(
	page: Page,
	apps: string[],
	active: number,
	listWidth = 'auto',
): Promise<void> {
	await page.locator('#header').waitFor({ state: 'attached' })
	await page.evaluate(
		({ names, activeIndex, width }) => {
			document.getElementById('e2e-nc32-menu')?.remove()
			const nav = document.createElement('nav')
			nav.id = 'e2e-nc32-menu'
			nav.className = 'app-menu'
			const list = document.createElement('ul')
			list.className = 'app-menu__list'
			list.style.cssText = `display:flex;margin:0;padding:0;list-style:none;width:${width}`
			names.forEach((name, i) => {
				const li = document.createElement('li')
				li.className =
					'app-menu-entry'
					+ (i === activeIndex ? ' app-menu-entry--active' : '')
				li.dataset.app = name
				const a = document.createElement('a')
				a.className = 'app-menu-entry__link'
				a.href = '#e2e-' + i
				a.title = name
				const icon = document.createElement('span')
				icon.className = 'app-menu-entry__icon'
				const inner = document.createElement('span')
				inner.className = 'app-menu-icon'
				inner.setAttribute('role', 'img')
				inner.setAttribute('aria-hidden', 'true')
				const img = document.createElement('img')
				img.alt = ''
				img.src =
					'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="20" height="20"/%3E'
				inner.appendChild(img)
				icon.appendChild(inner)
				const label = document.createElement('span')
				label.className = 'app-menu-entry__label'
				label.textContent = name
				a.append(icon, label)
				li.appendChild(a)
				list.appendChild(li)
			})
			nav.appendChild(list)
			const host =
				document.querySelector('#header .header-start')
				?? document.getElementById('header')
			host?.appendChild(nav)
		},
		{ names: apps, activeIndex: active, width: listWidth },
	)
}

/** Computed styles of one fixture element. */
async function styleOf(
	page: Page,
	selector: string,
	props: string[],
	pseudo: string | null = null,
): Promise<Record<string, string>> {
	return page
		.locator(selector)
		.first()
		.evaluate(
			(el, { p, ps }) => {
				const s = getComputedStyle(el, ps)
				return Object.fromEntries(
					p.map((name) => [name, s.getPropertyValue(name)]),
				)
			},
			{ p: props, ps: pseudo },
		)
}

/** Load the probe page with labels on (and the given set), mount the fixture, run `fn`. */
async function withLabels(
	browser: Browser,
	page: Page,
	fn: () => Promise<void>,
	tokenSet = 'rijkshuisstijl',
): Promise<void> {
	await withThemeState(browser, { tokenSet, showMenuLabels: true }, async () => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		expect(await thematiqLayers(page)).toContain('show-menu-labels')
		await mountNc32Menu(page, APPS, 1)
		await fn()
	})
}

const ENTRY = '#e2e-nc32-menu .app-menu-entry'
const ACTIVE_LABEL = '#e2e-nc32-menu .app-menu-entry--active .app-menu-entry__label'
const LABEL =
	'#e2e-nc32-menu .app-menu-entry:not(.app-menu-entry--active) .app-menu-entry__label'

test.use({ colorScheme: 'light' })

test.describe('menu-labels', () => {
	// Not `mode: 'serial'`: every test sets and restores its own state through
	// withThemeState(), so none depends on the one before it. Serial mode would
	// skip the rest of the file after one failure (26 tests in run
	// 37438276176), and keep all 32 tests on one CI shard.
	test.describe.configure({ timeout: 90_000 })

	test(// @e2e openspec/specs/menu-labels/spec.md#setting-stored-as-enabled
	// @e2e openspec/specs/menu-labels/spec.md#toggle-menu-labels-on
	// @e2e openspec/specs/menu-labels/spec.md#route-registration
	'POST /settings/menulabels with true answers ok and stores the string 1', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { showMenuLabels: false }, async () => {
			await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
			const res = await api(page, 'POST', LABELS_ENDPOINT, {
				showMenuLabels: true,
			})
			expect(res.status).toBe(200)
			expect(res.json).toEqual({ status: 'ok', showMenuLabels: true })
			expect((await readToggles(page)).showMenuLabels).toBe(true)
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#setting-stored-as-disabled
	// @e2e openspec/specs/menu-labels/spec.md#toggle-menu-labels-off
	'POST /settings/menulabels with false answers ok and stores the string 0', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { showMenuLabels: true }, async () => {
			await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
			const res = await api(page, 'POST', LABELS_ENDPOINT, {
				showMenuLabels: false,
			})
			expect(res.status).toBe(200)
			expect(res.json).toEqual({ status: 'ok', showMenuLabels: false })
			expect((await readToggles(page)).showMenuLabels).toBe(false)
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#non-admin-access-denied
	'a non-admin POST is refused and the setting does not move', async ({
		browser,
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		await ensureNonAdminUser(page)
		await withThemeState(browser, { showMenuLabels: false }, async () => {
			const user = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
			try {
				await user.page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
				const res = await api(user.page, 'POST', LABELS_ENDPOINT, {
					showMenuLabels: true,
				})
				expect(res.status).toBe(403)
			} finally {
				await user.close()
			}
			expect((await readToggles(page)).showMenuLabels).toBe(false)
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#feature-enabled-loads-css
	'enabled, show-menu-labels.css loads after every core layer and custom-overrides', async ({
		browser,
		page,
	}) => {
		// An empty overrides file of its own, so the custom-overrides layer is
		// there to load after: without one, findIndex() is -1 and "after it"
		// holds for any position.
		await withThemeState(
			browser,
			{ showMenuLabels: true, overrides: {} },
			async () => {
				await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
				const layers = await thematiqLayers(page)
				const labels = layers.indexOf('show-menu-labels')
				const overrides = layers.findIndex((l) =>
					l.startsWith('custom-overrides'),
				)
				expect(
					overrides,
					'the custom-overrides layer must load',
				).toBeGreaterThanOrEqual(0)
				expect(labels).toBeGreaterThan(overrides)
				for (const [i, l] of layers.entries()) {
					if (l.startsWith('systems/') || l.startsWith('tokens/')) {
						expect(i, l).toBeLessThan(labels)
					}
				}
			},
		)
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#feature-disabled-skips-css
	'disabled, no show-menu-labels.css is loaded and the stock app menu renders', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { showMenuLabels: false }, async () => {
			await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
			expect(await thematiqLayers(page)).not.toContain('show-menu-labels')
			await expect(page.locator('#header nav.app-menu').first()).toBeVisible()
			// Nothing hides an icon when the stylesheet is absent.
			await mountNc32Menu(page, APPS, 1)
			expect(
				(await styleOf(page, `${ENTRY} .app-menu-entry__icon`, ['display']))
					.display,
			).not.toBe('none')
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#css-loading-order-relative-to-other-conditionals
	'with both toggles on, hide-slogan loads before show-menu-labels, both after custom-overrides', async ({
		browser,
		page,
	}) => {
		// The custom-overrides layer is only linked while the active set has a
		// saved overrides file, so the test saves one (empty) itself rather than
		// rely on an earlier spec having left one behind.
		await withThemeState(
			browser,
			{ hideSlogan: true, showMenuLabels: true, overrides: {} },
			async () => {
				await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
				const layers = await thematiqLayers(page)
				const overrides = layers.findIndex((l) =>
					l.startsWith('custom-overrides'),
				)
				const slogan = layers.indexOf('hide-slogan')
				const labels = layers.indexOf('show-menu-labels')
				expect(
					overrides,
					'the custom-overrides layer must load',
				).toBeGreaterThanOrEqual(0)
				expect(slogan).toBeGreaterThan(overrides)
				expect(labels).toBeGreaterThan(slogan)
			},
		)
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#app-menu-icons-hidden
	'both icon selectors compute to display none and visibility hidden', async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			for (const selector of [
				`${ENTRY} .app-menu-entry__icon`,
				`${ENTRY} .app-menu-icon`,
			]) {
				expect(
					await styleOf(page, selector, ['display', 'visibility']),
					selector,
				).toEqual({ display: 'none', visibility: 'hidden' })
			}
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#icons-hidden-for-all-apps
	'with ten apps in the menu, all ten icons are hidden', async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			const ten = [...APPS, 'Talk', 'Deck']
			await mountNc32Menu(page, ten, 0)
			const displays = await page
				.locator(`${ENTRY} .app-menu-entry__icon`)
				.evaluateAll((els) => els.map((el) => getComputedStyle(el).display))
			expect(displays).toEqual(Array(10).fill('none'))
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#menu-overflow-icons-preserved
	'with labels on, the stock app-menu trigger still opens the app list', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { showMenuLabels: true }, async () => {
			await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
			// The overflow control of the NC 33+ header is the waffle button.
			const trigger = page.locator('#header .app-menu__waffle').first()
			await expect(trigger).toBeVisible()
			await trigger.click()
			const firstApp = page.locator('.app-menu__grid .app-item').first()
			await expect(firstApp).toBeVisible()
			expect(((await firstApp.textContent()) ?? '').trim()).not.toBe('')
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#labels-made-visible
	'labels are declared inline-block and compute visible and fully opaque', async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			// The stylesheet declares what the spec names ...
			const rule = new RegExp(
				'#header nav\\.app-menu \\.app-menu-entry__label \\{[^}]*'
					+ 'display: inline-block !important;',
			)
			expect(stripComments(await servedCss(page, 'show-menu-labels'))).toMatch(
				rule,
			)
			// ... and the browser blockifies it: the label is a flex item of the
			// column-flex entry link (asserted in the tests below), and a flex
			// item's inline-block computes to block (CSS Display 3, 2.7).
			expect(
				await page
					.locator(LABEL)
					.first()
					.evaluate(
						(el) =>
							getComputedStyle(el.parentElement as Element).display,
					),
			).toBe('flex')
			expect(
				await styleOf(page, LABEL, ['display', 'visibility', 'opacity']),
			).toEqual({
				display: 'block',
				visibility: 'visible',
				opacity: '1',
			})
			await expect(page.locator(LABEL).first()).toBeVisible()
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#label-typography
	'labels are 14px, weight 400 (600 when active), nowrap, line-height 1.4', async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			const normal = await styleOf(page, LABEL, [
				'font-size',
				'font-weight',
				'white-space',
				'line-height',
			])
			expect(normal).toEqual({
				'font-size': '14px',
				'font-weight': '400',
				'white-space': 'nowrap',
				'line-height': '19.6px',
			})
			expect(
				(await styleOf(page, ACTIVE_LABEL, ['font-weight']))['font-weight'],
			).toBe('600')
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#label-positioning-overrides-nextcloud-defaults
	'labels are static, untransformed, untruncated and centred', async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			expect(
				await styleOf(page, LABEL, [
					'position',
					'transform',
					'max-width',
					'text-align',
				]),
			).toEqual({
				position: 'static',
				transform: 'none',
				'max-width': 'none',
				'text-align': 'center',
			})
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#label-padding-for-spacing
	'labels have 8px inline padding, no block padding, and sit in the middle', async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			expect(
				await styleOf(page, LABEL, [
					'padding-top',
					'padding-right',
					'padding-bottom',
					'padding-left',
					'vertical-align',
				]),
			).toEqual({
				'padding-top': '0px',
				'padding-right': '8px',
				'padding-bottom': '0px',
				'padding-left': '8px',
				'vertical-align': 'middle',
			})
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#labels-use-the-nl-design-font
	'on an nldesign set the labels render in the nldesign font family', async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			const first = (v: string) => v.replace(/['"]/g, '').split(',')[0].trim()
			const family = (await styleOf(page, LABEL, ['font-family']))[
				'font-family'
			]
			expect(first(family)).toBe(
				first(await rootVar(page, '--nldesign-font-family')),
			)
			expect(first(family)).toBe('Fira Sans')
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#menu-entry-dimensions
	'entries take the header height, at least 80px width, and do not shrink', async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			const headerHeight = await bodyVar(page, '--header-height')
			expect(headerHeight).not.toBe('')
			const entry = await styleOf(page, ENTRY, [
				'height',
				'min-width',
				'flex-shrink',
			])
			expect(entry).toEqual({
				height: await page.evaluate((h) => {
					const probe = document.createElement('div')
					probe.style.height = h
					document.body.appendChild(probe)
					const px = getComputedStyle(probe).height
					probe.remove()
					return px
				}, headerHeight),
				'min-width': '80px',
				'flex-shrink': '0',
			})
			// width: auto, so the long label widens its entry past the minimum.
			const widths = await page
				.locator(ENTRY)
				.evaluateAll((els) =>
					els.map((el) => el.getBoundingClientRect().width),
				)
			expect(Math.max(...widths)).toBeGreaterThan(80)
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#menu-entry-link-layout
	'the entry link is a full-height centred flex column without padding', async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			const link = await styleOf(page, `${ENTRY} .app-menu-entry__link`, [
				'display',
				'flex-direction',
				'align-items',
				'justify-content',
				'height',
				'padding-top',
				'padding-left',
			])
			const entryHeight = (await styleOf(page, ENTRY, ['height'])).height
			expect(link).toEqual({
				display: 'flex',
				'flex-direction': 'column',
				'align-items': 'center',
				'justify-content': 'center',
				height: entryHeight,
				'padding-top': '0px',
				'padding-left': '0px',
			})
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#menu-stretches-to-accommodate-all-labels
	'in a list too narrow for eight labels, no entry is squeezed below its label or 80px', async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			await mountNc32Menu(page, APPS, 1, '300px')
			const entries = await page.locator(ENTRY).evaluateAll((els) =>
				els.map((el) => {
					const label = el.querySelector(
						'.app-menu-entry__label',
					) as HTMLElement
					return {
						width: el.getBoundingClientRect().width,
						labelFits: label.scrollWidth <= label.clientWidth + 1,
					}
				}),
			)
			expect(entries.length).toBe(8)
			for (const entry of entries) {
				expect(entry.width).toBeGreaterThanOrEqual(80)
				expect(entry.labelFits).toBe(true)
			}
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#default-active-indicator-removed
	"the active entry's ::before dot is transparent and fully faded", async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			expect(
				await styleOf(
					page,
					'#e2e-nc32-menu .app-menu-entry--active',
					['background-color', 'opacity'],
					'::before',
				),
			).toEqual({ 'background-color': 'rgba(0, 0, 0, 0)', opacity: '0' })
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#active-item-distinguished-by-font-weight
	'only the active label is 600; every other label is 400', async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			const weights = await page
				.locator('#e2e-nc32-menu .app-menu-entry__label')
				.evaluateAll((els) =>
					els.map((el) => getComputedStyle(el).fontWeight),
				)
			expect(weights).toEqual(APPS.map((_, i) => (i === 1 ? '600' : '400')))
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#active-state-visible-on-all-backgrounds
	'on a white and on a coloured header the weights differ and the text takes the header colour', async ({
		browser,
		page,
	}) => {
		// rijkshuisstijl paints the header white, utrecht red (#cc0000).
		for (const set of ['rijkshuisstijl', 'utrecht']) {
			await withLabels(
				browser,
				page,
				async () => {
					const headerText = await resolveColor(
						page,
						await rootVar(page, '--nldesign-color-header-text'),
					)
					const active = await styleOf(page, ACTIVE_LABEL, [
						'font-weight',
						'color',
					])
					const other = await styleOf(page, LABEL, [
						'font-weight',
						'color',
					])
					expect(active, set).toEqual({
						'font-weight': '600',
						color: headerText,
					})
					expect(other, set).toEqual({
						'font-weight': '400',
						color: headerText,
					})
				},
				set,
			)
		}
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#checkbox-reflects-current-state-on-load
	'the admin checkbox renders the stored value, checked and unchecked', async ({
		browser,
		page,
	}) => {
		const box = page.locator('#nldesign-show-menu-labels')
		await withThemeState(browser, { showMenuLabels: true }, async () => {
			await openTheming(page)
			await expect(box).toBeChecked()
		})
		await withThemeState(browser, { showMenuLabels: false }, async () => {
			await openTheming(page)
			await expect(box).not.toBeChecked()
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#checkbox-change-triggers-save
	'toggling the box posts the new boolean and the stored value follows', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { showMenuLabels: false }, async () => {
			await openTheming(page)
			const [response] = await Promise.all([
				page.waitForResponse(
					(r) =>
						r.url().includes('/settings/menulabels')
						&& r.request().method() === 'POST',
				),
				page.locator('label[for="nldesign-show-menu-labels"]').click(),
			])
			expect(response.request().postDataJSON()).toEqual({
				showMenuLabels: true,
			})
			expect(response.status()).toBe(200)
			expect((await readToggles(page)).showMenuLabels).toBe(true)
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#checkbox-label-is-localized-and-accessible
	'the checkbox label reads the translated string and names the box', async ({
		page,
	}) => {
		await openTheming(page)
		const label = page.locator('label[for="nldesign-show-menu-labels"]')
		await expect(label).toHaveText('Show text labels in app menu (hide icons)')
		await expect(
			page.locator('#nldesign-show-menu-labels'),
		).toHaveAccessibleName('Show text labels in app menu (hide icons)')
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#screen-reader-improvement
	"each entry's accessible name is the label a sighted user reads", async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			for (const name of APPS) {
				const link = page.locator(
					`#e2e-nc32-menu li[data-app="${name}"] .app-menu-entry__link`,
				)
				await expect(link).toHaveAccessibleName(name)
				await expect(link.locator('.app-menu-entry__label')).toBeVisible()
				expect(((await link.innerText()) ?? '').trim()).toBe(name)
			}
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#cognitive-accessibility
	// @e2e openspec/specs/menu-labels/spec.md#feature-satisfies-wcag-guidelines
	'every entry is identified by visible text equal to its app name, with no icon shown', async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			const entries = await page.locator(ENTRY).evaluateAll((els) =>
				els.map((el) => ({
					title: (el.querySelector('a') as HTMLAnchorElement).title,
					text: (el as HTMLElement).innerText.trim(),
					iconShown:
						getComputedStyle(
							el.querySelector('.app-menu-entry__icon') as Element,
						).display !== 'none',
				})),
			)
			expect(entries.map((e) => e.text)).toEqual(APPS)
			expect(entries.map((e) => e.title)).toEqual(APPS)
			expect(entries.filter((e) => e.iconShown)).toEqual([])
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#labels-on-wide-viewport
	'at 1400px wide all eight labels are inside the viewport and untruncated', async ({
		browser,
		page,
	}) => {
		await page.setViewportSize({ width: 1400, height: 900 })
		await withLabels(browser, page, async () => {
			const labels = await page
				.locator('#e2e-nc32-menu .app-menu-entry__label')
				.evaluateAll((els) =>
					els.map((el) => ({
						right: el.getBoundingClientRect().right,
						fits: el.scrollWidth <= el.clientWidth + 1,
					})),
				)
			expect(labels.length).toBe(8)
			for (const label of labels) {
				expect(label.fits).toBe(true)
				expect(label.right).toBeLessThanOrEqual(1400)
			}
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#labels-on-narrow-viewport
	'at 600px wide entries keep their minimum and the stock app menu stays reachable', async ({
		browser,
		page,
	}) => {
		await page.setViewportSize({ width: 600, height: 900 })
		await withLabels(browser, page, async () => {
			const widths = await page
				.locator(ENTRY)
				.evaluateAll((els) =>
					els.map((el) => el.getBoundingClientRect().width),
				)
			expect(widths.length).toBe(8)
			for (const width of widths) expect(width).toBeGreaterThanOrEqual(80)
			// flex-shrink: 0 is what holds them there, so the row may overflow.
			expect(
				(await styleOf(page, ENTRY, ['flex-shrink']))['flex-shrink'],
			).toBe('0')
			// Apps that do not fit stay reachable through the stock menu.
			await expect(
				page.locator('#header .app-menu__waffle').first(),
			).toBeVisible()
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#labels-with-nowrap-prevent-wrapping
	'a 21-character label stays on one line and widens its entry', async ({
		browser,
		page,
	}) => {
		await withLabels(browser, page, async () => {
			const long = await page
				.locator('#e2e-nc32-menu li[data-app="Zaakafhandelcomponent"]')
				.evaluate((li) => {
					const label = li.querySelector(
						'.app-menu-entry__label',
					) as HTMLElement
					return {
						whiteSpace: getComputedStyle(label).whiteSpace,
						lines: Math.round(
							label.getBoundingClientRect().height
								/ parseFloat(getComputedStyle(label).lineHeight),
						),
						labelWidth: label.scrollWidth,
						entryWidth: li.getBoundingClientRect().width,
					}
				})
			expect(long.whiteSpace).toBe('nowrap')
			expect(long.lines).toBe(1)
			expect(long.entryWidth).toBeGreaterThanOrEqual(long.labelWidth)
			expect(long.entryWidth).toBeGreaterThan(80)
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#both-features-enabled-simultaneously
	'with both on: no slogan on the login page, text labels after logging in', async ({
		browser,
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		await ensureNonAdminUser(page)
		await withThemeState(
			browser,
			{ hideSlogan: true, showMenuLabels: true },
			async () => {
				const anon = await anonymousPage(browser)
				try {
					await openLoginPage(anon.page)
					expect(
						await anon.page
							.locator('footer.guest-box')
							.first()
							.evaluate((el) => getComputedStyle(el).display),
					).toBe('none')
				} finally {
					await anon.close()
				}
				const user = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
				try {
					await user.page.goto('/apps/dashboard/', {
						waitUntil: 'domcontentloaded',
					})
					expect(await thematiqLayers(user.page)).toContain(
						'show-menu-labels',
					)
					await mountNc32Menu(user.page, APPS, 0)
					expect(
						(
							await styleOf(
								user.page,
								`${ENTRY} .app-menu-entry__icon`,
								['display'],
							)
						).display,
					).toBe('none')
					await expect(
						user.page.locator(`${ENTRY} .app-menu-entry__label`).first(),
					).toBeVisible()
				} finally {
					await user.close()
				}
			},
		)
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#only-menu-labels-enabled
	'with only labels on: the slogan shows on the login page, labels after logging in', async ({
		browser,
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		await ensureNonAdminUser(page)
		await withThemeState(
			browser,
			{ hideSlogan: false, showMenuLabels: true },
			async () => {
				const anon = await anonymousPage(browser)
				try {
					await openLoginPage(anon.page)
					const footer = anon.page.locator('footer.guest-box').first()
					expect(
						await footer.evaluate((el) => getComputedStyle(el).display),
					).not.toBe('none')
					expect(((await footer.textContent()) ?? '').trim()).not.toBe('')
				} finally {
					await anon.close()
				}
				const user = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
				try {
					await user.page.goto(PROBE_URL, {
						waitUntil: 'domcontentloaded',
					})
					expect(await thematiqLayers(user.page)).toContain(
						'show-menu-labels',
					)
					await mountNc32Menu(user.page, APPS, 0)
					await expect(
						user.page.locator(`${ENTRY} .app-menu-entry__label`).first(),
					).toBeVisible()
				} finally {
					await user.close()
				}
			},
		)
	})

	/*
	 * NEXTCLOUD 33 AND NEWER (#895). These run on the server's own header, not
	 * on a mounted fixture: the waffle, the current-app button and the popover
	 * grid are what stable35 renders. On a Nextcloud 32 server the waffle does
	 * not exist and the test says so by skipping, by name.
	 */
	test(// @e2e openspec/specs/menu-labels/spec.md#current-app-name-shown-next-to-the-waffle-at-every-width
	'on a narrow screen the current app name stays next to the waffle, which core hides without labels', async ({
		browser,
		page,
	}) => {
		await page.setViewportSize({ width: 800, height: 900 })
		const current = page.locator('#header .app-menu__current-app')
		const name = page.locator('#header .app-menu__current-app-name')

		// Control: without labels, core hides the button below 1024px.
		await withThemeState(browser, { showMenuLabels: false }, async () => {
			await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
			await page.locator('#header .app-menu').waitFor({ state: 'attached' })
			test.skip(
				(await page.locator('#header .app-menu__waffle').count()) === 0,
				'Nextcloud 32 renders no waffle menu; its labels are covered by the fixture tests above',
			)
			await expect(current).toBeHidden()
		})

		await withThemeState(browser, { showMenuLabels: true }, async () => {
			await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
			expect(await thematiqLayers(page)).toContain('show-menu-labels')
			await expect(current).toBeVisible()
			await expect(name).toBeVisible()
			expect(((await name.textContent()) ?? '').trim()).not.toBe('')
		})
	})

	test(// @e2e openspec/specs/menu-labels/spec.md#grid-tiles-keep-their-labels
	'the waffle grid shows a visible label on every tile, the active one in 600', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { showMenuLabels: true }, async () => {
			await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
			await page.locator('#header .app-menu').waitFor({ state: 'attached' })
			test.skip(
				(await page.locator('#header .app-menu__waffle').count()) === 0,
				'Nextcloud 32 renders no waffle menu; its labels are covered by the fixture tests above',
			)
			await page.locator('#header .app-menu__waffle').click()
			const labels = page.locator('.app-menu__popover .app-item__label')
			await expect(labels.first()).toBeVisible()
			const count = await labels.count()
			expect(count).toBeGreaterThan(0)
			for (let i = 0; i < count; i++) {
				await expect(labels.nth(i)).toBeVisible()
				expect(((await labels.nth(i).textContent()) ?? '').trim()).not.toBe(
					'',
				)
			}
			const active = page.locator(
				'.app-menu__popover .app-item--active .app-item__label',
			)
			if ((await active.count()) > 0) {
				expect(
					await active
						.first()
						.evaluate((el) => getComputedStyle(el).fontWeight),
				).toBe('600')
			}
			await page.keyboard.press('Escape')
		})
	})
})
