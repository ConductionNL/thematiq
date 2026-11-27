/**
 * The apps menu walkthrough.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * The tour is nextcloud-vue's walkthrough engine (CnWalkthrough and
 * useWalkthrough, ADR-043), the mechanism and look the other Conduction apps
 * use, over the tour data in ./walkthrough/apps-menu.json. js/app-menu.js adds
 * this script to a page only when a tour is due or asked for, and starts it
 * with the menu open through the one global this file registers:
 *
 *   window.thematiqMenuWalkthrough.start({ mode: 'auto' | 'replay', state })
 *
 * `state` is the `walkthrough` part of the menu's initial state: the person's
 * seen version, their progress and the installed version core. The seen
 * version and the progress are written back through the preference routes the
 * engine already speaks (`/apps/thematiq/api/preferences/{key}`).
 *
 * Done, the close button and Escape all end the tour and record the installed
 * version as seen; moving to a step records the progress, so leaving the page
 * resumes at that step on the next open of the menu.
 *
 * `npm run build:menu-walkthrough` bundles this file into
 * js/vendor/menuWalkthrough.js.
 *
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-the-apps-menu-has-a-walkthrough-with-one-step-per-feature-req-tqwt-001
 */

import { register, translate as t } from '@nextcloud/l10n'
import { createApp, h } from 'vue'
import CnWalkthrough from '@conduction/nextcloud-vue/dist/esm/components/CnWalkthrough/CnWalkthrough.vue.js'
import {
	compareSemver,
	persistWalkthroughProgress,
	persistWalkthroughSeenVersion,
	useWalkthrough,
} from '@conduction/nextcloud-vue/dist/esm/composables/useWalkthrough.js'
import '@conduction/nextcloud-vue/dist/nextcloud-vue.css'
import data from './walkthrough/apps-menu.json'

export const APP_ID = 'thematiq'
export const TOUR_ID = 'apps-menu'
export const SEEN_KEY = data.walkthrough.completionConfigKey
export const HOST_CLASS = 'thematiq-menu-walkthrough'
export const REDUCED_MOTION_CLASS = 'thematiq-menu-walkthrough--reduced-motion'

// Runs on this page: the engine keeps one machine per app id, so each run
// takes its own id.
let runs = 0

/**
 * The host's own rules. Inside the menu's popover, which is moved with a
 * transform, a fixed overlay is placed against the popover, not the window:
 * the host shifts it back by the popover's offset (`--thematiq-wt-top`,
 * `--thematiq-wt-left`), sizes it to the window and makes it the frame its
 * own fixed parts (dimmer, ring, card) are placed in. With reduced motion the
 * tour draws without transitions.
 */
export const HOST_CSS = '.' + HOST_CLASS + '>.cn-walkthrough{inset:auto;top:var(--thematiq-wt-top,0);left:var(--thematiq-wt-left,0);width:100vw;height:100vh;transform:translateZ(0)}'
	+ '.' + REDUCED_MOTION_CLASS + ',.' + REDUCED_MOTION_CLASS + ' *{transition:none!important;animation:none!important}'
	+ '@media (prefers-reduced-motion:reduce){.' + HOST_CLASS + ' *{transition:none!important;animation:none!important}}'

/**
 * The tour's steps as the data holds them.
 *
 * @return {Array<object>} The steps.
 */
export function tourSteps() {
	const tour = data.walkthrough.tours.find((candidate) => candidate.id === TOUR_ID)
	return tour ? tour.steps : []
}

/**
 * The version core: `major.minor.patch` without a pre-release or build
 * suffix, so a nightly and its release compare equal. Anything that is not a
 * version reads as ''.
 *
 * @param {string} version Such as `1.3.0-unstable.20261201`.
 * @return {string} Such as `1.3.0`, or ''.
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-a-new-menu-feature-gets-its-own-step-after-an-upgrade-req-tqwt-005
 */
export function versionCore(version) {
	const match = /^\s*v?(\d+)(?:\.(\d+))?(?:\.(\d+))?/.exec(String(version || ''))
	if (!match) {
		return ''
	}
	return [match[1], match[2] || '0', match[3] || '0'].join('.')
}

/**
 * The steps a person gets, as the engine composes them: every step up to the
 * installed version, and with a seen version only the steps newer than it,
 * unless the tour is replayed.
 *
 * @param {Array<object>} steps The tour's steps.
 * @param {object} options `{ seen, appVersion, replay }`.
 * @return {Array<object>} The steps to show.
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-a-new-menu-feature-gets-its-own-step-after-an-upgrade-req-tqwt-005
 */
export function stepsFor(steps, options) {
	const seen = versionCore(options.seen)
	const appVersion = versionCore(options.appVersion) || '0.0.0'
	return steps.filter((step) => {
		const since = versionCore(step.sinceVersion) || '0.0.0'
		if (compareSemver(since, appVersion) > 0) {
			return false
		}
		return options.replay === true || seen === '' || compareSemver(since, seen) > 0
	})
}

