/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin.js: the custom fonts panel. Uploading a font (name
 * first, the file, the inline refusal), the list of uploaded fonts with delete
 * after a confirmation, and the font library whose bundled fonts are assigned
 * to or taken off the body and heading roles.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/custom-fonts/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const BASE = '/index.php/apps/thematiq/settings/fonts'

let answers
let toast

/**
 * Answer the fonts endpoints from `answers`, keyed `METHOD url`; an Error rejects.
 */
function installGlobals() {
	global.t = (app, text, values = {}) => text.replace(/\{(\w+)\}/g, (match, key) => (key in values ? values[key] : match))
	global.n = (app, singular, plural, count) => (count === 1 ? singular : plural)
	toast = vi.fn()
	global.OC = {
		generateUrl: (url) => '/index.php' + url,
		linkTo: (app, path) => path,
		requestToken: 'test-token',
		Notification: { showTemporary: vi.fn() },
		dialogs: { confirm: vi.fn() },
	}
	global.OCP = {
		InitialState: { loadState: (app, key, fallback) => fallback },
		Toast: { message: toast },
	}
	window.OCP = global.OCP
	global.fetch = vi.fn((url, options = {}) => {
		const answer = answers[(options.method || 'GET') + ' ' + url]
		if (answer === undefined) {
			return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({}), headers: { get: () => null } })
		}
		if (answer instanceof Error) {
			return Promise.reject(answer)
		}
		return Promise.resolve({ ok: answer.status < 400, status: answer.status, json: () => Promise.resolve(answer.data) })
	})
}

