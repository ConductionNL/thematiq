/**
 * The playground's code fields as code editors.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * The editor is CnJsonViewer from @conduction/nextcloud-vue, the CodeMirror
 * editor the other Conduction apps use, mounted over a text area that stays in
 * the page: the text area keeps its id, its label and its value, and every edit
 * is written back to it with an `input` event, so js/playground.js reads and
 * listens to the text area exactly as it does without the editor.
 *
 * `npm run build:code-editor` bundles this file into js/vendor/codeEditor.js,
 * which registers `window.NldesignCodeEditor`. The playground falls back to the
 * plain text areas when that file is not there.
 *
 * A language the installed CnJsonViewer does not know, such as `css` today, is
 * shown as plain text, and highlights as soon as an update of the package adds it.
 *
 * @spec openspec/specs/own-component-preview/spec.md#requirement-the-playground-previews-a-builders-own-component
 */

import { createApp, h, ref } from 'vue'
import CnJsonViewer from '@conduction/nextcloud-vue/dist/esm/components/CnJsonViewer/CnJsonViewer.vue.js'

/**
 * Whether the installed CnJsonViewer highlights a language.
 *
 * @param {string} language The language.
 * @return {boolean}
 */
function supports(language) {
	const prop = CnJsonViewer.props && CnJsonViewer.props.language
	return prop !== undefined && typeof prop.validator === 'function'
		? prop.validator(language) === true
		: false
}

/**
 * Mount an editor over a text area.
 *
 * @param {HTMLTextAreaElement} textarea The field the editor stands in for.
 * @param {object} [options] Options.
 * @param {string} [options.language] The language of the code, such as `html` or `css`.
 * @param {string} [options.labelledBy] The id of the field's visible label.
 * @param {string} [options.height] The editor's height.
 * @return {{unmount: Function}} The way to take the editor away again.
 */
function mount(textarea, options = {}) {
	const host = document.createElement('div')
	host.className = 'nldesign-code-editor'
	textarea.after(host)
	textarea.hidden = true

	const value = ref(textarea.value)
	const language =
		options.language && supports(options.language) ? options.language : 'text'
	const app = createApp({
		render() {
			return h(CnJsonViewer, {
				value: value.value,
				language,
				height: options.height || '180px',
				'onUpdate:value': (next) => {
					value.value = next
					textarea.value = next
					textarea.dispatchEvent(new Event('input', { bubbles: true }))
				},
			})
		},
	})
	app.mount(host)

	// The editable region is named by the text area's own label.
	const content = host.querySelector('.cm-content')
	if (content !== null && options.labelledBy) {
		content.setAttribute('aria-labelledby', options.labelledBy)
	}

	return {
		unmount() {
			app.unmount()
			host.remove()
			textarea.hidden = false
		},
	}
}

window.NldesignCodeEditor = { mount, supports }