/**
 * The stored progress, or null: `{ tourId, stepId, index, immediate? }`.
 *
 * @param {string|object|null} value The stored value.
 * @return {object|null} The progress of this tour.
 */
export function progressOf(value) {
	let progress = value
	if (typeof progress === 'string') {
		if (progress === '') {
			return null
		}
		try {
			progress = JSON.parse(progress)
		} catch {
			return null
		}
	}
	if (!progress || typeof progress !== 'object' || progress.tourId !== TOUR_ID) {
		return null
	}
	return progress
}

/**
 * Whether a tour is due: never seen, a step newer than the seen version, or
 * progress left part-way.
 *
 * @param {object} state `{ seen, progress, appVersion }`.
 * @return {boolean}
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-the-tour-starts-on-the-first-open-of-the-apps-menu-req-tqwt-002
 */
export function isDue(state) {
	const s = state || {}
	if (progressOf(s.progress) !== null) {
		return true
	}
	return stepsFor(tourSteps(), { seen: s.seen, appVersion: s.appVersion }).length > 0
}

/**
 * Translate a step text or label in thematiq's catalogue.
 *
 * @param {string} text The English source.
 * @return {string}
 */
export function translate(text) {
	return t(APP_ID, text)
}

/**
 * The card's labels.
 *
 * @return {object} `nextLabel`, `backLabel`, `finishLabel`, `closeLabel`, `skipLabel`.
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-the-apps-menu-has-a-walkthrough-with-one-step-per-feature-req-tqwt-001
 */
export function labels() {
	return {
		nextLabel: t(APP_ID, 'Next'),
		backLabel: t(APP_ID, 'Back'),
		finishLabel: t(APP_ID, 'Done'),
		closeLabel: t(APP_ID, 'Close walkthrough'),
		skipLabel: t(APP_ID, 'Close walkthrough'),
	}
}

/**
 * The engine's own strings (its counter and live region speak in the
 * `nextcloud-vue` catalogue, which a Nextcloud page does not carry), filled
 * from thematiq's catalogue: "Step 1 of 7" reads "Stap 1 van 7" in Dutch.
 *
 * @return {void}
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-the-tour-is-accessible-req-tqwt-007
 */
export function registerEngineStrings() {
	const strings = {}
	for (const key of ['Step {current} of {total}', 'Step {n} of {total}', 'Walkthrough']) {
		const value = t(APP_ID, key)
		if (value !== key) {
			strings[key] = value
		}
	}
	if (Object.keys(strings).length > 0 && typeof register === 'function') {
		try {
			register('nextcloud-vue', strings)
		} catch {
			/* the English source stays */
		}
	}
}

/**
 * Whether the person asked for less motion.
 *
 * @return {boolean}
 */
function reducedMotion() {
	try {
		return typeof window.matchMedia === 'function'
			&& window.matchMedia('(prefers-reduced-motion: reduce)').matches
	} catch {
		return false
	}
}

/**
 * The walkthrough controller for this page. One per page: the engine keeps
 * one machine per app id.
 *
 * @param {object} [options] `{ http, storage }`, the engine's injection points, and
 *                           `present(ref)`, whether a part is drawn (default: in the page).
 * @return {object} `{ start, close, isRunning, currentStepId }`.
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-finishing-or-closing-records-the-seen-version-req-tqwt-004
 */
