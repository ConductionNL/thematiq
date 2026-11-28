/**
 * @vitest-environment jsdom
 *
 * The first-time setup wizard (src/setupWizard.js over src/setupWizard/model.js): the steps store
 * nothing, Later stores nothing, Skip runs `skip`, a restricted set needs the statement before Next,
 * Apply sends every choice once and a refusal or a failed part keeps step 5 open, and the done step
 * hands over to the apps menu walkthrough.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
	applyRequest,
	clamp,
	confirmationNeeded,
	houseStyleReady,
	immediateWalkthrough,
	initialChoices,
	partsOf,
	visibleSteps,
} from '../../src/setupWizard/model.js'

vi.mock('@conduction/nextcloud-vue/dist/esm/components/CnWizardDialog/CnWizardDialog.vue.js', () => ({ default: { name: 'CnWizardDialog', render: () => null } }))
vi.mock('@conduction/nextcloud-vue/dist/nextcloud-vue.css', () => ({}))
vi.mock('../../src/setupWizard.css', () => ({}))

const LICENCES = [
	{ id: 'amsterdam', name: 'Gemeente Amsterdam', licence: { holder: 'Gemeente Amsterdam', restricted: true }, confirmation: null },
	{ id: 'denhaag', name: 'Den Haag', licence: { holder: 'Den Haag', restricted: true }, confirmation: { by: 'admin' } },
]

describe('the setup model', () => {
	it('opens on the shipped defaults when nothing is set, and on the stored choices on Set up again', () => {
		const fresh = initialChoices({ completed: false, configured: false, current: { tokenSet: 'nextcloud' } })
		expect(fresh.tokenSet).toBe('nextcloud')
		expect(fresh.nextcloudThemeFollows).toBe(true)
		expect(fresh.menu.enabled).toBe(true)
		expect(fresh.menu.showCreateRow).toBe(true)
		expect(fresh.menu.showCreateButton).toBe(true)
		expect(fresh.menu.showContactsMenu).toBe(true)
		expect(fresh.menu.titles).toBe(true)

		const again = initialChoices({
			completed: { by: 'admin' },
			current: { tokenSet: 'zuiddrecht', nextcloudThemeFollows: false, loginStyle: 'nextcloud', hideSlogan: true, menu: { enabled: false } },
		})
		expect(again).toMatchObject({ tokenSet: 'zuiddrecht', nextcloudThemeFollows: false, loginStyle: 'nextcloud', hideSlogan: true })
		expect(again.menu.enabled).toBe(false)
		expect(initialChoices(null).tokenSet).toBe('nextcloud')
	})

	it('leaves out the Nextcloud theme step for Nextcloud default', () => {
		expect(visibleSteps({ tokenSet: 'nextcloud' })).toEqual(['welcome', 'house-style', 'login-page', 'apps-menu', 'done'])
		expect(visibleSteps({ tokenSet: 'amsterdam' })).toHaveLength(6)
	})

	it('asks for the statement only for a restricted set nobody confirmed', () => {
		expect(confirmationNeeded('amsterdam', LICENCES).id).toBe('amsterdam')
		expect(confirmationNeeded('denhaag', LICENCES)).toBeNull()
		expect(confirmationNeeded('zuiddrecht', LICENCES)).toBeNull()
		expect(houseStyleReady({ tokenSet: 'amsterdam', confirmRestricted: false }, LICENCES)).toBe(false)
		expect(houseStyleReady({ tokenSet: 'amsterdam', confirmRestricted: true }, LICENCES)).toBe(true)
	})

	it('sends every choice once; the menu off sends only enabled false; the confirmation only when needed', () => {
		const choices = initialChoices(null)
		choices.tokenSet = 'amsterdam'
		choices.confirmRestricted = true
		choices.menu.showCreateButton = false
		choices.menu.showContactsMenu = false
		choices.menu.columns = 9
		expect(applyRequest(choices, LICENCES)).toEqual({
			tokenSet: 'amsterdam',
			confirmRestricted: true,
			nextcloudThemeFollows: true,
			loginStyle: 'house-style',
			hideSlogan: false,
			menu: { enabled: true, columns: 5, rowsAboveFold: 2, titles: true, payoff: true, showCreateRow: true, showExternalApps: true, showCreateButton: false, showContactsMenu: false },
		})

		choices.menu.enabled = false
		choices.tokenSet = 'nextcloud'
		const off = applyRequest(choices, LICENCES)
		expect(off.menu).toEqual({ enabled: false })
		expect(off.nextcloudThemeFollows).toBe(false)
		expect(off).not.toHaveProperty('confirmRestricted')
	})

	it('holds numbers in range, lists the parts and starts the walkthrough at once', () => {
		expect(clamp('x', [3, 5], 4)).toBe(4)
		expect(clamp(1, [3, 5], 4)).toBe(3)
		expect(partsOf({ applied: ['house-style', 'apps-menu'], failed: ['login-page'] })).toEqual([
			{ id: 'house-style', applied: true },
			{ id: 'login-page', applied: false },
			{ id: 'apps-menu', applied: true },
		])
		expect(JSON.parse(immediateWalkthrough())).toEqual({ tourId: 'apps-menu', stepId: 'open', index: 0, immediate: true })
	})
})

describe('the wizard', () => {
	let calls
	let answers

	beforeEach(() => {
		calls = []
		answers = {}
		window.OC = { requestToken: 'token', generateUrl: (path) => '/index.php' + path }
		window.thematiqSetupWizardManual = true
		vi.stubGlobal('fetch', vi.fn((url, options) => {
			const body = options.body instanceof FormData ? options.body : (options.body ? JSON.parse(options.body) : undefined)
			calls.push({ url, method: options.method, body })
			const answer = answers[url] || { ok: true, status: 200, data: {} }
			return Promise.resolve({ ok: answer.ok, status: answer.status, json: () => Promise.resolve(answer.data) })
		}))
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.unstubAllGlobals()
		delete window.OC
		delete window.thematiqSetupWizardManual
	})

	/**
	 * The wizard component as a plain object over its methods, with the given choices.
	 *
	 * @param {object} [status] The setup status.
	 * @return {Promise<object>} The instance-like object.
	 */
	async function wizard(status = null) {
		const { SetupWizard } = await import('../../src/setupWizard.js')
		const closed = vi.fn()
		const vm = { ...SetupWizard.methods, onClosed: closed, closed, state: SetupWizard.setup({ status }).state }
		vm.state.licences = LICENCES
		return vm
	}

	it('stores nothing while the steps change the choices, and Later stores nothing', async () => {
		const vm = await wizard()
		vm.state.choices.tokenSet = 'zuiddrecht'
		expect(await vm.validate('house-style')).toBe(true)
		expect(await vm.validate('login-page')).toBe(true)
		vm.close()
		expect(vm.closed).toHaveBeenCalled()
		expect(calls).toEqual([])
	})

	it('Skip runs skip and closes', async () => {
		const vm = await wizard()
		await vm.skip()
		expect(calls).toEqual([{ url: '/index.php/apps/thematiq/api/setup/action/skip', method: 'POST', body: {} }])
		expect(vm.closed).toHaveBeenCalled()
	})

	it('holds Next on a restricted set until the statement is ticked', async () => {
		const vm = await wizard()
		vm.state.choices.tokenSet = 'amsterdam'
		expect(await vm.validate('house-style')).toBe('Confirm that you may use this house style first.')
		vm.state.choices.confirmRestricted = true
		expect(await vm.validate('house-style')).toBe(true)
	})

	it('Apply sends the choices once and moves on; a refusal keeps step 5 with the reason', async () => {
		const vm = await wizard()
		const apply = '/index.php/apps/thematiq/api/setup/action/apply'
		answers[apply] = { ok: false, status: 403, data: { error: 'restricted_set_not_confirmed', message: 'Confirm the licence first.' } }
		expect(await vm.validate('apps-menu')).toBe('Confirm the licence first.')

		answers[apply] = { ok: true, status: 200, data: { applied: ['house-style', 'login-page', 'apps-menu'], failed: [], completed: true } }
		expect(await vm.validate('apps-menu')).toBe(true)
		expect(calls.filter((call) => call.url === apply)).toHaveLength(2)
		expect(vm.state.result.completed).toBe(true)
	})

	it('offers Show titles before Show payoff; payoff is disabled while titles are off and keeps its value', async () => {
		const vm = await wizard()
		const inputs = (node, out = []) => {
			if (Array.isArray(node)) {
				node.forEach((child) => inputs(child, out))
			} else if (node && typeof node === 'object') {
				if (node.type === 'input' && node.props && node.props.role === 'switch') {
					out.push(node.props)
				}
				inputs(node.children, out)
			}
			return out
		}
		const switches = () => inputs(vm.appsMenu()).map((props) => [props.id, props.disabled])
		expect(switches().map(([id]) => id)).toEqual(['tq-setup-menu', 'tq-setup-titles', 'tq-setup-payoff', 'tq-setup-showCreateRow', 'tq-setup-showExternalApps', 'tq-setup-showCreateButton', 'tq-setup-showContactsMenu'])
		expect(switches()).toContainEqual(['tq-setup-payoff', false])

		vm.state.choices.menu.titles = false
		expect(switches()).toContainEqual(['tq-setup-payoff', true])
		expect(vm.state.choices.menu.payoff).toBe(true)
		expect(applyRequest(vm.state.choices, LICENCES).menu).toMatchObject({ titles: false, payoff: true })
	})

	it('names the parts that were not applied and keeps Apply open', async () => {
		const vm = await wizard()
		answers['/index.php/apps/thematiq/api/setup/action/apply'] = { ok: false, status: 500, data: { applied: ['login-page', 'apps-menu'], failed: ['house-style'], completed: false } }
		expect(await vm.validate('apps-menu')).toBe('Not applied: House style. Press Apply to try again.')
	})

	it('says the house styles are loading until the sets arrive, and offers the cards after', async () => {
		const vm = await wizard()
		const texts = (node) => (node == null ? [] : typeof node === 'string' ? [node] : [].concat(...[].concat(node.children || []).map(texts)))
		const loading = vm.houseStyle()
		expect(vm.state.setsLoaded).toBe(false)
		expect(texts(loading)).toContain('Loading the house styles…')
		expect(texts(loading).some((line) => line.startsWith('All '))).toBe(false)

		answers['/index.php/apps/thematiq/settings/tokensets'] = { ok: true, status: 200, data: { tokenSets: [{ id: 'nextcloud', name: 'Nextcloud' }, { id: 'zuiddrecht', name: 'Zuiddrecht' }] } }
		answers['/index.php/apps/thematiq/settings/tokensets/licences'] = { ok: true, status: 200, data: { sets: LICENCES } }
		await vm.loadCatalogue()
		expect(vm.state.setsLoaded).toBe(true)
		expect(vm.state.sets).toHaveLength(2)
		const loaded = texts(vm.houseStyle())
		expect(loaded).not.toContain('Loading the house styles…')
		expect(loaded).toContain('All 2 sets in the gallery')
		expect(loaded).toContain('Nextcloud default')
	})

	it('keeps the stored set and Nextcloud default first while picking, keys every card, and keeps a picked set in view', async () => {
		const vm = await wizard({ completed: false, configured: false, current: { tokenSet: 'nextcloud' } })
		vm.state.setsLoaded = true
		vm.state.sets = ['amsterdam', 'basis', 'college', 'demodam', 'duo', 'esdoornveen', 'nextcloud', 'rijkshuisstijl', 'rotterdam', 'zuiddrecht'].map((id) => ({ id, name: id }))
		const order = () => vm.setCards().children.map((card) => card.key)
		// TqSetupHuisstijl: the featured sets, Nextcloud default, then the upload card.
		expect(order()).toEqual(['zuiddrecht', 'rijkshuisstijl', 'amsterdam', 'rotterdam', 'nextcloud', 'tq-setup-upload'])

		vm.state.choices.tokenSet = 'amsterdam'
		expect(order()).toEqual(['zuiddrecht', 'rijkshuisstijl', 'amsterdam', 'rotterdam', 'nextcloud', 'tq-setup-upload'])
		const checked = vm.setCards().children.filter((card) => card.children[0].props.checked).map((card) => card.key)
		expect(checked).toEqual(['amsterdam'])

		vm.state.choices.tokenSet = 'duo'
		expect(order()).toEqual(['zuiddrecht', 'rijkshuisstijl', 'amsterdam', 'rotterdam', 'nextcloud', 'duo', 'tq-setup-upload'])
	})

	it('puts a stored set that is not featured first', async () => {
		const vm = await wizard({ completed: true, configured: true, current: { tokenSet: 'duo' } })
		vm.state.setsLoaded = true
		vm.state.sets = ['amsterdam', 'duo', 'nextcloud', 'rijkshuisstijl', 'rotterdam', 'zuiddrecht'].map((id) => ({ id, name: id }))
		expect(vm.setCards().children.map((card) => card.key)).toEqual(['duo', 'zuiddrecht', 'rijkshuisstijl', 'amsterdam', 'nextcloud', 'tq-setup-upload'])
	})

	it('stops saying loading when reading the sets fails', async () => {
		const vm = await wizard()
		vi.stubGlobal('fetch', vi.fn(() => Promise.reject(new Error('offline'))))
		await expect(vm.loadCatalogue()).rejects.toThrow('offline')
		expect(vm.state.setsLoaded).toBe(true)
	})

	it('Upload your own set stores the set among the own sets and chooses it, without applying it', async () => {
		const vm = await wizard()
		answers['/index.php/apps/thematiq/settings/tokensets/upload'] = { ok: true, status: 200, data: { id: 'custom-ours' } }
		answers['/index.php/apps/thematiq/settings/tokensets'] = { ok: true, status: 200, data: { tokenSets: [{ id: 'custom-ours', name: 'Ours' }] } }
		const file = new File(['{}'], 'ours.json', { type: 'application/json' })
		await vm.upload(file)
		expect(calls[0].url).toBe('/index.php/apps/thematiq/settings/tokensets/upload')
		expect(calls[0].body.get('file').name).toBe('ours.json')
		expect(vm.state.choices.tokenSet).toBe('custom-ours')
		expect(vm.state.uploadMessage).toBe('Uploaded. It is applied when you click Apply.')
		expect(calls.some((call) => call.url.includes('/api/setup/action/apply'))).toBe(false)

		answers['/index.php/apps/thematiq/settings/tokensets/upload'] = { ok: false, status: 400, data: { message: 'Not a token set.' } }
		await vm.upload(file)
		expect(vm.state.uploadMessage).toBe('Not a token set.')
	})

	it('hands over to the walkthrough: stores an immediate start and reloads', async () => {
		const reload = vi.fn()
		vi.stubGlobal('location', { reload })
		const vm = await wizard()
		await vm.startWalkthrough()
		expect(calls[0]).toEqual({
			url: '/index.php/apps/thematiq/api/preferences/apps_menu_walkthrough_seen_version-progress',
			method: 'PUT',
			body: { value: immediateWalkthrough() },
		})
		expect(reload).toHaveBeenCalled()
	})
})
