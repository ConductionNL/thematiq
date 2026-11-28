/**
 * Thematiq's first-time setup wizard (change first-time-setup, boards TqSetupWelkom, TqSetupHuisstijl,
 * TqSetupHuisstijlLicentie, TqSetupTheming, TqSetupInlogpagina, TqSetupAppmenu, TqSetupKlaar).
 *
 * js/setup-wizard-loader.js adds this bundle to an administrator's page while setup is due (the server
 * adds the loader only then, with the `setup_status` initial state). It mounts nextcloud-vue's wizard
 * dialog in its own container on `body`, so it never depends on the page's layout, and it is also
 * opened again from the version card (`window.thematiqSetupWizard.open()`).
 *
 * Nothing is written before Apply (design.md): the steps change the choices only; Apply on step 5
 * sends them once (`POST /apps/thematiq/api/setup/action/apply`). Later closes and stores nothing;
 * "Skip, keep Nextcloud as it is" runs `skip`. One thing is stored before Apply: a set uploaded with
 * "Upload your own set" goes among the own sets, as on the settings page.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
 */

import { translate as t } from '@nextcloud/l10n'
import { createApp, h, reactive } from 'vue'
import { mdiAlertCircle, mdiCheckCircle, mdiChevronRight, mdiDatabaseOutline, mdiDotsGrid, mdiInformationOutline, mdiPaletteOutline } from '@mdi/js'
import CnWizardDialog from '@conduction/nextcloud-vue/dist/esm/components/CnWizardDialog/CnWizardDialog.vue.js'
import '@conduction/nextcloud-vue/dist/nextcloud-vue.css'
import './setupWizard.css'
import {
	applyRequest,
	confirmationNeeded,
	houseStyleReady,
	immediateWalkthrough,
	initialChoices,
	MENU_RANGES,
	FEATURED_SETS,
	NEXTCLOUD_DEFAULT,
	partsOf,
	visibleSteps,
	WALKTHROUGH,
} from './setupWizard/model.js'

export const APP_ID = 'thematiq'
export const CONTAINER_ID = 'thematiq-setup-wizard'

/** The rail's labels, by step. */
const RAIL = {
	welcome: () => t(APP_ID, 'Welcome'),
	'house-style': () => t(APP_ID, 'House style'),
	'nextcloud-theme': () => t(APP_ID, 'Nextcloud theme'),
	'login-page': () => t(APP_ID, 'Login page'),
	'apps-menu': () => t(APP_ID, 'Apps menu'),
	done: () => t(APP_ID, 'Done'),
}

/**
 * The address of one of this app's routes.
 *
 * @param {string} path The path under /apps/thematiq.
 * @return {string} The URL.
 */
function url(path) {
	const OC = window.OC || {}
	return typeof OC.generateUrl === 'function' ? OC.generateUrl('/apps/thematiq' + path) : '/index.php/apps/thematiq' + path
}

/**
 * The address of a file this app ships, such as a set's logo.
 *
 * @param {string} path The path inside the app.
 * @return {string} The URL.
 */
function appFile(path) {
	const OC = window.OC || {}
	return typeof OC.filePath === 'function' ? OC.filePath('thematiq', '', path) : '/apps/thematiq/' + path
}

/**
 * A request to the server with the CSRF token; JSON in and out.
 *
 * @param {string} method The verb.
 * @param {string} path The path under /apps/thematiq.
 * @param {object} [body] The JSON body.
 * @return {Promise<{ok: boolean, status: number, data: object}>}
 */
export async function request(method, path, body) {
	const json = body !== undefined && !(body instanceof FormData)
	const headers = { requesttoken: (window.OC || {}).requestToken || '', 'OCS-APIRequest': 'true' }
	if (json) {
		headers['Content-Type'] = 'application/json'
	}
	const response = await fetch(url(path), { method, headers, body: json ? JSON.stringify(body) : body })
	return { ok: response.ok, status: response.status, data: await jsonOf(response) }
}

/**
 * The JSON an answer carries, or an empty object.
 *
 * @param {Response} response The answer.
 * @return {Promise<object>}
 */
async function jsonOf(response) {
	try {
		return (await response.json()) || {}
	} catch (e) {
		return {}
	}
}

/**
 * The reason a refused answer gives, else the fallback.
 *
 * @param {object} answer The answer of request().
 * @param {string} fallback What to say otherwise.
 * @return {string}
 */
function reasonOf(answer, fallback) {
	return answer.data.message || answer.data.error || fallback
}

/**
 * The setup status the page carries (`setup_status`), or null.
 *
 * @return {object|null}
 */
export function preloadedStatus() {
	try {
		return window.OCP.InitialState.loadState(APP_ID, 'setup_status', null)
	} catch (e) {
		return null
	}
}

/**
 * A 24 px icon from one path, coloured by the text colour.
 *
 * @param {string} d The path.
 * @return {object} The vnode.
 */
