/**
 * @vitest-environment jsdom
 *
 * Golden for src/codeEditor.js mount(): the props the editor component gets,
 * the host and text area state, the label wiring and the write-back of an
 * edit, for every combination of the options. The component is a recording
 * double, so the snapshot pins what mount() passes, not how CodeMirror draws.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/own-component-preview/spec.md#requirement-the-playground-previews-a-builders-own-component
 */

import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'

const viewer = vi.hoisted(() => ({ renders: [], emit: null }))

vi.mock(
	'@conduction/nextcloud-vue/dist/esm/components/CnJsonViewer/CnJsonViewer.vue.js',
	async () => {
		const { h } = await import('vue')
		return {
			default: {
				props: {
					value: { type: String, default: '' },
					language: {
						type: String,
						default: 'json',
						validator: (language) =>
							['html', 'json', 'text'].includes(language),
					},
					height: { type: String, default: '' },
				},
				emits: ['update:value'],
				render() {
					viewer.renders.push({
						value: this.value,
						language: this.language,
						height: this.height,
					})
					viewer.emit = (next) => this.$emit('update:value', next)
					return h('div', { class: 'cm-editor' }, [
						h('div', { class: 'cm-content' }, this.value),
					])
				},
			},
		}
	},
)

const languages = [undefined, '', 'html', 'css', 'json']
const labels = [undefined, '', 'code-label']
const heights = [undefined, '', '320px']

/**
 * Mount the editor with one set of options and describe what happened.
 *
 * @param {object|undefined} options The options passed to mount(), or none.
 * @return {object} The observed state.
 */
async function observe(options) {
	document.body.innerHTML = `
		<label id="code-label" for="code">HTML</label>
		<textarea id="code"><p>Tekst</p></textarea>
	`
	viewer.renders.length = 0
	viewer.emit = null
	const field = document.getElementById('code')
	const inputs = []
	field.addEventListener('input', (event) => inputs.push(event.bubbles))

	const editor =
		options === undefined
			? window.NldesignCodeEditor.mount(field)
			: window.NldesignCodeEditor.mount(field, options)
	const host = document.querySelector('.nldesign-code-editor')
	const mounted = {
		hidden: field.hidden,
		hostIsNextSibling: field.nextElementSibling === host,
		hostClass: host.className,
		labelledBy: host
			.querySelector('.cm-content')
			.getAttribute('aria-labelledby'),
		renders: viewer.renders.slice(),
	}

	viewer.emit('<h1>Nieuw</h1>')
	const { nextTick } = await import('vue')
	await nextTick()
	const edited = {
		fieldValue: field.value,
		inputs,
		renders: viewer.renders.slice(mounted.renders.length),
		content: host.querySelector('.cm-content').textContent,
	}

	const result = editor.unmount()
	const unmounted = {
		result,
		hostGone: document.querySelector('.nldesign-code-editor') === null,
		hidden: field.hidden,
		fieldValue: field.value,
	}
	return { mounted, edited, unmounted }
}

describe('codeEditor mount golden', () => {
	beforeAll(async () => {
		await import('../../src/codeEditor.js')
	}, 30000)

	afterEach(() => {
		document.body.innerHTML = ''
	})

	it('without options', async () => {
		expect(await observe(undefined)).toMatchSnapshot()
	})

	for (const language of languages) {
		for (const labelledBy of labels) {
			for (const height of heights) {
				const options = {}
				if (language !== undefined) options.language = language
				if (labelledBy !== undefined) options.labelledBy = labelledBy
				if (height !== undefined) options.height = height
				it(`options ${JSON.stringify(options)}`, async () => {
					expect(await observe(options)).toMatchSnapshot()
				})
			}
		}
	}

	it('supports', () => {
		expect(
			['html', 'css', 'json', 'text', '', undefined].map((language) =>
				window.NldesignCodeEditor.supports(language),
			),
		).toMatchSnapshot()
	})
})