export function createMenuWalkthrough(options = {}) {
	const io = { http: options.http, storage: options.storage }
	let wt = null
	let app = null
	let state = { seen: '', progress: '', appVersion: '' }
	let onEnd = null
	let host = null
	let frame = null

	/**
	 * Keep the overlay over the window while it sits in a moved container.
	 *
	 * @return {void}
	 */
	const follow = () => {
		if (host === null) {
			return
		}
		const box = host.parentElement && host.parentElement !== document.body
			? host.parentElement.getBoundingClientRect()
			: { top: 0, left: 0 }
		host.style.setProperty('--thematiq-wt-top', (-box.top) + 'px')
		host.style.setProperty('--thematiq-wt-left', (-box.left) + 'px')
		if (wt && wt.running.value && typeof window.requestAnimationFrame === 'function') {
			frame = window.requestAnimationFrame(follow)
		} else {
			frame = null
		}
	}

	const finish = () => {
		const version = versionCore(state.appVersion)
		state.seen = version
		state.progress = ''
		persistWalkthroughSeenVersion(APP_ID, SEEN_KEY, version, io)
		persistWalkthroughProgress(APP_ID, SEEN_KEY, null, io)
		if (typeof onEnd === 'function') {
			const done = onEnd
			onEnd = null
			done()
		}
	}

	const handlers = {
		onComplete: () => finish(),
		onPause: () => {
			if (wt) {
				wt.dismiss()
			}
			finish()
		},
		onProgress: (where) => {
			if (!wt || !wt.running.value) {
				return
			}
			const progress = { tourId: where.tourId, stepId: where.stepId, index: where.index, version: versionCore(state.appVersion) }
			state.progress = JSON.stringify(progress)
			persistWalkthroughProgress(APP_ID, SEEN_KEY, progress, io)
		},
	}

	let parts = {}
	const present = typeof options.present === 'function'
		? options.present
		: (ref) => (typeof parts[ref] === 'boolean'
			? parts[ref]
			: document.querySelector('[data-walkthrough-id="' + ref + '"]') !== null)
	/**
	 * Mount a fresh engine for one run, over the steps whose part is drawn
	 * now: an optional step whose part is absent is left out, so the counter
	 * counts only the steps shown. The engine keeps one machine per app id for
	 * the page, so every run takes its own.
	 *
	 * @return {void}
	 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-the-apps-menu-has-a-walkthrough-with-one-step-per-feature-req-tqwt-001
	 */
	const mount = () => {
		if (app !== null) {
			app.unmount()
			app = null
		}
		runs++
		const engineId = APP_ID + '-' + TOUR_ID + '-' + runs
		const tours = data.walkthrough.tours.map((tour) => ({
			...tour,
			steps: tour.steps.filter((step) => !step.optional || present(step.target.ref)),
		}))
		const manifest = { version: versionCore(state.appVersion) || '0.0.0', walkthrough: { ...data.walkthrough, tours } }
		wt = useWalkthrough(engineId, manifest, { appVersion: manifest.version, seenVersion: versionCore(state.seen) })
		if (document.querySelector('style[data-thematiq-menu-walkthrough-host]') === null) {
			const style = document.createElement('style')
			style.setAttribute('data-thematiq-menu-walkthrough-host', '')
			style.textContent = HOST_CSS
			document.head.appendChild(style)
		}
		if (host === null) {
			host = document.createElement('div')
			host.className = HOST_CLASS
			document.body.appendChild(host)
		}
		app = createApp({
			render: () => h(CnWalkthrough, {
				appId: engineId,
				manifest,
				seenVersion: versionCore(state.seen),
				autoStart: false,
				look: 'board',
				zIndex: 100000,
				translate,
				class: reducedMotion() ? REDUCED_MOTION_CLASS : '',
				...labels(),
				...handlers,
			}),
		})
		app.mount(host)
	}

	/**
	 * Take a start request's options.
	 *
	 * @param {object} request The request.
	 * @return {void}
	 */
	const take = (request) => {
		if (request.state && typeof request.state === 'object') {
			state = { ...state, ...request.state }
		}
		onEnd = typeof request.onEnd === 'function' ? request.onEnd : null
		parts = request.parts && typeof request.parts === 'object' ? request.parts : {}
	}

	/**
	 * Put the overlay in its container and keep it over the window.
	 *
	 * @param {Element|undefined} container Where the overlay goes.
	 * @return {void}
	 */
	const place = (container) => {
		const target = container && container.appendChild ? container : document.body
		if (host.parentElement !== target) {
			target.appendChild(host)
		}
		if (frame === null) {
			follow()
		}
	}

	/**
	 * Run the due steps, at the step left part-way when there is one.
	 *
	 * @return {void}
	 */
	const runDue = () => {
		const steps = stepsFor(tourSteps(), { seen: state.seen, appVersion: state.appVersion })
			.filter((step) => !step.optional || present(step.target.ref))
		const progress = progressOf(state.progress)
		const stepId = progress ? progress.stepId : ''
		if (steps.length === 0) {
			// Progress left over from a tour the person has seen since.
			wt.resumeAt(TOUR_ID, stepId)
			return
		}
		wt.start(TOUR_ID, Math.max(0, steps.findIndex((step) => step.id === stepId)))
	}

	/**
	 * Start the tour.
	 *
	 * @param {object} request `{ mode: 'auto'|'replay', state, container, parts, onEnd }`:
	 *                         `container` is where the overlay goes, the menu's popover;
	 *                         `parts` says for a part's ref whether it is drawn, where the page
	 *                         cannot tell yet.
	 * @return {boolean} Whether a tour runs.
	 */
	const start = (request = {}) => {
		if (wt && wt.running.value) {
			return true
		}
		take(request)
		const replay = request.mode === 'replay'
		if (!replay && !isDue(state)) {
			return false
		}
		mount()
		if (replay) {
			wt.restart(TOUR_ID)
		} else {
			runDue()
		}
		place(request.container)
		return true
	}

	/**
	 * Close the running tour as the close button does.
	 *
	 * @return {boolean} Whether a tour was running.
	 */
	const close = () => {
		if (!wt || !wt.running.value) {
			return false
		}
		wt.pause()
		handlers.onPause()
		return true
	}

	return {
		start,
		close,
		isRunning: () => !!(wt && wt.running.value),
		currentStepId: () => (wt && wt.currentStep.value ? wt.currentStep.value.id : ''),
	}
}

if (typeof window !== 'undefined' && !window.thematiqMenuWalkthrough) {
	registerEngineStrings()
	window.thematiqMenuWalkthrough = createMenuWalkthrough()
	window.dispatchEvent(new CustomEvent('thematiq:menu-walkthrough-ready'))
}
