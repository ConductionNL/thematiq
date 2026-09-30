/**
 * @vitest-environment jsdom
 *
 * The component instrument in the browser: booted onto the token editor that
 * js/admin.js renders, the way the settings page does it.
 *
 * tests/vitest/playgroundSelection.spec.js covers the pure selection logic
 * under Node. These cover what only a DOM shows: the version switch and what
 * the stage says about the version drawn, the rows a component offers per
 * version, the cloned rows of an `r, g, b` token, and the links that must not
 * navigate the settings page away.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'

const ROOT = path.resolve(__dirname, '../..')

const inventory = JSON.parse(
	fs.readFileSync(path.join(ROOT, 'js/playground/components.json'), 'utf8'),
)

/** A registry entry for every token of the components exercised here. */
function registryFor(ids) {
	const registry = {}
	inventory.components
		.filter((component) => ids.includes(component.id))
		.forEach((component) => {
			component.tokens.forEach((spec) => {
				registry[spec.name] = {
					tab: component.tab,
					label: spec.name,
					type: spec.name.endsWith('-rgb')
						? 'rgb'
						: spec.name.endsWith('-radius')
							? 'text'
							: 'color',
				}
			})
		})
	return registry
}

const REGISTRY = registryFor(['note-cards', 'header-bar', 'sidebar', 'avatar'])

const INFO_RGB = '--nldesign-component-notecard-info-color-rgb'

/** Every URL the page fetched, in order. */
let requests = []

/** Flush pending promise chains across a few macrotask boundaries. */
async function flush(rounds = 10) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

/**
 * Render the settings page, let admin.js mount the editor, and boot the
 * instrument onto it.
 *
 * @param {number} version The Nextcloud major this instance runs.
 * @param {object} [options] Fixture options.
 * @param {object} [options.state] Extra initial-state keys.
 * @param {object} [options.overrides] The saved overrides the server answers with.
 */
async function boot(version, { state: extra = {}, overrides = {} } = {}) {
	const state = {
		tokenSets: [],
		currentTokenSet: 'rijkshuisstijl',
		playgroundInventory: inventory,
		playgroundVersion: version,
		...extra,
	}
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				Object.prototype.hasOwnProperty.call(state, key)
					? state[key]
					: fallback,
		},
	}
	global.t = (app, text, params) =>
		params === undefined
			? text
			: Object.keys(params).reduce(
					(acc, key) => acc.replace('{' + key + '}', params[key]),
					text,
				)
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	global.OC = {
		generateUrl: (url) => url,
		linkTo: (app, file) => file,
		filePath: (app, type, file) => '/apps/' + app + '/' + type + '/' + file,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	requests = []
	global.fetch = vi.fn((url) => {
		requests.push(url)
		return Promise.resolve({
			ok: true,
			status: 200,
			json: () =>
				Promise.resolve(
					url.indexOf('/settings/overrides') !== -1
						? { overrides, registry: REGISTRY, tabs: {} }
						: {},
				),
		})
	})

	document.body.innerHTML = `
		<div class="nldesign-preview" id="nldesign-preview">
			<div class="nldesign-preview-head"><h3>Preview</h3></div>
			<div class="nldesign-preview-stage" data-view="app">
				<div class="nl-mini"><a href="#elsewhere" class="nl-mini__link">Link</a></div>
			</div>
			<div class="nldesign-preview-stage" data-view="login" hidden></div>
		</div>
		<div id="nldesign-token-editor"></div>
	`
	window.history.replaceState(null, '', '#')

	vi.resetModules()
	await import('../../js/lib/tokenTransforms.js?t=' + Math.random())
	await import('../../js/admin.js?t=' + Math.random())
	await flush()

	const playground = (await import('../../js/playground.js?t=' + Math.random()))
		.default
	window.ThematiqPlayground = playground
	playground.boot()
}

/** Click a tab of the moved tab strip. */
function openTab(id) {
	document
		.querySelector(
			'.nldesign-pg-selector .nldesign-tab-btn[data-tab="' + id + '"]',
		)
		.click()
}

/** Click the chip of a component. */
function openComponent(title) {
	;[...document.querySelectorAll('.nldesign-pg-chip')]
		.find((chip) => chip.textContent === title)
		.click()
}

/** Click a version in the stage's version switch. */
function drawAs(version) {
	;[...document.querySelectorAll('.nldesign-pg-version')]
		.find((button) => button.textContent === String(version))
		.click()
}

