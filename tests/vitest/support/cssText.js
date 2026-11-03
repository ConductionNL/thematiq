/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Read a stylesheet as text in one fixed layout, whatever its formatting: one
 * rule per block, selectors and values with their whitespace folded, no
 * comments. A spec that searches stylesheet text reads it through here, so a
 * formatter that wraps a long selector or value over several lines does not
 * change what the spec finds.
 */
import fs from 'node:fs'
import postcss from 'postcss'

/**
 * A value or selector with its whitespace folded: runs become one space, and
 * none is kept just inside parentheses or before a comma.
 *
 * @param {string} text The text as written.
 * @return {string} The folded text.
 */
export function compact(text) {
	return text
		.replace(/\s+/g, ' ')
		.replace(/\(\s+/g, '(')
		.replace(/\s+\)/g, ')')
		.replace(/\s+,/g, ',')
		.trim()
}

/**
 * Write one node of a parsed stylesheet in the fixed layout.
 *
 * @param {import('postcss').ChildNode} node The node.
 * @param {string} indent The indent of its lines.
 * @return {string} The node as text.
 */
function layout(node, indent) {
	if (node.type === 'decl') {
		return `${indent}${node.prop}: ${compact(node.value)}${node.important ? ' !important' : ''};\n`
	}
	if (node.type === 'comment') {
		return ''
	}
	const head =
		node.type === 'rule'
			? compact(node.selector)
			: `@${node.name} ${compact(node.params)}`
	if (node.nodes === undefined) {
		return `${indent}${head};\n`
	}
	return `${indent}${head} {\n${node.nodes.map((child) => layout(child, indent + '\t')).join('')}${indent}}\n`
}

/**
 * A stylesheet's text in the fixed layout.
 *
 * @param {string} css The stylesheet as written.
 * @return {string} The stylesheet in the fixed layout.
 */
export function normalisedCss(css) {
	return postcss
		.parse(css)
		.nodes.map((node) => layout(node, ''))
		.join('')
}

/**
 * Read a stylesheet from disk in the fixed layout.
 *
 * @param {string} file The absolute path.
 * @return {string} The stylesheet in the fixed layout.
 */
export function readCss(file) {
	return normalisedCss(fs.readFileSync(file, 'utf8'))
}
