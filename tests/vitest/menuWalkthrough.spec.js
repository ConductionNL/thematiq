/**
 * @vitest-environment jsdom
 *
 * src/menuWalkthrough.js: the apps menu walkthrough over nextcloud-vue's
 * engine. The step order, the optional steps, the version gating on version
 * cores, replay, the texts through thematiq's catalogue, the reduced-motion
 * look and what Done and the close button store.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/apps-menu-walkthrough/spec.md
 */

import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'

const translated = []
vi.mock('@nextcloud/l10n', () => ({
	translate: (app, text, vars) => {
		translated.push([app, text])
		return String(text).replace(/\{(\w+)\}/g, (m, k) => (vars && k in vars ? String(vars[k]) : m))
	},
	t: (app, text) => text,
	register: vi.fn(),
}))
const button = {
	props: ['variant', 'alignment'],
	emits: ['click'],
	render() {
		const { h } = require('vue')
		return h('button', { onClick: (e) => this.$emit('click', e) }, [this.$slots.default && this.$slots.default()])
	},
}
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: button }))
vi.mock('@nextcloud/vue', () => ({ NcButton: button }))
vi.mock('vue-material-design-icons/Close.vue', () => ({ default: { render: () => null } }))
vi.mock('vue-material-design-icons/ChevronLeft.vue', () => ({ default: { render: () => null } }))
vi.mock('vue-material-design-icons/ChevronRight.vue', () => ({ default: { render: () => null } }))
vi.mock('@conduction/nextcloud-vue/dist/nextcloud-vue.css', () => ({}))

const ROOT = resolve(__dirname, '../..')
const DATA = JSON.parse(readFileSync(resolve(ROOT, 'src/walkthrough/apps-menu.json'), 'utf8'))
const STEPS = DATA.walkthrough.tours[0].steps

let mod

/** An http double for the engine's preference writes. */
function http() {
	const calls = []
	return {
		calls,
		get: vi.fn(() => Promise.resolve({ data: { value: null } })),
		put: vi.fn((url, body) => {
			calls.push([url, body.value])
			return Promise.resolve({ data: body })
		}),
	}
}

/** Every part the tour spotlights, drawn. */
function page({ more = true, create = true } = {}) {
	const ids = ['apps-menu-button', 'apps-menu-list', 'apps-menu-first-entry', 'apps-menu-pins', 'apps-menu-settings']
	if (more) {
		ids.push('apps-menu-more')
	}
	if (create) {
		ids.push('apps-menu-create')
	}
	document.body.innerHTML = '<div class="pop">' + ids.map((id) => `<div data-walkthrough-id="${id}">${id}</div>`).join('') + '</div>'
}

async function settle() {
	for (let i = 0; i < 6; i++) {
		await Promise.resolve()
	}
	await new Promise((r) => setTimeout(r, 0))
}

const card = () => document.querySelector('.cn-walkthrough__card')
const counter = () => card()?.querySelector('.cn-walkthrough__counter')?.textContent.trim()
const titleText = () => card()?.querySelector('.cn-walkthrough__title')?.textContent.trim()
const buttons = () => Array.from(card().querySelectorAll('.cn-walkthrough__actions button'))

