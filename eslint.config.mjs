/**
 * ESLint flat config.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * js/ is shipped as written (no build step), as classic browser scripts that
 * read Nextcloud's globals. The tests run under Node. scripts/ (the token and
 * asset generators) is not linted yet: build-tokens.js carries dead loads that
 * need their own look.
 *
 * Two rules are relaxed on purpose, both for the ES5 style these files share:
 *   - unused catch bindings and unused trailing parameters are allowed: the
 *     files target browsers without optional catch binding in their style
 *     guide, and handlers keep the signature they are called with;
 *   - no-useless-assignment is off: a variable is initialised before the
 *     try or if that sets it, so its fallback value is visible at the top.
 */
import js from '@eslint/js'
import globals from 'globals'

const relaxed = {
	'no-unused-vars': ['error', { args: 'none', caughtErrors: 'none' }],
	'no-useless-assignment': 'off',
}

export default [
	{
		ignores: [
			'node_modules/**',
			'vendor/**',
			'docs/**',
			'coverage*/**',
			'l10n/**',
			'js/vendor/**',
			'build/**',
		],
	},
	js.configs.recommended,
	{
		files: ['js/**/*.js'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'script',
			globals: {
				...globals.browser,
				// The js/lib modules are dual-mode: module.exports, require()
				// and Buffer under Node for their tests, a window global in the
				// browser. Each use is guarded by a typeof check.
				module: 'readonly',
				require: 'readonly',
				Buffer: 'readonly',
				OC: 'readonly',
				OCA: 'readonly',
				OCP: 'readonly',
				t: 'readonly',
				n: 'readonly',
			},
		},
		rules: relaxed,
	},
	{
		files: ['tests/vitest/**/*.js'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: {
				...globals.browser,
				...globals.node,
				OC: 'writable',
				OCP: 'writable',
				t: 'writable',
				n: 'writable',
			},
		},
		rules: relaxed,
	},
	{
		// ES-module checks that drive a browser: Node globals for the script,
		// browser globals for the functions they evaluate in the page.
		files: ['tests/**/*.mjs'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: { ...globals.node, ...globals.browser },
		},
		rules: relaxed,
	},
	{
		files: ['tests/css/**/*.js', 'tests/l10n/**/*.js'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'commonjs',
			globals: globals.node,
		},
		rules: relaxed,
	},
]