function svgIcon(d) {
	return h('svg', { class: 'tq-setup__icon', viewBox: '0 0 24 24', width: 20, height: 20, 'aria-hidden': 'true' }, [h('path', { d, fill: 'currentColor' })])
}

/**
 * The chevron on the done step's walkthrough button (TqSetupKlaar), as an icon component the wizard
 * draws with its `size`.
 *
 * @spec openspec/changes/first-time-setup/tasks.md#6-live-check
 */
export const ChevronRight = {
	name: 'TqChevronRight',
	props: { size: { type: Number, default: 20 } },
	render() {
		return h('span', { class: 'material-design-icon', 'aria-hidden': 'true' }, [
			h('svg', { viewBox: '0 0 24 24', width: this.size, height: this.size }, [h('path', { d: mdiChevronRight, fill: 'currentColor' })]),
		])
	},
}

/**
 * A switch drawn as the boards draw it: a bordered card, the label and help on the start, the switch
 * on the end (TqSetupAppmenu).
 *
 * @param {object} row The switch: id, label, checked, onChange, hint, disabled.
 * @return {object} The vnode.
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-apps-menu-step-is-one-switch-req-tqfs-010
 */
function switchCard({ id, label, checked, onChange, hint, disabled }) {
	return h('div', { class: ['tq-setup__switch-card', disabled === true ? 'tq-setup__switch-card--disabled' : null] }, [
		h('div', { class: 'tq-setup__switch-text' }, [
			h('label', { for: id }, label),
			hint ? h('p', { id: id + '-hint', class: 'tq-setup__hint' }, hint) : null,
		]),
		h('input', {
			id,
			type: 'checkbox',
			role: 'switch',
			class: 'tq-setup__toggle',
			checked,
			// Disabled, not hidden: it stays in the reading order with its hint (REQ-TQMT-004).
			disabled: disabled === true,
			'aria-describedby': hint ? id + '-hint' : undefined,
			onChange: (event) => onChange(event.target.checked === true),
		}),
	])
}

/**
 * A radio card.
 *
 * @param {object} card The card.
 * @param {string} card.name The group name.
 * @param {string} card.value The value.
 * @param {boolean} card.checked Whether it is chosen.
 * @param {Function} card.onPick Called when chosen.
 * @param {Array} card.children The card's content.
 * @return {object} The vnode.
 */
function radioCard({ name, value, checked, onPick, children }) {
	// Keyed by value: the cards are re-rendered on every pick, and an unkeyed list let Vue patch
	// the inputs in place, so the browser's own checked state stayed on another set's radio.
	return h('label', { key: value, class: 'tq-setup__card' + (checked ? ' tq-setup__card--on' : '') }, [
		h('input', { type: 'radio', name, value, checked, onChange: () => onPick(value) }),
		h('span', { class: 'tq-setup__card-body' }, children),
	])
}

/**
 * The wizard component.
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md
 */
