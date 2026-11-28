/**
 * The setup wizard's bundle keeps the board look's dialog frame from nextcloud-vue's stylesheet: the
 * rules ship without a component scope, so scripts/webpack/style-inject-loader.js keeps them through
 * `alsoKeep` (webpack.setup-wizard.config.js), and nothing of another component comes with them.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/first-time-setup/tasks.md#6-live-check
 */

import { createRequire } from 'node:module'
import { describe, expect, it } from 'vitest'

const require = createRequire(import.meta.url)
const loader = require('../../scripts/webpack/style-inject-loader.js')
const config = require('../../webpack.setup-wizard.config.js')
const options = config.module.rules[0].use[0].options

const CSS = [
	'.cn-look-board{--cn-board-control-radius:8px}',
	'/*! cn-board-look-start */.cn-look-board{--cn-field-height:40px}',
	'.cn-look-board .app-navigation{display:flex}',
	'.cn-dialog-board .dialog__actions button{height:40px}',
	'.cn-dialog-board .cn-dialog__tertiary{color:red}',
	'.cn-dialog-header__eyebrow{font-weight:700}',
	'.cn-look-board .cn-stepper__label{white-space:normal}',
	'.cn-dialog-board .cn-assign-dialog__hint{color:blue}',
	'@media (max-width:600px){.cn-dialog-board .dialog__content{padding:0}.cn-edit-view{margin:0}}',
	'.other{color:green}',
].join('')

/**
 * Run the loader over a stylesheet with the wizard's options and give back the css it injects.
 *
 * @param {string} css The stylesheet.
 * @param {object} opts The loader options.
 * @return {string} The kept css.
 */
function run(css, opts) {
	const out = loader.call({ getOptions: () => opts, addDependency: () => {} }, css)
	return JSON.parse(/^var css = (.*);$/m.exec(out)[1])
}

describe('the setup wizard bundle\'s styles', () => {
	it('keeps the board tokens and the dialog frame, header and stepper rules', () => {
		const kept = run(CSS, { scopeOf: [], alsoKeep: options.alsoKeep })
		expect(kept).toContain('--cn-board-control-radius:8px')
		expect(kept).toContain('--cn-field-height:40px')
		expect(kept).toContain('.cn-dialog-board .dialog__actions button{height:40px}')
		expect(kept).toContain('.cn-dialog-board .cn-dialog__tertiary{color:red}')
		expect(kept).toContain('.cn-dialog-header__eyebrow{font-weight:700}')
		expect(kept).toContain('.cn-look-board .cn-stepper__label{white-space:normal}')
		expect(kept).toContain('@media (max-width:600px){.cn-dialog-board .dialog__content{padding:0}}')
	})

	it('leaves out another component\'s rules and the board look of the rest of the app', () => {
		const kept = run(CSS, { scopeOf: [], alsoKeep: options.alsoKeep })
		expect(kept).not.toContain('cn-assign-dialog')
		expect(kept).not.toContain('cn-edit-view')
		expect(kept).not.toContain('app-navigation')
		expect(kept).not.toContain('.other')
	})

	it('keeps nothing extra without the option', () => {
		expect(run(CSS, { scopeOf: [] })).toBe('')
		expect(loader.keep(CSS, '.cn-dialog-header', () => false)).toBe('')
	})
})