/** Flush pending promise chains. */
async function flush(rounds = 8) {
	for (let i = 0; i < rounds; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

async function load() {
	vi.resetModules()
	await import('../../js/dialogs/admin-dialog.js?t=' + Math.random())
	await import('../../js/admin.js?t=' + Math.random())
	await flush()
}

const $ = (id) => document.getElementById(id)
const calls = (method, url) => global.fetch.mock.calls.filter(([u, o = {}]) => u === url && (o.method || 'GET') === method)
const rows = (id) => Array.from($(id).querySelectorAll('.thematiq-font-row')).map((row) => Array.from(row.children).map((el) => (el.classList.contains('thematiq-font-row__actions') ? Array.from(el.children).map((b) => b.textContent).join(' | ') : el.textContent)))

describe('admin.js custom fonts', () => {
	beforeEach(() => {
		answers = {
			['GET ' + BASE]: { status: 200, data: { fonts: [{ id: 'f1', name: 'Rijksoverheid Sans', role: 'heading', size: 40960 }, { id: 'f2', role: 'body', size: 100 }] } },
			['GET ' + BASE + '/library']: {
				status: 200,
				data: { library: [{ name: 'Fira Sans', licence: 'OFL-1.1', roles: ['body'] }, { name: 'Source Serif', licence: 'OFL-1.1' }] },
			},
		}
		installGlobals()
		document.body.innerHTML = `
			<div id="nldesign-settings" class="section">
				<input type="text" id="nldesign-font-name">
				<select id="nldesign-font-role"><option value="body">Body</option><option value="heading">Heading</option></select>
				<input type="file" id="nldesign-font-input">
				<button type="button" id="nldesign-font-upload-btn">Upload</button>
				<div id="nldesign-font-upload-result" style="display:none"></div>
				<div id="nldesign-font-list"></div>
				<div id="nldesign-font-library"></div>
			</div>
		`
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.OCP
		vi.restoreAllMocks()
	})

	it('lists the uploaded fonts and the library as the board: name and source, sample, licence, status, actions', async () => {
		await load()

		expect(rows('nldesign-font-list')).toEqual([
			['Rijksoverheid Sansuploaded, 40 kB', 'Waste and recycling', 'Own licence', 'In use for headings', 'Delete'],
			['f2uploaded, 1 kB', 'Waste and recycling', 'Own licence', 'In use for body text', 'Delete'],
		])
		expect(rows('nldesign-font-library')).toEqual([
			['Fira Sansbundled', 'Waste and recycling', 'OFL-1.1', 'In use for body text', 'Use for headings | Stop using for body text'],
			['Source Serifbundled', 'Waste and recycling', 'OFL-1.1', 'Available', 'Use for headings | Use for body text'],
		])
		expect($('nldesign-font-library').querySelector('.thematiq-font-row__sample').style.fontFamily).toContain('Fira Sans')
	})

	it('says so when there are no fonts', async () => {
		answers['GET ' + BASE] = { status: 200, data: {} }
		answers['GET ' + BASE + '/library'] = { status: 200, data: { library: [] } }
		await load()
		expect($('nldesign-font-list').textContent).toBe('No fonts uploaded yet.')
		expect($('nldesign-font-library').textContent).toBe('The app bundles no fonts.')
	})

	it('says so when a list cannot be loaded', async () => {
		answers['GET ' + BASE] = new Error('offline')
		answers['GET ' + BASE + '/library'] = new Error('offline')
		vi.spyOn(console, 'error').mockImplementation(() => {})
		await load()
		expect($('nldesign-font-list').textContent).toBe('Failed to load fonts.')
		expect($('nldesign-font-library').textContent).toBe('Failed to load the font library.')
	})

	it('asks for a display name before it opens the file picker', async () => {
		await load()
		const pick = vi.spyOn($('nldesign-font-input'), 'click').mockImplementation(() => {})
		$('nldesign-font-name').value = '   '
		$('nldesign-font-upload-btn').click()
		expect(pick).not.toHaveBeenCalled()
		expect(toast).toHaveBeenCalledWith('Enter a font display name first.')
		expect(document.activeElement).toBe($('nldesign-font-name'))

		$('nldesign-font-name').value = 'Rijks'
		$('nldesign-font-upload-btn').click()
		expect(pick).toHaveBeenCalled()
	})

	/** Choose files in the hidden input. */
	function choose(files) {
		Object.defineProperty($('nldesign-font-input'), 'files', { value: files, configurable: true })
		$('nldesign-font-input').dispatchEvent(new Event('change'))
	}

	it('uploads the chosen file with its name and role, and reloads the list', async () => {
		await load()
		answers['POST ' + BASE + '/upload'] = { status: 200, data: { status: 'ok' } }
		$('nldesign-font-name').value = '  Rijks Sans '
		$('nldesign-font-role').value = 'heading'
		choose([])
		await flush()
		expect(calls('POST', BASE + '/upload')).toHaveLength(0)

		const before = calls('GET', BASE).length
		choose([new File(['wOF2'], 'rijks.woff2')])
		await flush()

		const [[, options]] = calls('POST', BASE + '/upload')
		expect(options.headers).toEqual({ requesttoken: 'test-token' })
		expect(options.body.get('name')).toBe('Rijks Sans')
		expect(options.body.get('role')).toBe('heading')
		expect(options.body.get('font')).toBeInstanceOf(File)
		expect(toast).toHaveBeenCalledWith('Font uploaded. Reload the page to apply it.')
		expect($('nldesign-font-upload-result').style.display).toBe('none')
		expect(calls('GET', BASE).length).toBe(before + 1)
	})

	it('shows a refused upload inline and in a toast', async () => {
		await load()
		answers['POST ' + BASE + '/upload'] = { status: 422, data: { error: 'Not a WOFF2 file.' } }
		$('nldesign-font-name').value = 'Rijks'
		choose([new File(['x'], 'x.ttf')])
		await flush()

		expect($('nldesign-font-upload-result').style.display).toBe('block')
		expect($('nldesign-font-upload-result').textContent).toBe('Upload failed: Not a WOFF2 file.')
		expect(toast).toHaveBeenCalledWith('Upload failed: Not a WOFF2 file.')

		answers['POST ' + BASE + '/upload'] = new Error('offline')
		vi.spyOn(console, 'error').mockImplementation(() => {})
		choose([new File(['x'], 'x.woff2')])
		await flush()
		expect(toast).toHaveBeenCalledWith('Upload failed.')
	})

	it('deletes a font only after the confirmation, and says how it went', async () => {
		await load()
		const deleteButton = () => $('nldesign-font-list').querySelector('button')
		const dialog = () => $('thematiq-delete-font-dialog')
		deleteButton().click()
		expect(dialog().querySelector('h2').textContent).toBe('Delete font Rijksoverheid Sans?')
		expect(dialog().querySelector('.thematiq-admin-dialog__eyebrow').textContent).toBe('Typefaces')
		dialog().querySelector('.nldesign-dialog-cancel').click()
		expect(dialog()).toBeNull()
		expect(calls('DELETE', BASE + '/f1')).toHaveLength(0)

		answers['DELETE ' + BASE + '/f1'] = { status: 200, data: { status: 'ok' } }
		deleteButton().click()
		dialog().querySelector('.nldesign-dialog-confirm').click()
		await flush()
		expect(calls('DELETE', BASE + '/f1')[0][1].headers).toEqual({ requesttoken: 'test-token' })
		expect(toast).toHaveBeenCalledWith('Font deleted. Reload the page to refresh the styling.')
		expect(dialog()).toBeNull()

		answers['DELETE ' + BASE + '/f1'] = { status: 200, data: { status: 'error' } }
		deleteButton().click()
		dialog().querySelector('.nldesign-dialog-confirm').click()
		await flush()
		expect(toast).toHaveBeenLastCalledWith('Failed to delete font.')

		answers['DELETE ' + BASE + '/f1'] = new Error('offline')
		vi.spyOn(console, 'error').mockImplementation(() => {})
		toast.mockClear()
		deleteButton().click()
		dialog().querySelector('.nldesign-dialog-confirm').click()
		await flush()
		expect(toast).toHaveBeenCalledWith('Failed to delete font.')
	})

	it('assigns a library font to a role and takes one off, and reloads the library', async () => {
		await load()
		answers['POST ' + BASE + '/library'] = { status: 200, data: { status: 'ok' } }
		answers['DELETE ' + BASE + '/library/body'] = { status: 200, data: { status: 'ok' } }
		const button = (label) => Array.from($('nldesign-font-library').querySelectorAll('button')).find((b) => b.textContent === label)

		button('Use for body text').click()
		await flush()
		const [[, post]] = calls('POST', BASE + '/library')
		expect(JSON.parse(post.body)).toEqual({ name: 'Source Serif', role: 'body' })
		expect(post.headers).toEqual({ requesttoken: 'test-token', 'Content-Type': 'application/json' })
		expect(toast).toHaveBeenCalledWith('Font assigned. Reload the page to apply it.')

		button('Stop using for body text').click()
		await flush()
		expect(calls('DELETE', BASE + '/library/body')).toHaveLength(1)
		expect(toast).toHaveBeenCalledWith('Font removed from the role. Reload the page to apply it.')
		expect(calls('GET', BASE + '/library').length).toBe(3)
	})

	it('says why a library change was refused, or that it failed', async () => {
		await load()
		answers['POST ' + BASE + '/library'] = { status: 422, data: { error: 'Unknown font.' } }
		const button = (label) => Array.from($('nldesign-font-library').querySelectorAll('button')).find((b) => b.textContent === label)
		button('Use for headings').click()
		await flush()
		expect(toast).toHaveBeenCalledWith('Could not change the font: Unknown font.')

		answers['POST ' + BASE + '/library'] = new Error('offline')
		vi.spyOn(console, 'error').mockImplementation(() => {})
		button('Use for headings').click()
		await flush()
		expect(toast).toHaveBeenCalledWith('Could not change the font.')
	})
})
