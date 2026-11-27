/**
 * A webpack loader that puts a stylesheet on the page when the bundle runs.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * The apps menu walkthrough (src/menuWalkthrough.js) is one script added to a
 * page on demand, with no stylesheet of its own. Its components' styles come
 * with it: each imported .css file becomes a <style> element in the head.
 *
 * With the option `scopeOf` (a compiled component such as CnWalkthrough.vue.js)
 * only the rules of that component's scope are kept: @conduction/nextcloud-vue
 * ships every component's style in one 1.4 MB file, and the walkthrough needs
 * 32 rules of it.
 *
 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-the-apps-menu-has-a-walkthrough-with-one-step-per-feature-req-tqwt-001
 */

/* global module, require */
'use strict'

const fs = require('fs')

/**
 * Where the block that opens at a brace ends: just past its closing brace.
 *
 * @param {string} css The stylesheet.
 * @param {number} open The index of the opening brace.
 * @return {number} The index after the closing brace.
 */
function blockEnd(css, open) {
	let depth = 1
	let j = open + 1
	while (j < css.length && depth > 0) {
		depth += css[j] === '{' ? 1 : (css[j] === '}' ? -1 : 0)
		j++
	}
	return j
}

/**
 * The top-level blocks of a stylesheet: `{ prelude, body }` each.
 *
 * @param {string} css The stylesheet.
 * @return {Array<object>} The blocks.
 */
function blocks(css) {
	const out = []
	let i = 0
	let open = css.indexOf('{')
	while (open !== -1) {
		const end = blockEnd(css, open)
		out.push({ prelude: css.slice(i, open).trim(), body: css.slice(open + 1, end - 1) })
		i = end
		open = css.indexOf('{', i)
	}
	return out
}

/**
 * The top-level blocks of a stylesheet that mention a text, nested blocks
 * filtered the same way.
 *
 * @param {string} css The stylesheet.
 * @param {string} needle The text a kept rule's selector names.
 * @return {string} The kept rules.
 */
function keep(css, needle) {
	return blocks(css).map(({ prelude, body }) => {
		if (!prelude.startsWith('@')) {
			return prelude.includes(needle) ? prelude + '{' + body + '}' : ''
		}
		const inner = body.includes('{') ? keep(body, needle) : ''
		return inner === '' ? '' : prelude + '{' + inner + '}'
	}).join('')
}

/**
 * The scope id a compiled single-file component sets.
 *
 * @param {string} file The compiled component.
 * @return {string} Such as `data-v-744bb405`, or ''.
 */
function scopeId(file) {
	const match = /__scopeId\s*=\s*["'](data-v-[0-9a-f]+)["']/.exec(fs.readFileSync(file, 'utf8'))
	return match ? match[1] : ''
}

/**
 * The loader.
 *
 * @param {string} source The stylesheet.
 * @return {string} A module that adds it to the head and exports its text.
 */
module.exports = function styleInjectLoader(source) {
	const options = (this.getOptions && this.getOptions()) || {}
	let css = String(source)
	if (options.scopeOf) {
		this.addDependency(options.scopeOf)
		const id = scopeId(options.scopeOf)
		css = id === '' ? '' : keep(css, '[' + id + ']')
	}
	return 'var css = ' + JSON.stringify(css) + ';\n'
		+ 'if (css !== "" && typeof document !== "undefined") {\n'
		+ '\tvar style = document.createElement("style");\n'
		+ '\tstyle.setAttribute("data-thematiq-menu-walkthrough", "");\n'
		+ '\tstyle.textContent = css;\n'
		+ '\tdocument.head.appendChild(style);\n'
		+ '}\n'
		+ 'export default css;\n'
}

module.exports.keep = keep
module.exports.scopeId = scopeId