/** What the stage says, one line per remark. */
function remarks() {
	return [
		...document.querySelectorAll('.nldesign-pg-stage .nldesign-pg-pointable'),
	].map((line) => line.textContent)
}

/** The token names the open component panel offers a row for. */
function panelTokens() {
	return [
		...document.querySelectorAll(
			'.nldesign-pg-panel .nldesign-pg-row [data-token]',
		),
	]
		.map((input) => input.getAttribute('data-token'))
		.filter((name, at, all) => all.indexOf(name) === at)
}

/** Type a value into an input and fire what the browser fires. */
function type(input, value) {
	input.value = value
	input.dispatchEvent(new window.Event('input', { bubbles: true }))
}

describe('the component instrument in the browser', () => {
	beforeEach(() => {
		if (typeof global.requestAnimationFrame !== 'function') {
			global.requestAnimationFrame = (cb) => setTimeout(cb, 0)
		}
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.ThematiqPlayground
		vi.restoreAllMocks()
	})

	it('marks the preview with the open tab, so the full view shows that tab', async () => {
		await boot(35)

		openTab('status')

		expect(
			document.getElementById('nldesign-preview').getAttribute('data-pg-tab'),
		).toBe('status')
	})

	it('keeps links in the app mock and on the stage from navigating', async () => {
		await boot(35)

		const mockLink = document.querySelector('.nl-mini a[href]')
		const mockClick = new window.MouseEvent('click', {
			bubbles: true,
			cancelable: true,
		})
		mockLink.dispatchEvent(mockClick)
		expect(mockClick.defaultPrevented).toBe(true)

		openTab('content')
		openComponent('Sidebar')
		const stageLink = document.createElement('a')
		stageLink.href = '#elsewhere'
		document.querySelector('.nldesign-pg-stage').appendChild(stageLink)
		const stageClick = new window.MouseEvent('click', {
			bubbles: true,
			cancelable: true,
		})
		stageLink.dispatchEvent(stageClick)
		expect(stageClick.defaultPrevented).toBe(true)
	})

	it("offers the note cards' r, g, b fills only while Nextcloud 32 is drawn", async () => {
		await boot(33)
		openTab('status')
		openComponent('Note cards')

		expect(panelTokens()).not.toContain(INFO_RGB)
		// One group per card type plus the shared corner, with a rule between each.
		expect(
			document.querySelectorAll('.nldesign-pg-panel .nldesign-pg-rowsep')
				.length,
		).toBe(4)
		const running = document.querySelector('.nldesign-pg-version.is-running')
		expect(running.textContent).toBe('33')
		expect(running.title).toBe('The version this instance is running')

		drawAs(32)

		expect(panelTokens()).toContain(INFO_RGB)
		expect(document.querySelector('.nldesign-pg-version.on').textContent).toBe(
			'32',
		)
		expect(remarks().some((line) => line.startsWith('Nextcloud 32:'))).toBe(true)
		// Drawn as 32 itself, so no "the preview keeps the styles of" remark.
		expect(remarks().some((line) => line.startsWith('The preview keeps'))).toBe(
			false,
		)
	})

	it('writes the triplet from a cloned r, g, b picker into the row and the editor', async () => {
		await boot(32)
		openTab('status')
		openComponent('Note cards')
		const clone = [
			...document.querySelectorAll('.nldesign-pg-panel .nldesign-pg-row'),
		].find(
			(row) => row.querySelector('[data-token="' + INFO_RGB + '"]') !== null,
		)
		const picker = clone.querySelector('.nldesign-color-picker')
		const text = clone.querySelector('.nldesign-color-text')
		const original = document.querySelector(
			'[data-token-row="' + INFO_RGB + '"] .nldesign-color-text',
		)

		type(picker, '#0a141e')
		expect(text.value).toBe('10, 20, 30')
		expect(original.value).toBe('10, 20, 30')
		expect(
			document
				.getElementById('nldesign-preview')
				.style.getPropertyValue(INFO_RGB),
		).toBe('10, 20, 30')

		type(text, '30, 20, 10')
		expect(picker.value).toBe('#1e140a')

		type(text, '#112233')
		expect(picker.value).toBe('#112233')

		type(text, 'not a colour')
		expect(picker.value).toBe('#112233')
	})

	it('says the header app icons stay white on 32 and 33, and not from 34', async () => {
		await boot(33)
		openTab('login')
		openComponent('Header bar')

		expect(
			remarks().some((line) => line.includes('the app icons are images')),
		).toBe(true)

		drawAs(35)

		expect(
			remarks().some((line) => line.includes('the app icons are images')),
		).toBe(false)
		expect(remarks().some((line) => line.startsWith('Nextcloud 35:'))).toBe(true)
	})

	it('gives a component every version draws the same no switch and no remark', async () => {
		await boot(33)
		openTab('content')
		openComponent('Avatar')

		expect(document.querySelector('.nldesign-pg-versions')).toBeNull()
		expect(remarks()).toEqual([])
		// A state with nothing to set draws no group, and so no rule for one.
		expect(
			document.querySelectorAll('.nldesign-pg-panel .nldesign-pg-rowsep')
				.length,
		).toBe(0)
		expect(panelTokens().length).toBeGreaterThan(0)
	})

	it("says the preview keeps the running version's styles for a component it does not redraw", async () => {
		await boot(35)
		openTab('content')
		openComponent('Sidebar')
		expect(remarks().some((line) => line.startsWith('The preview keeps'))).toBe(
			false,
		)

		drawAs(32)

		expect(remarks()).toContain(
			'The preview keeps the styles of Nextcloud 35, the version this instance runs.',
		)
	})

	describe('export as token set', () => {
		/**
		 * Click the export button and read back the file it offered.
		 *
		 * @return {Promise<{name: string, css: string}>} The download.
		 */
		async function exportFile() {
			let blob = null
			let name = ''
			URL.createObjectURL = vi.fn((file) => {
				blob = file
				return 'blob:export'
			})
			URL.revokeObjectURL = vi.fn()
			vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(
				function () {
					name = this.download
				},
			)

			document.getElementById('nldesign-pg-export-btn').click()
			await flush()

			return { name, css: blob === null ? '' : await blob.text() }
		}

		it('writes what the set declares, not the nldesign defaults drawn under it', async () => {
			await boot(35, {
				state: {
					playgroundSet: 'custom-house',
					// What the instrument draws with: the set on top of every
					// nldesign default, spacing included.
					playgroundTokens: {
						'--nldesign-color-primary': '#123456',
						'--nldesign-space-block-md': '24px',
						'--nldesign-component-button-padding-inline': '32px',
					},
					playgroundExportTokens: {
						'--nldesign-color-primary': '#123456',
					},
					playgroundTokenSources: {
						'--color-primary': '--nldesign-color-primary',
					},
				},
				overrides: { '--color-primary': '#a90061' },
			})

			const file = await exportFile()

			expect(file.name).toBe('custom-house.css')
			expect(file.css).toContain('--nldesign-color-primary: #a90061;')
			expect(file.css).not.toContain('--nldesign-space-block-md')
			expect(file.css).not.toContain(
				'--nldesign-component-button-padding-inline',
			)
		})

		it('reads the overrides saved for the set it exports', async () => {
			await boot(35, { state: { playgroundSet: 'custom-house' } })

			await exportFile()

			expect(requests).toContain(
				'/apps/thematiq/settings/overrides?tokenSet=custom-house',
			)
		})

		it('marks the file with the design system the set is worn on', async () => {
			await boot(35, { state: { playgroundSet: 'custom-house' } })
			document.body.insertAdjacentHTML(
				'beforeend',
				'<select id="nldesign-token-set-select">'
					+ '<option value="rijkshuisstijl"></option>'
					+ '<option value="custom-house" data-design-system="none"></option>'
					+ '</select>',
			)

			const file = await exportFile()

			expect(file.css).toContain(
				'/* thematiq-token-set: design-system=none */',
			)
		})

		it('marks a listed set without a design system as nldesign, and claims nothing for an unlisted one', async () => {
			await boot(35, { state: { playgroundSet: 'custom-house' } })
			document.body.insertAdjacentHTML(
				'beforeend',
				'<select id="nldesign-token-set-select"><option value="custom-house"></option></select>',
			)
			expect((await exportFile()).css).toContain(
				'/* thematiq-token-set: design-system=nldesign */',
			)

			document.getElementById('nldesign-token-set-select').innerHTML =
				'<option value="other"></option>'
			expect((await exportFile()).css).not.toContain('thematiq-token-set')
		})

		it('asks for the active set when the page names none', async () => {
			await boot(35)

			const file = await exportFile()

			expect(requests).toContain('/apps/thematiq/settings/overrides')
			expect(file.name).toBe('token-set.css')
		})
	})
})
