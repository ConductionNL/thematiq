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
 * A request to the server with the CSRF token; JSON in and out.
 *
 * @param {string} method The verb.
 * @param {string} path The path under /apps/thematiq.
 * @param {object} [body] The JSON body.
 * @return {Promise<{ok: boolean, status: number, data: object}>}
 */
export async function request(method, path, body) {
	const headers = { requesttoken: (window.OC && window.OC.requestToken) || '', 'OCS-APIRequest': 'true' }
	if (body !== undefined && !(body instanceof FormData)) {
		headers['Content-Type'] = 'application/json'
	}
	const response = await fetch(url(path), {
		method,
		headers,
		body: body === undefined ? undefined : (body instanceof FormData ? body : JSON.stringify(body)),
	})
	let data = {}
	try {
		data = await response.json()
	} catch (e) {
		data = {}
	}
	return { ok: response.ok, status: response.status, data: data || {} }
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
 * A labelled switch (`role="switch"`).
 *
 * @param {string} id The input id.
 * @param {string} label The label.
 * @param {boolean} checked Whether it is on.
 * @param {Function} onChange Called with the new value.
 * @param {string} [hint] The line under it.
 * @return {object} The vnode.
 */
function switchRow(id, label, checked, onChange, hint) {
	return h('div', { class: 'tq-setup__switch' }, [
		h('input', {
			id,
			type: 'checkbox',
			role: 'switch',
			class: 'checkbox',
			checked,
			'aria-describedby': hint ? id + '-hint' : undefined,
			onChange: (event) => onChange(event.target.checked === true),
		}),
		h('label', { for: id }, label),
		hint ? h('p', { id: id + '-hint', class: 'tq-setup__hint' }, hint) : null,
	])
}

/**
 * A radio card.
 *
 * @param {string} name The group name.
 * @param {string} value The value.
 * @param {boolean} checked Whether it is chosen.
 * @param {Function} onPick Called when chosen.
 * @param {Array} children The card's content.
 * @return {object} The vnode.
 */
function radioCard(name, value, checked, onPick, children) {
	return h('label', { class: 'tq-setup__card' + (checked ? ' tq-setup__card--on' : '') }, [
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
			licences: [],
			showAll: false,
			stepId: 'welcome',
			result: null,
			uploadMessage: '',
		})
		return { state }
	},
	async mounted() {
		const [sets, licences] = await Promise.all([
			request('GET', '/settings/tokensets'),
			request('GET', '/settings/tokensets/licences'),
		])
		this.state.sets = Array.isArray(sets.data.tokenSets) ? sets.data.tokenSets : []
		this.state.licences = Array.isArray(licences.data.sets) ? licences.data.sets : []
	},
	computed: {
		steps() {
			return visibleSteps(this.state.choices).map((id) => ({
				id,
				label: RAIL[id](),
				cancelLabel: t(APP_ID, 'Later'),
				submitLabel: t(APP_ID, 'Close'),
			}))
		},
		chosenSet() {
			return this.state.sets.find((set) => set.id === this.state.choices.tokenSet) || { id: this.state.choices.tokenSet, name: this.state.choices.tokenSet }
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
		 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-later-and-skip-req-tqfs-004
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
			if (stepId !== 'apps-menu') {
				return true
			}
			const answer = await request('POST', '/api/setup/action/apply', applyRequest(this.state.choices, this.state.licences))
			// A write that failed half-way answers with the parts (applied, failed) and an error status.
			if (!answer.ok && !Array.isArray(answer.data.failed)) {
				return answer.data.message || answer.data.error || t(APP_ID, 'Apply did not work: {status}', { status: answer.status })
			}
			this.state.result = answer.data
			if (answer.data.completed !== true) {
				const failed = (answer.data.failed || []).map((id) => RAIL[id] ? RAIL[id]() : id).join(', ')
				return t(APP_ID, 'Not applied: {parts}. Press Apply to try again.', { parts: failed })
			}
			return true
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
		 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-house-style-step-chooses-the-token-set-req-tqfs-007
		 */
		async upload(file) {
			if (!file) {
				return
			}
			const form = new FormData()
			form.append('file', file)
			const answer = await request('POST', '/settings/tokensets/upload', form)
			if (!answer.ok) {
				this.state.uploadMessage = answer.data.message || answer.data.error || t(APP_ID, 'The set could not be uploaded.')
				return
			}
			const sets = await request('GET', '/settings/tokensets')
			this.state.sets = Array.isArray(sets.data.tokenSets) ? sets.data.tokenSets : this.state.sets
			const id = answer.data.id || (answer.data.tokenSet && answer.data.tokenSet.id)
			if (id) {
				this.state.choices.tokenSet = id
			}
			this.state.uploadMessage = t(APP_ID, 'Uploaded. It is applied when you click Apply.')
		},

		welcome() {
			return h('div', { class: 'tq-setup__step' }, [
				h('h2', t(APP_ID, 'Welcome to Thematiq')),
				h('p', t(APP_ID, 'Thematiq gives Nextcloud your organisation\'s house style: colours, fonts, logo and the apps menu, on every screen. You set it up in five short steps. You can change every choice later in Administration settings, Theming.')),
				h('ul', { class: 'tq-setup__points' }, [
					h('li', t(APP_ID, 'Choose a house style')),
					h('li', t(APP_ID, 'Nextcloud follows')),
					h('li', t(APP_ID, 'An apps menu made to measure')),
				]),
				h('p', { class: 'tq-setup__note' }, t(APP_ID, 'Only administrators see this screen. Later closes it; it comes back on the next page you open, until you finish or skip. A Nextcloud that thematiq already set up does not see this.')),
			])
		},

		licenceLine(set) {
			const entry = this.state.licences.find((item) => item.id === set.id)
			if (set.id === NEXTCLOUD_DEFAULT) {
				return t(APP_ID, 'No house style, only the apps menu')
			}
			if (entry && entry.licence && entry.licence.restricted === true) {
				return entry.confirmation ? t(APP_ID, 'Restricted licence, confirmed') : t(APP_ID, 'Restricted licence, confirmation needed')
			}
			return t(APP_ID, 'Free to use')
		},

		houseStyle() {
			const choices = this.state.choices
			const all = this.state.sets
			const first = all.filter((set) => set.id === choices.tokenSet || set.id === NEXTCLOUD_DEFAULT)
			const rest = all.filter((set) => set.id !== choices.tokenSet && set.id !== NEXTCLOUD_DEFAULT)
			const shown = this.state.showAll ? first.concat(rest) : first.concat(rest.slice(0, 4))
			const needed = confirmationNeeded(choices.tokenSet, this.state.licences)
			const theming = this.chosenSet.theming || {}
			return h('div', { class: 'tq-setup__step' }, [
				h('h2', t(APP_ID, 'Choose the house style')),
				h('p', t(APP_ID, 'A house style is a token set: colours, fonts, corners and spacing in one file. Choose your organisation\'s set. You use another government\'s set only with its permission; thematiq asks for it in the next step.')),
				h('div', { class: 'tq-setup__cards', role: 'radiogroup', 'aria-label': t(APP_ID, 'House style') }, shown.map((set) =>
					radioCard('tq-setup-set', set.id, set.id === choices.tokenSet, (id) => {
						choices.tokenSet = id
						choices.confirmRestricted = false
					}, [
						h('span', { class: 'tq-setup__swatch', style: { background: (set.theming && set.theming.primary_color) || 'var(--color-primary-element)' }, 'aria-hidden': 'true' }),
						h('strong', set.id === NEXTCLOUD_DEFAULT ? t(APP_ID, 'Nextcloud default') : set.name),
						h('span', { class: 'tq-setup__hint' }, this.licenceLine(set)),
					]),
				)),
				h('p', { class: 'tq-setup__summary', 'aria-live': 'polite' }, choices.tokenSet === NEXTCLOUD_DEFAULT
					? t(APP_ID, 'Nextcloud keeps its own look; thematiq adds only the apps menu.')
					: t(APP_ID, '{name}: primary {primary}, background {background}.', { name: this.chosenSet.name || choices.tokenSet, primary: theming.primary_color || '-', background: theming.background_color || '-' })),
				this.state.showAll
					? null
					: h('button', { type: 'button', class: 'tq-setup__link', onClick: () => { this.state.showAll = true } }, t(APP_ID, 'All {count} sets in the gallery', { count: all.length })),
				h('label', { class: 'tq-setup__upload' }, [
					h('span', t(APP_ID, 'Upload your own set')),
					h('input', { type: 'file', accept: '.json,application/json', onChange: (event) => this.upload(event.target.files && event.target.files[0]) }),
				]),
				this.state.uploadMessage ? h('p', { class: 'tq-setup__hint', role: 'status' }, this.state.uploadMessage) : null,
				needed ? this.licence(needed) : null,
			])
		},

		licence(entry) {
			const choices = this.state.choices
			const licence = entry.licence || {}
			return h('div', { class: 'tq-setup__licence', role: 'group', 'aria-labelledby': 'tq-setup-licence-title' }, [
				h('h3', { id: 'tq-setup-licence-title' }, t(APP_ID, 'This house style belongs to {holder}', { holder: licence.holder || entry.name })),
				h('p', t(APP_ID, 'Its licence allows the logo and house style only for the holder or with its permission.')),
				h('p', { class: 'tq-setup__hint' }, [licence.package || '', licence.version ? ' ' + licence.version : '', licence.id ? ' · ' + licence.id : ''].join('')),
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
			const cell = (label, before, after) => h('div', { class: 'tq-setup__cell' }, [
				h('strong', label),
				h('span', t(APP_ID, 'Now: {value}', { value: before })),
				h('span', t(APP_ID, 'New: {value}', { value: after })),
			])
			return h('div', { class: 'tq-setup__step' }, [
				h('h2', t(APP_ID, 'Update the Nextcloud theme')),
				switchRow('tq-setup-follows', t(APP_ID, 'Keep the Nextcloud theme in line with the set'), choices.nextcloudThemeFollows,
					(on) => { choices.nextcloudThemeFollows = on },
					t(APP_ID, 'Also on the next change of set. Off: thematiq leaves the Nextcloud theme alone.')),
				h('div', { class: 'tq-setup__cells' }, [
					cell(t(APP_ID, 'Primary colour'), now('--color-primary'), theming.primary_color || '-'),
					cell(t(APP_ID, 'Background colour'), now('--color-main-background'), theming.background_color || '-'),
					cell(t(APP_ID, 'Logo'), t(APP_ID, 'Nextcloud logo'), theming.logo ? t(APP_ID, 'The set\'s logo, with a dark version') : '-'),
					cell(t(APP_ID, 'Favicon'), t(APP_ID, 'Nextcloud favicon'), t(APP_ID, 'From the set\'s logo')),
				]),
				h('p', { class: 'tq-setup__note' }, t(APP_ID, 'What Nextcloud has now is kept as a version in the log.')),
			])
		},

		loginPage() {
			const choices = this.state.choices
			return h('div', { class: 'tq-setup__step' }, [
				h('h2', t(APP_ID, 'The login page')),
				h('div', { class: 'tq-setup__cards', role: 'radiogroup', 'aria-label': t(APP_ID, 'Login page style') }, [
					radioCard('tq-setup-login', 'house-style', choices.loginStyle === 'house-style', (value) => { choices.loginStyle = value }, [h('strong', t(APP_ID, 'Follow the house style'))]),
					radioCard('tq-setup-login', 'nextcloud', choices.loginStyle === 'nextcloud', (value) => { choices.loginStyle = value }, [h('strong', t(APP_ID, 'Nextcloud default'))]),
				]),
				h('div', { class: 'tq-setup__check' }, [
					h('input', { id: 'tq-setup-slogan', type: 'checkbox', class: 'checkbox', checked: choices.hideSlogan, onChange: (event) => { choices.hideSlogan = event.target.checked === true } }),
					h('label', { for: 'tq-setup-slogan' }, t(APP_ID, 'Hide the Nextcloud slogan')),
				]),
				h('div', { class: 'tq-setup__login-preview', 'aria-hidden': 'true', style: { borderTopColor: choices.loginStyle === 'house-style' ? ((this.chosenSet.theming || {}).primary_color || 'var(--color-primary-element)') : '#0082c9' } }, [
					h('span', { class: 'tq-setup__login-name' }, choices.loginStyle === 'house-style' ? (this.chosenSet.name || '') : 'Nextcloud'),
					choices.hideSlogan ? null : h('span', { class: 'tq-setup__hint' }, t(APP_ID, 'a safe home for all your data')),
					h('span', { class: 'tq-setup__login-field' }),
					h('span', { class: 'tq-setup__login-field' }),
				]),
			])
		},

		stepper(id, label, key) {
			const menu = this.state.choices.menu
			const range = MENU_RANGES[key]
			return h('div', { class: 'tq-setup__stepper' }, [
				h('label', { for: id }, label),
				h('input', { id, type: 'number', min: range[0], max: range[1], value: menu[key], onChange: (event) => { menu[key] = Number(event.target.value) } }),
			])
		},

		appsMenu() {
			const menu = this.state.choices.menu
			return h('div', { class: 'tq-setup__step' }, [
				h('h2', t(APP_ID, 'The apps menu')),
				switchRow('tq-setup-menu', t(APP_ID, 'Use the thematiq apps menu'), menu.enabled, (on) => { menu.enabled = on },
					t(APP_ID, 'Off: Nextcloud keeps its own menu, the rest of the house style stays.')),
				menu.enabled
					? h('div', { class: 'tq-setup__menu' }, [
						this.stepper('tq-setup-columns', t(APP_ID, 'Columns'), 'columns'),
						this.stepper('tq-setup-rows', t(APP_ID, 'Rows above More apps'), 'rowsAboveFold'),
						switchRow('tq-setup-payoff', t(APP_ID, 'Show payoff'), menu.payoff, (on) => { menu.payoff = on }),
						switchRow('tq-setup-create', t(APP_ID, 'Show Create'), menu.showCreate, (on) => { menu.showCreate = on }),
						switchRow('tq-setup-external', t(APP_ID, 'Show external apps'), menu.showExternalApps, (on) => { menu.showExternalApps = on }),
						h('p', { class: 'tq-setup__note' }, t(APP_ID, 'Change later: in the menu itself, under Menu settings, tab Configuration.')),
					])
					: null,
			])
		},

		done() {
			const name = this.state.choices.tokenSet === NEXTCLOUD_DEFAULT ? t(APP_ID, 'Nextcloud default') : (this.chosenSet.name || this.state.choices.tokenSet)
			return h('div', { class: 'tq-setup__step' }, [
				h('h2', t(APP_ID, 'Done, {set} is set', { set: name })),
				h('p', { role: 'status' }, t(APP_ID, 'Applied.')),
				h('p', t(APP_ID, 'Everyone sees the house style on the next page they open.')),
				h('ul', { class: 'tq-setup__parts' }, partsOf(this.state.result).map((part) =>
					h('li', { class: part.applied ? 'tq-setup__part--ok' : 'tq-setup__part--failed' }, (part.applied ? '✓ ' : '! ') + RAIL[part.id]()),
				)),
				h('p', { class: 'tq-setup__note' }, t(APP_ID, 'You can always set up again from the version card in Administration settings, Theming.')),
			])
		},

		footer(scope) {
			if (scope.currentStep && scope.currentStep.id === 'done') {
				return this.state.choices.menu.enabled
					? [h('button', { type: 'button', class: 'button', 'data-testid': 'tq-setup-walkthrough', onClick: () => this.startWalkthrough() }, t(APP_ID, 'Start the apps menu walkthrough'))]
					: []
			}
			return [h('button', { type: 'button', class: 'button tq-setup__skip', 'data-testid': 'tq-setup-skip', disabled: scope.loading, onClick: () => this.skip() }, t(APP_ID, 'Skip, keep Nextcloud as it is'))]
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
			dialogTitle: t(APP_ID, 'Set up Thematiq'),
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
			onSubmit: () => this.close(),
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
