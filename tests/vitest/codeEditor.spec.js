/**
 * @vitest-environment jsdom
 *
 * src/codeEditor.js: CnJsonViewer from @conduction/nextcloud-vue mounted over a
 * text area, which keeps its value; a language the component does not know yet
 * is shown as plain text.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/own-component-preview/spec.md#requirement-the-playground-previews-a-builders-own-component
 */

import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'

// CnJsonViewer only draws NcButton for JSON, which the playground never edits;
// the bundle takes it alone (src/nextcloudVue.js), the test none of it.
vi.mock('@nextcloud/vue', () => ({ NcButton: { render: () => null } }))

describe('the playground code editor', { timeout: 20000 }, () => {
	beforeAll(async () => {
		await import('../../src/codeEditor.js')
	}, 30000)

	afterEach(() => {
		document.body.innerHTML = ''
	})

	it('mounts CodeMirror over the field and puts the field back on unmount', () => {
		document.body.innerHTML = `
			<label id="code-label" for="code">HTML</label>
			<textarea id="code"><p>Tekst</p></textarea>
		`
		const field = document.getElementById('code')

		const editor = window.NldesignCodeEditor.mount(field, {
			language: 'html',
			labelledBy: 'code-label',
		})

		expect(field.hidden).toBe(true)
		const host = document.querySelector('.nldesign-code-editor')
		expect(host.querySelector('.cm-editor')).not.toBeNull()
		expect(host.querySelector('.cm-content').textContent).toContain(
			'<p>Tekst</p>',
		)
		expect(
			host.querySelector('.cm-content').getAttribute('aria-labelledby'),
		).toBe('code-label')

		editor.unmount()
		expect(document.querySelector('.nldesign-code-editor')).toBeNull()
		expect(field.hidden).toBe(false)
		expect(field.value).toBe('<p>Tekst</p>')
	})

	it('knows which languages the installed component highlights', () => {
		expect(window.NldesignCodeEditor.supports('html')).toBe(true)
		expect(window.NldesignCodeEditor.supports('not-a-language')).toBe(false)
	})
})