export const SetupWizard = {
	name: 'ThematiqSetupWizard',
	props: {
		status: { type: Object, default: null },
		onClosed: { type: Function, default: () => {} },
	},
	setup(props) {
		const state = reactive({
			choices: initialChoices(props.status),
			sets: [],
			setsLoaded: false,
			storedSet: initialChoices(props.status).tokenSet,
			licences: [],
			showAll: false,
			stepId: 'welcome',
			result: null,
			uploadMessage: '',
		})
		return { state }
	},
	async mounted() {
		await this.loadCatalogue()
	},
	computed: {
		steps() {
			return visibleSteps(this.state.choices).map((id) => ({
				id,
				label: RAIL[id](),
				cancelLabel: t(APP_ID, 'Later'),
				submitLabel: id === 'done' && this.offersWalkthrough ? t(APP_ID, 'Start the apps menu walkthrough') : t(APP_ID, 'Close'),
			}))
		},
		chosenSet() {
			return this.state.sets.find((set) => set.id === this.state.choices.tokenSet) || { id: this.state.choices.tokenSet, name: this.state.choices.tokenSet }
		},
		/**
		 * The name of the chosen set as the done step shows it.
		 *
		 * @return {string} The set's name, or "Nextcloud default".
		 */
		setName() {
			const choices = this.state.choices
			return choices.tokenSet === NEXTCLOUD_DEFAULT ? t(APP_ID, 'Nextcloud default') : (this.chosenSet.name || choices.tokenSet)
		},
		/**
		 * The dialog's heading: the current step's own title, under the eyebrow
		 * "Set up Thematiq, step n of 6", as the TqSetup boards draw it.
		 *
		 * @return {string} The title.
		 * @spec openspec/changes/first-time-setup/tasks.md#6-live-check
		 */
		heading() {
			const titles = {
				welcome: () => t(APP_ID, 'Welcome to Thematiq'),
				'house-style': () => t(APP_ID, 'Choose the house style'),
				'nextcloud-theme': () => t(APP_ID, 'Update the Nextcloud theme'),
				'login-page': () => t(APP_ID, 'The login page'),
				'apps-menu': () => t(APP_ID, 'The apps menu'),
				done: () => t(APP_ID, 'Done, {set} is set', { set: this.setName }, undefined, { escape: false }),
			}
			const title = titles[this.state.stepId]
			return title ? title() : t(APP_ID, 'Set up Thematiq')
		},
		/**
		 * Whether the done step offers the walkthrough as its finishing button.
		 *
		 * @return {boolean} True with the apps menu on.
		 */
		offersWalkthrough() {
			return this.state.choices.menu.enabled === true
		},
		nextLabel() {
			if (this.state.stepId === 'welcome') {
				return t(APP_ID, 'Start')
			}
			return this.state.stepId === 'apps-menu' ? t(APP_ID, 'Apply') : t(APP_ID, 'Next')
		},
	},
	methods: {
		t,

		/**
		 * Close without storing anything (Later), or after Done.
		 *
		 * @return {void}
		 */
		close() {
			this.onClosed()
		},

		/**
		 * "Skip, keep Nextcloud as it is": record the skip, change nothing else.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-later-closes-the-wizard-for-this-page-skip-ends-it-req-tqfs-004
		 */
		async skip() {
			await request('POST', '/api/setup/action/skip', {})
			this.close()
		},

		/**
		 * Guard Next: the licence statement on the house style step, Apply on the apps menu step.
		 *
		 * @param {string} stepId The step being left.
		 * @return {Promise<true|string>} True to move on, or the reason it cannot.
		 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-apps-menu-step-is-one-switch-req-tqfs-010
		 */
		async validate(stepId) {
			if (stepId === 'house-style' && !houseStyleReady(this.state.choices, this.state.licences)) {
				return t(APP_ID, 'Confirm that you may use this house style first.')
			}
			return stepId === 'apps-menu' ? this.apply() : true
		},

		/**
		 * Apply: send every choice once. A refusal, or a part that failed, keeps step 5 open with why.
		 *
		 * @return {Promise<true|string>} True when every part was applied, else the reason.
		 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-nothing-changes-until-apply-req-tqfs-003
		 */
		async apply() {
			const answer = await request('POST', '/api/setup/action/apply', applyRequest(this.state.choices, this.state.licences))
			// A write that failed half-way answers with the parts (applied, failed) and an error status.
			if (!answer.ok && !Array.isArray(answer.data.failed)) {
				return reasonOf(answer, t(APP_ID, 'Apply did not work: {status}', { status: answer.status }))
			}
			this.state.result = answer.data
			if (answer.data.completed === true) {
				return true
			}
			const failed = partsOf(answer.data).filter((part) => !part.applied).map((part) => RAIL[part.id]()).join(', ')
			return t(APP_ID, 'Not applied: {parts}. Press Apply to try again.', { parts: failed })
		},

		/**
		 * Store an immediate start of the apps menu walkthrough and reload in the new house style.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-done-step-sums-up-and-offers-the-apps-menu-walkthrough-req-tqfs-011
		 */
		async startWalkthrough() {
			await request('PUT', '/api/preferences/' + WALKTHROUGH.key, { value: immediateWalkthrough() })
			window.location.reload()
		},

		/**
		 * "Upload your own set": store it among the own sets, then choose it.
		 *
		 * @param {File} file The set file.
		 * @return {Promise<void>}
		 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-house-style-step-chooses-a-token-set-and-its-licence-confirmation-req-tqfs-007
		 */
		async upload(file) {
			if (!file) {
				return
			}
			const form = new FormData()
			form.append('file', file)
			const answer = await request('POST', '/settings/tokensets/upload', form)
			if (!answer.ok) {
				this.state.uploadMessage = reasonOf(answer, t(APP_ID, 'The set could not be uploaded.'))
				return
			}
			await this.loadSets()
			this.state.choices.tokenSet = answer.data.id || this.state.choices.tokenSet
			this.state.uploadMessage = t(APP_ID, 'Uploaded. It is applied when you click Apply.')
		},

		/**
		 * Read the selectable sets again.
		 *
		 * @return {Promise<void>}
		 */
		async loadSets() {
			const sets = await request('GET', '/settings/tokensets')
			if (Array.isArray(sets.data.tokenSets)) {
				this.state.sets = sets.data.tokenSets
			}
		},

		welcome() {
			const point = (d, title, text) => h('div', { class: 'tq-setup__point' }, [
				h('span', { class: 'tq-setup__point-icon' }, [svgIcon(d)]),
				h('strong', title),
				h('span', { class: 'tq-setup__hint' }, text),
			])
			return h('div', { class: 'tq-setup__step' }, [
				h('p', t(APP_ID, 'Thematiq gives Nextcloud your organisation\'s house style: colours, fonts, logo and the apps menu, on every screen. You set it up in five short steps. You can change every choice later in Administration settings, Theming.')),
				h('div', { class: 'tq-setup__points' }, [
					point(mdiPaletteOutline, t(APP_ID, 'Choose a house style'), t(APP_ID, 'A token set of a Dutch government, or your own set.')),
					point(mdiDatabaseOutline, t(APP_ID, 'Nextcloud follows'), t(APP_ID, 'Logo, colours, favicon and the login page take on the house style.')),
					point(mdiDotsGrid, t(APP_ID, 'An apps menu made to measure'), t(APP_ID, 'Apps with a payoff, pinning to the top bar and Create.')),
				]),
				h('p', { class: 'tq-setup__info' }, [
					svgIcon(mdiInformationOutline),
					h('span', t(APP_ID, 'Only administrators see this screen. Later closes it; it comes back on the next page you open, until you finish or skip. A Nextcloud that thematiq already set up does not see this.')),
				]),
			])
		},

		licenceLine(set) {
			if (set.id === NEXTCLOUD_DEFAULT) {
				return t(APP_ID, 'No house style, only the apps menu')
			}
			const entry = this.state.licences.find((item) => item.id === set.id) || {}
			if ((entry.licence || {}).restricted !== true) {
				return t(APP_ID, 'Free to use')
			}
			return entry.confirmation ? t(APP_ID, 'Restricted licence, confirmed') : t(APP_ID, 'Restricted licence, confirmation needed')
		},

		/**
		 * Read the selectable sets and their licences once, and mark the step as loaded either way.
		 * On a fresh install the set catalogue is audited cold, which can take seconds; until then
		 * the house style step says it is loading instead of offering no cards and "All 0 sets".
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-house-style-step-chooses-a-token-set-and-its-licence-confirmation-req-tqfs-007
		 */
		async loadCatalogue() {
			try {
				const [sets, licences] = await Promise.all([
					request('GET', '/settings/tokensets'),
					request('GET', '/settings/tokensets/licences'),
				])
				this.state.sets = Array.isArray(sets.data.tokenSets) ? sets.data.tokenSets : []
				this.state.licences = Array.isArray(licences.data.sets) ? licences.data.sets : []
			} finally {
				this.state.setsLoaded = true
			}
		},

		/**
		 * The house style cards: the stored set and Nextcloud default first, then four more (and the
		 * chosen set when it is not among them) or all.
		 *
		 * @return {object} The vnode.
		 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-house-style-step-chooses-a-token-set-and-its-licence-confirmation-req-tqfs-007
		 */
		setCards() {
			const choices = this.state.choices
			// TqSetupHuisstijl: the stored set, the featured sets and Nextcloud default, five cards
			// and then the upload card. The order follows the stored set, not the pick, so it stays
			// put while the administrator picks; a set picked from the full list stays in view.
			const byId = (id) => this.state.sets.find((set) => set.id === id)
			const leadIds = []
			for (const id of [this.state.storedSet, ...FEATURED_SETS]) {
				if (id !== NEXTCLOUD_DEFAULT && !leadIds.includes(id) && byId(id) !== undefined && leadIds.length < 4) {
					leadIds.push(id)
				}
			}
			if (byId(NEXTCLOUD_DEFAULT) !== undefined) {
				leadIds.push(NEXTCLOUD_DEFAULT)
			}
			let shown = leadIds.map(byId)
			const rest = this.state.sets.filter((set) => !leadIds.includes(set.id))
			if (this.state.showAll) {
				shown = shown.concat(rest)
			} else if (!leadIds.includes(choices.tokenSet)) {
				shown = shown.concat(rest.filter((set) => set.id === choices.tokenSet))
			}
			const pick = (id) => {
				choices.tokenSet = id
				choices.confirmRestricted = false
			}
			const swatches = (colours) => h('span', { class: 'tq-setup__swatches', 'aria-hidden': 'true' }, colours.map((colour) => h('span', { class: 'tq-setup__swatch', style: { background: colour } })))
			const cards = shown.map((set) => radioCard({
				name: 'tq-setup-set',
				value: set.id,
				checked: set.id === choices.tokenSet,
				onPick: pick,
				children: [
					h('strong', set.id === NEXTCLOUD_DEFAULT ? t(APP_ID, 'Nextcloud default') : set.name),
					h('span', { class: 'tq-setup__hint' }, this.licenceLine(set)),
					swatches([(set.theming || {}).primary_color || 'var(--color-primary-element)', (set.theming || {}).background_color || 'var(--color-main-background)']),
				],
			}))
			cards.push(h('label', { key: 'tq-setup-upload', class: 'tq-setup__card tq-setup__card--upload' }, [
				h('input', { type: 'file', class: 'tq-setup__upload-input', accept: '.json,application/json', onChange: (event) => this.upload(event.target.files && event.target.files[0]) }),
				h('span', { class: 'tq-setup__card-body' }, [
					h('strong', t(APP_ID, 'Upload your own set')),
					h('span', { class: 'tq-setup__hint' }, t(APP_ID, 'A token file from your designer.')),
					swatches(['var(--color-background-dark)', 'var(--color-background-dark)', 'var(--color-background-dark)']),
				]),
			]))
			return h('div', { class: 'tq-setup__cards', role: 'radiogroup', 'aria-label': t(APP_ID, 'House style') }, cards)
		},

		/**
		 * The line under the cards that sums up the chosen set.
		 *
		 * @return {string} The line.
		 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-house-style-step-chooses-a-token-set-and-its-licence-confirmation-req-tqfs-007
		 */
		setSummary() {
			if (this.state.choices.tokenSet === NEXTCLOUD_DEFAULT) {
				return t(APP_ID, 'Nextcloud keeps its own look; thematiq adds only the apps menu.')
			}
			const theming = this.chosenSet.theming || {}
			return t(APP_ID, '{name}: primary {primary}, background {background}.', {
				name: this.chosenSet.name || this.state.choices.tokenSet,
				primary: theming.primary_color || '-',
				background: theming.background_color || '-',
			})
		},

		houseStyle() {
			const needed = confirmationNeeded(this.state.choices.tokenSet, this.state.licences)
			const message = this.state.uploadMessage
			return h('div', { class: 'tq-setup__step' }, [
				h('p', t(APP_ID, 'A house style is a token set: colours, fonts, corners and spacing in one file. Choose your organisation\'s set. You use another government\'s set only with its permission; thematiq asks for it in the next step.')),
				h('strong', { class: 'tq-setup__label' }, t(APP_ID, 'Token set')),
				this.state.setsLoaded
					? this.setCards()
					: h('p', { class: 'tq-setup__hint', role: 'status', 'data-testid': 'tq-setup-sets-loading' }, t(APP_ID, 'Loading the house styles…')),
				h('div', { class: 'tq-setup__summary-panel' }, [
					h('p', { class: 'tq-setup__summary', 'aria-live': 'polite' }, this.setSummary()),
					this.state.showAll || !this.state.setsLoaded
						? null
						: h('button', { type: 'button', class: 'tq-setup__link', onClick: () => { this.state.showAll = true } }, t(APP_ID, 'All {count} sets in the gallery', { count: this.state.sets.length })),
				]),
				message ? h('p', { class: 'tq-setup__hint', role: 'status' }, message) : null,
				needed ? this.licence(needed) : null,
			])
		},

		licence(entry) {
			const choices = this.state.choices
			const licence = entry.licence || {}
			return h('div', { class: 'tq-setup__licence', role: 'group', 'aria-labelledby': 'tq-setup-licence-title' }, [
				h('h3', { id: 'tq-setup-licence-title' }, t(APP_ID, 'This house style belongs to {holder}', { holder: licence.holder || entry.name })),
				h('p', t(APP_ID, 'Its licence allows the logo and house style only for the holder or with its permission.')),
				h('p', { class: 'tq-setup__hint' }, [licence.package, licence.version, licence.id].filter(Boolean).join(' · ')),
				h('div', { class: 'tq-setup__check' }, [
					h('input', {
						id: 'tq-setup-licence-statement',
						type: 'checkbox',
						class: 'checkbox',
						checked: choices.confirmRestricted,
						onChange: (event) => { choices.confirmRestricted = event.target.checked === true },
					}),
					h('label', { for: 'tq-setup-licence-statement' }, t(APP_ID, 'We are this organisation, or we have its permission to use this house style.')),
				]),
				h('p', { class: 'tq-setup__hint' }, t(APP_ID, 'Your name and the time go into the audit log. Next works once you confirm this.')),
			])
		},

		nextcloudTheme() {
			const choices = this.state.choices
			const theming = this.chosenSet.theming || {}
			const now = (name) => (getComputedStyle(document.documentElement).getPropertyValue(name) || '').trim() || '-'
			// The value goes into another translated line: not escaped twice (Vue writes text, not HTML).
			const nowLine = (value) => t(APP_ID, 'Now: {value}', { value }, undefined, { escape: false })
			const file = (path) => (path ? path.split('/').pop() : '-')
			const swatch = (colour) => h('span', { class: 'tq-setup__cell-preview', style: { background: colour || 'transparent' }, 'aria-hidden': 'true' })
			const image = (path) => h('span', { class: 'tq-setup__cell-preview tq-setup__cell-preview--image', 'aria-hidden': 'true' }, path ? [h('img', { src: appFile(path), alt: '' })] : [])
			const cell = (label, preview, value, lines) => h('div', { class: 'tq-setup__cell' }, [
				h('span', { class: 'tq-setup__cell-label' }, label),
				preview,
				h('strong', value),
				...lines.filter(Boolean).map((line) => h('span', { class: 'tq-setup__hint' }, line)),
			])
			const favicon = theming.favicon || theming.logo
			return h('div', { class: 'tq-setup__step' }, [
				h('p', t(APP_ID, 'Nextcloud has a theme of its own: logo, colours and favicon. Thematiq sets them to the set, so the apps on phone and computer, the emails and the login page follow the house style too.')),
				switchCard({
					id: 'tq-setup-follows',
					label: t(APP_ID, 'Keep the Nextcloud theme in line with the set'),
					checked: choices.nextcloudThemeFollows,
					onChange: (on) => { choices.nextcloudThemeFollows = on },
					hint: t(APP_ID, 'Also on the next change of set. Off: thematiq leaves the Nextcloud theme alone.'),
				}),
				h('div', { class: 'tq-setup__cells tq-setup__cells--four' }, [
					cell(t(APP_ID, 'Primary colour'), swatch(theming.primary_color), theming.primary_color || '-', [nowLine(now('--color-primary'))]),
					cell(t(APP_ID, 'Background colour'), swatch(theming.background_color), theming.background_color || '-', [nowLine(now('--color-main-background'))]),
					cell(t(APP_ID, 'Logo'), image(theming.logo), file(theming.logo), [theming.logo_dark ? t(APP_ID, 'With a dark version.') : null, nowLine(t(APP_ID, 'Nextcloud logo'))]),
					cell(t(APP_ID, 'Favicon'), image(favicon), file(favicon), [t(APP_ID, 'In the browser tab and on the home screen of your phone.'), nowLine(t(APP_ID, 'Nextcloud favicon'))]),
				]),
				h('p', { class: 'tq-setup__info' }, [
					svgIcon(mdiInformationOutline),
					h('span', t(APP_ID, 'What Nextcloud has now is kept as a version in the log.')),
				]),
			])
		},

		loginPage() {
			const choices = this.state.choices
			const styles = [
				['house-style', t(APP_ID, 'Follow the house style'), t(APP_ID, 'A plain background in the colour of the set, the logo above it, the login card with the primary button.')],
				['nextcloud', t(APP_ID, 'Nextcloud default'), t(APP_ID, 'Nextcloud\'s own background image and layout. Only the logo changes.')],
			]
			return h('div', { class: 'tq-setup__step' }, [
				h('p', t(APP_ID, 'The login page is the first thing everyone sees. Choose whether it follows the house style or stays as Nextcloud draws it.')),
				h('div', { class: 'tq-setup__login' }, [
					h('div', { class: 'tq-setup__login-choices' }, [
						h('strong', { id: 'tq-setup-login-label', class: 'tq-setup__label' }, t(APP_ID, 'Login page style')),
						h('div', { class: 'tq-setup__cards tq-setup__cards--stack', role: 'radiogroup', 'aria-labelledby': 'tq-setup-login-label' }, styles.map(([value, label, text]) => radioCard({
							name: 'tq-setup-login',
							value,
							checked: choices.loginStyle === value,
							onPick: (picked) => { choices.loginStyle = picked },
							children: [h('strong', label), h('span', { class: 'tq-setup__hint' }, text)],
						}))),
						h('div', { class: 'tq-setup__check' }, [
							h('input', { id: 'tq-setup-slogan', type: 'checkbox', class: 'checkbox', checked: choices.hideSlogan, 'aria-describedby': 'tq-setup-slogan-hint', onChange: (event) => { choices.hideSlogan = event.target.checked === true } }),
							h('label', { for: 'tq-setup-slogan' }, t(APP_ID, 'Hide the Nextcloud slogan')),
							h('p', { id: 'tq-setup-slogan-hint', class: 'tq-setup__hint' }, t(APP_ID, 'The slogan stays in the theme, but not under the logo.')),
						]),
					]),
					this.loginPreview(),
				]),
			])
		},

		/**
		 * A small login page in the chosen style (TqSetupInlogpagina).
		 *
		 * @return {object} The vnode.
		 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-login-page-step-sets-the-login-page-style-req-tqfs-009
		 */
		loginPreview() {
			const choices = this.state.choices
			const house = choices.loginStyle === 'house-style'
			const colour = (house ? (this.chosenSet.theming || {}).primary_color : null) || 'var(--color-primary-element)'
			const name = house ? this.chosenSet.name : 'Nextcloud'
			return h('div', { class: ['tq-setup__login-preview', house ? null : 'tq-setup__login-preview--nextcloud'], 'aria-hidden': 'true' }, [
				h('div', { class: 'tq-setup__login-card' }, [
					h('strong', t(APP_ID, 'Log in')),
					h('span', { class: 'tq-setup__login-field' }, t(APP_ID, 'Account name or email')),
					h('span', { class: 'tq-setup__login-field' }, t(APP_ID, 'Password')),
					h('span', { class: 'tq-setup__login-button', style: { background: colour } }, t(APP_ID, 'Log in')),
					h('span', { class: 'tq-setup__login-link', style: { color: colour } }, t(APP_ID, 'Forgot password?')),
				]),
				h('span', { class: 'tq-setup__login-footer' }, choices.hideSlogan ? name : name + ' · ' + t(APP_ID, 'a safe home for all your data')),
			])
		},

		stepper(id, label, key) {
			const menu = this.state.choices.menu
			const range = MENU_RANGES[key]
			const options = []
			for (let value = range[0]; value <= range[1]; value++) {
				options.push(h('option', { value, selected: menu[key] === value }, String(value)))
			}
			return h('div', { class: 'tq-setup__stepper' }, [
				h('label', { for: id }, label),
				h('select', { id, value: menu[key], onChange: (event) => { menu[key] = Number(event.target.value) } }, options),
			])
		},

		appsMenu() {
			const menu = this.state.choices.menu
			const card = (key, label, hint, extra = {}) => switchCard({
				id: 'tq-setup-' + key,
				label,
				hint,
				checked: menu[key],
				onChange: (on) => { menu[key] = on },
				...extra,
			})
			return h('div', { class: 'tq-setup__step' }, [
				h('p', t(APP_ID, 'The thematiq apps menu replaces the standard menu: apps with a title and payoff, pinning to the top bar, Create and an order of their own for each person. This is the default for everyone; afterwards everyone changes their own order and pinned apps.')),
				switchCard({
					id: 'tq-setup-menu',
					label: t(APP_ID, 'Use the thematiq apps menu'),
					checked: menu.enabled,
					onChange: (on) => { menu.enabled = on },
					hint: t(APP_ID, 'On or off, there is no other choice. Off: Nextcloud keeps its own menu, the rest of the house style stays.'),
				}),
				menu.enabled
					? h('div', { class: 'tq-setup__menu' }, [
						h('div', { class: 'tq-setup__cells' }, [
							this.stepper('tq-setup-columns', t(APP_ID, 'Columns'), 'columns'),
							this.stepper('tq-setup-rows', t(APP_ID, 'Rows above More apps'), 'rowsAboveFold'),
						]),
						h('div', { class: 'tq-setup__section', role: 'heading', 'aria-level': 3 }, t(APP_ID, 'Display')),
						// apps-menu-show-titles REQ-TQMT-004: Show titles before Show payoff; the payoff
						// switch is disabled while titles are off and keeps its value.
						h('div', { class: 'tq-setup__cells' }, [
							card('titles', t(APP_ID, 'Show titles'), t(APP_ID, 'The name of the app under or next to the icon. Off: icons only, the name appears as a tooltip.')),
							card('payoff', t(APP_ID, 'Show payoff'), menu.titles === false ? t(APP_ID, 'Only with Show titles.') : t(APP_ID, 'One line under each app on what it does.'), { disabled: menu.titles === false }),
							card('showCreateRow', t(APP_ID, 'Create row in the apps menu'), t(APP_ID, 'Document, spreadsheet, presentation, whiteboard.')),
							card('showExternalApps', t(APP_ID, 'Show external apps'), t(APP_ID, 'OpenProject, XWiki and what you add yourself.')),
						]),
						h('div', { class: 'tq-setup__section', role: 'heading', 'aria-level': 3 }, t(APP_ID, 'Top bar')),
						h('div', { class: 'tq-setup__cells' }, [
							card('showCreateButton', t(APP_ID, 'Create button in the top bar'), t(APP_ID, 'The plus button before the notifications, on every page.')),
							card('showContactsMenu', t(APP_ID, 'Contacts button in the top bar'), t(APP_ID, 'The address book next to the notifications. Off: the button disappears, the Contacts app stays in the menu.')),
						]),
						h('p', { class: 'tq-setup__note' }, t(APP_ID, 'Change later: in the menu itself, under Menu settings, tab Configuration.')),
					])
					: null,
			])
		},

		done() {
			const choices = this.state.choices
			const name = this.setName
			const theming = this.chosenSet.theming || {}
			const plain = { escape: false }
			const details = {
				'house-style': [
					t(APP_ID, 'House style: {set}', { set: name }, undefined, plain),
					theming.primary_color ? t(APP_ID, 'Primary {colour}', { colour: theming.primary_color }, undefined, plain) : '',
				],
				'nextcloud-theme': [t(APP_ID, 'Nextcloud theme updated'), t(APP_ID, 'Logo, primary colour, background colour and favicon follow the set')],
				'login-page': [
					choices.loginStyle === 'house-style' ? t(APP_ID, 'Login page follows the house style') : t(APP_ID, 'Login page stays Nextcloud\'s own'),
					choices.hideSlogan ? t(APP_ID, 'Slogan hidden') : '',
				],
				'apps-menu': [
					choices.menu.enabled ? t(APP_ID, 'Apps menu on') : t(APP_ID, 'Nextcloud\'s own apps menu'),
					choices.menu.enabled ? t(APP_ID, '{columns} columns, {rows} rows above More apps', { columns: choices.menu.columns, rows: choices.menu.rowsAboveFold }, undefined, plain) : '',
				],
			}
			const part = (item) => {
				const [title, line] = details[item.id]
				return h('li', { class: ['tq-setup__part', item.applied ? 'tq-setup__part--ok' : 'tq-setup__part--failed'] }, [
					svgIcon(item.applied ? mdiCheckCircle : mdiAlertCircle),
					h('span', { class: 'tq-setup__part-text' }, [
						h('strong', item.applied ? title : t(APP_ID, 'Not applied: {part}', { part: RAIL[item.id]() }, undefined, plain)),
						line && item.applied ? h('span', { class: 'tq-setup__hint' }, line) : null,
					]),
				])
			}
			return h('div', { class: 'tq-setup__step' }, [
				h('p', { class: 'tq-setup__applied', role: 'status' }, [
					svgIcon(mdiCheckCircle),
					h('span', [h('strong', t(APP_ID, 'Applied.')), ' ', t(APP_ID, 'Everyone sees the house style on the next page they open.')]),
				]),
				h('ul', { class: 'tq-setup__parts' }, partsOf(this.state.result).map(part)),
				choices.menu.enabled
					? h('div', { class: 'tq-setup__tour' }, [
						h('span', { class: 'tq-setup__point-icon' }, [svgIcon(mdiDotsGrid)]),
						h('span', { class: 'tq-setup__part-text' }, [
							h('strong', t(APP_ID, 'See how the new apps menu works?')),
							h('span', { class: 'tq-setup__hint' }, t(APP_ID, 'A few short steps: opening, ordering, pinning, Create and the settings. It takes a minute. Everyone gets it the first time they open the menu.')),
						]),
					])
					: null,
				h('p', { class: 'tq-setup__note' }, t(APP_ID, 'You can always set up again from the version card in Administration settings, Theming.')),
			])
		},

		/**
		 * The footer's start: Skip as a link on every step but done; on done, Close
		 * beside the walkthrough as the primary button (TqSetupKlaar).
		 *
		 * @param {object} scope The wizard's actions-start scope.
		 * @return {Array} The buttons.
		 * @spec openspec/changes/first-time-setup/tasks.md#6-live-check
		 */
		footer(scope) {
			if (scope.currentStep && scope.currentStep.id === 'done') {
				return this.offersWalkthrough
					? [h('button', { type: 'button', class: 'tq-setup__close', 'data-testid': 'tq-setup-close', onClick: () => this.close() }, t(APP_ID, 'Close'))]
					: []
			}
			return [h('button', { type: 'button', class: 'tq-setup__skip', 'data-testid': 'tq-setup-skip', disabled: scope.loading, onClick: () => this.skip() }, t(APP_ID, 'Skip, keep Nextcloud as it is'))]
		},

		/**
		 * The finishing button of the done step: the walkthrough with the apps menu on, else Close.
		 *
		 * @return {Promise<void>|void}
		 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
		 */
		finish() {
			return this.offersWalkthrough ? this.startWalkthrough() : this.close()
		},
	},
	render() {
		const bodies = {
			welcome: this.welcome,
			'house-style': this.houseStyle,
			'nextcloud-theme': this.nextcloudTheme,
			'login-page': this.loginPage,
			'apps-menu': this.appsMenu,
			done: this.done,
		}
		const slots = { 'actions-start': (scope) => this.footer(scope) }
		this.steps.forEach((step) => {
			slots['step-' + step.id] = () => bodies[step.id]()
		})
		return h(CnWizardDialog, {
			look: 'board',
			width: 'wizard',
			dialogTitle: this.heading,
			eyebrowContext: t(APP_ID, 'Set up Thematiq'),
			stepEyebrow: t(APP_ID, '{context}, step {step} of {total}'),
			submitIcon: this.offersWalkthrough ? ChevronRight : null,
			steps: this.steps,
			initialStep: 'welcome',
			nextLabel: this.nextLabel,
			backLabel: t(APP_ID, 'Back'),
			cancelLabel: t(APP_ID, 'Later'),
			submitLabel: t(APP_ID, 'Close'),
			cancellable: this.state.stepId !== 'done',
			nextDisabled: this.state.stepId === 'house-style' && !houseStyleReady(this.state.choices, this.state.licences),
			validate: (stepId) => this.validate(stepId),
			onStepChange: (payload) => { this.state.stepId = payload.stepId },
			onSubmit: () => this.finish(),
			onClose: () => this.close(),
		}, slots)
	},
}

/**
 * Open the wizard in its own container on `body`; once at a time.
 *
 * @param {object|null} [status] The setup status; the page's `setup_status` when left out.
 * @return {object|null} The app, or null when it is open already.
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
 */
export function open(status) {
	if (document.getElementById(CONTAINER_ID) !== null) {
		return null
	}
	const container = document.createElement('div')
	container.id = CONTAINER_ID
	container.setAttribute('data-thematiq-setup-wizard', '')
	document.body.appendChild(container)
	let app = null
	app = createApp(SetupWizard, {
		status: status === undefined ? preloadedStatus() : status,
		onClosed: () => {
			app.unmount()
			container.remove()
		},
	})
	app.mount(container)
	return app
}

window.thematiqSetupWizard = { open }
if (!window.thematiqSetupWizardManual) {
	open()
}