describe('the apps menu walkthrough', { timeout: 60000 }, () => {
	beforeAll(async () => {
		window.matchMedia = window.matchMedia || (() => ({ matches: false, addEventListener() {}, removeEventListener() {} }))
		mod = await import('../../src/menuWalkthrough.js')
	}, 30000)

	beforeEach(() => {
		translated.length = 0
		window.localStorage.clear()
	})

	afterEach(() => {
		document.body.innerHTML = ''
	})

	it('has the seven steps of the boards, in order, with more and create optional', () => {
		expect(STEPS.map((s) => s.id)).toEqual(['open', 'apps', 'more', 'reorder', 'pin', 'create', 'settings'])
		expect(STEPS.filter((s) => s.optional).map((s) => s.id)).toEqual(['more', 'create'])
		expect(STEPS.map((s) => s.target.ref)).toEqual([
			'apps-menu-button', 'apps-menu-list', 'apps-menu-more', 'apps-menu-first-entry', 'apps-menu-pins', 'apps-menu-create', 'apps-menu-settings',
		])
		STEPS.forEach((s) => {
			expect(s.advanceOn).toEqual({ type: 'manual' })
			expect(s.sinceVersion).toMatch(/^\d+\.\d+\.\d+$/)
		})
		expect(DATA.walkthrough.completionConfigKey).toBe('apps_menu_walkthrough_seen_version')
	})

	it('keeps the menu script\'s newest step version equal to the data', () => {
		const script = readFileSync(resolve(ROOT, 'js/app-menu.js'), 'utf8')
		const latest = /var TOUR_LATEST = '([\d.]+)'/.exec(script)[1]
		const newest = STEPS.map((s) => s.sinceVersion).sort((a, b) => mod.versionCore(a).localeCompare(mod.versionCore(b), undefined, { numeric: true })).pop()
		expect(latest).toBe(newest)
	})

	it('compares version cores, so a nightly and its release are equal', () => {
		expect(mod.versionCore('1.3.0-unstable.20261201')).toBe('1.3.0')
		expect(mod.versionCore('1.4')).toBe('1.4.0')
		expect(mod.versionCore('')).toBe('')
		const steps = [
			{ id: 'a', sinceVersion: '1.3.0' },
			{ id: 'b', sinceVersion: '1.4.0' },
			{ id: 'c', sinceVersion: '1.3.1' },
		]
		// A person who saw 1.3.0 gets only the newer steps up to the installed version.
		expect(mod.stepsFor(steps, { seen: '1.3.0', appVersion: '1.4.0' }).map((s) => s.id)).toEqual(['b', 'c'])
		expect(mod.stepsFor(steps, { seen: '1.3.1', appVersion: '1.4.0-unstable.20261210' }).map((s) => s.id)).toEqual(['b'])
		// A nightly seen version does not hide the next release's step.
		expect(mod.stepsFor(steps, { seen: '1.3.0-unstable.20261201', appVersion: '1.3.1' }).map((s) => s.id)).toEqual(['c'])
		// A step newer than the installed version never shows; replay shows the rest.
		expect(mod.stepsFor(steps, { seen: '1.4.0', appVersion: '1.3.1', replay: true }).map((s) => s.id)).toEqual(['a', 'c'])
		expect(mod.stepsFor(steps, { seen: '', appVersion: '1.4.0' })).toHaveLength(3)
	})

	it('is due without a seen version, with a newer step, or with progress left part-way', () => {
		const installed = STEPS[0].sinceVersion
		expect(mod.isDue({ seen: '', progress: '', appVersion: installed })).toBe(true)
		expect(mod.isDue({ seen: installed, progress: '', appVersion: installed })).toBe(false)
		expect(mod.isDue({ seen: installed, progress: '{"tourId":"apps-menu","stepId":"pin","index":4}', appVersion: installed })).toBe(true)
		expect(mod.isDue({ seen: installed, progress: '{"tourId":"other"}', appVersion: installed })).toBe(false)
	})

	it('walks every step with the card of the boards, and Done stores the version core as seen', async () => {
		page()
		const io = http()
		const tour = mod.createMenuWalkthrough({ http: io })
		const appVersion = STEPS[0].sinceVersion + '-unstable.20261127113354'
		const ended = vi.fn()
		expect(tour.start({ mode: 'auto', state: { seen: '', progress: '', appVersion }, onEnd: ended })).toBe(true)
		await settle()
		expect(counter()).toBe('Step 1 of 7')
		expect(titleText()).toBe('Open the apps menu')
		expect(document.querySelector('.cn-walkthrough').getAttribute('aria-label')).toBe('Open the apps menu')
		expect(document.querySelector('.cn-walkthrough__live').textContent.trim()).toBe('Step 1 of 7: Open the apps menu')
		const seenTitles = []
		for (let i = 0; i < 7; i++) {
			seenTitles.push(tour.currentStepId())
			const next = buttons().pop()
			if (i === 6) {
				expect(next.textContent.trim()).toBe('Done')
			} else {
				expect(next.textContent.trim()).toBe('Next')
			}
			next.click()
			await settle()
			if (i === 0) {
				expect(document.querySelector('.cn-walkthrough__live').textContent.trim()).toBe('Step 2 of 7: Your apps in a row')
				expect(buttons()[0].textContent.trim()).toBe('Back')
			}
		}
		expect(seenTitles).toEqual(['open', 'apps', 'more', 'reorder', 'pin', 'create', 'settings'])
		expect(tour.isRunning()).toBe(false)
		expect(ended).toHaveBeenCalledTimes(1)
		const seen = io.calls.filter(([url]) => url.endsWith('/apps_menu_walkthrough_seen_version'))
		expect(seen.pop()[1]).toBe(STEPS[0].sinceVersion)
		const progress = io.calls.filter(([url]) => url.endsWith('-progress'))
		expect(JSON.parse(progress[1][1])).toMatchObject({ tourId: 'apps-menu', stepId: 'apps' })
		expect(progress.pop()[1]).toBe('')
	})

	it('leaves out an optional step whose part is not drawn, and counts only the steps shown', async () => {
		page({ create: false })
		const tour = mod.createMenuWalkthrough({ http: http() })
		tour.start({ mode: 'auto', state: { seen: '', progress: '', appVersion: STEPS[0].sinceVersion } })
		await settle()
		expect(counter()).toBe('Step 1 of 6')
		tour.close()
	})

	it('takes the page\'s word for a part that is not drawn yet', async () => {
		page({ more: false, create: false })
		const tour = mod.createMenuWalkthrough({ http: http() })
		tour.start({ mode: 'auto', state: { seen: '', progress: '', appVersion: STEPS[0].sinceVersion }, parts: { 'apps-menu-create': true } })
		await settle()
		expect(counter()).toBe('Step 1 of 6')
		tour.close()
	})

	it('closing ends the tour and stores the seen version, like Done', async () => {
		page()
		const io = http()
		const tour = mod.createMenuWalkthrough({ http: io })
		tour.start({ mode: 'auto', state: { seen: '', progress: '', appVersion: STEPS[0].sinceVersion } })
		await settle()
		card().querySelector('.cn-walkthrough__close').click()
		await settle()
		expect(tour.isRunning()).toBe(false)
		expect(card()).toBeNull()
		expect(io.calls.some(([url, value]) => url.endsWith('/apps_menu_walkthrough_seen_version') && value === STEPS[0].sinceVersion)).toBe(true)
	})

	it('does not start when nothing is due, and a replay walks every step', async () => {
		page()
		const tour = mod.createMenuWalkthrough({ http: http() })
		const state = { seen: STEPS[0].sinceVersion, progress: '', appVersion: STEPS[0].sinceVersion }
		expect(tour.start({ mode: 'auto', state })).toBe(false)
		expect(tour.isRunning()).toBe(false)
		expect(tour.start({ mode: 'replay', state })).toBe(true)
		await settle()
		expect(counter()).toBe('Step 1 of 7')
		expect(tour.currentStepId()).toBe('open')
		tour.close()
	})

	it('resumes at the step left part-way', async () => {
		page()
		const tour = mod.createMenuWalkthrough({ http: http() })
		tour.start({ mode: 'auto', state: { seen: '', progress: '{"tourId":"apps-menu","stepId":"reorder","index":3}', appVersion: STEPS[0].sinceVersion } })
		await settle()
		expect(tour.currentStepId()).toBe('reorder')
		expect(counter()).toBe('Step 4 of 7')
		tour.close()
	})

	it('puts the overlay in the container it is given and follows its offset', async () => {
		page()
		const container = document.querySelector('.pop')
		container.getBoundingClientRect = () => ({ top: 64, left: 194, width: 720, height: 400 })
		const tour = mod.createMenuWalkthrough({ http: http() })
		tour.start({ mode: 'replay', state: { seen: '', progress: '', appVersion: STEPS[0].sinceVersion }, container })
		await settle()
		const host = container.querySelector(':scope > .' + mod.HOST_CLASS)
		expect(host).not.toBeNull()
		expect(host.style.getPropertyValue('--thematiq-wt-top')).toBe('-64px')
		expect(host.style.getPropertyValue('--thematiq-wt-left')).toBe('-194px')
		tour.close()
	})

	it('sends the step texts and the labels through thematiq\'s catalogue', async () => {
		page()
		const tour = mod.createMenuWalkthrough({ http: http() })
		tour.start({ mode: 'replay', state: { seen: '', progress: '', appVersion: STEPS[0].sinceVersion } })
		await settle()
		const ours = translated.filter(([app]) => app === 'thematiq').map(([, text]) => text)
		expect(ours).toEqual(expect.arrayContaining(['Open the apps menu', STEPS[0].body, STEPS[0].task, 'Next', 'Close walkthrough']))
		tour.close()
	})

	it('passes the reduced-motion look to the engine', async () => {
		const original = window.matchMedia
		window.matchMedia = (q) => ({ matches: q.includes('reduce'), addEventListener() {}, removeEventListener() {} })
		page()
		const tour = mod.createMenuWalkthrough({ http: http() })
		tour.start({ mode: 'replay', state: { seen: '', progress: '', appVersion: STEPS[0].sinceVersion } })
		await settle()
		expect(document.querySelector('.cn-walkthrough').classList.contains(mod.REDUCED_MOTION_CLASS)).toBe(true)
		expect(document.querySelector('style[data-thematiq-menu-walkthrough-host]').textContent).toContain('prefers-reduced-motion')
		tour.close()
		window.matchMedia = original
	})
})
