/**
 * SPDX-FileCopyrightText: 2026 Conduction / NL Design System Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest configuration for the NL Design System app's frontend unit tests.
 *
 * thematiq is a CSS/PHP theming app — it has no Vue/Pinia frontend. The
 * testable client-side logic is the set of PURE design-token / colour
 * transforms used by the admin settings page, extracted into
 * js/lib/tokenTransforms.js (darkenHex, getPreviewColors,
 * normaliseColorForPicker, designSystemLabel). admin.js consumes them via the
 * window.NldesignTokenTransforms global the helper script registers.
 *
 * The transforms are framework-free, so the environment is `node` and no stubs
 * are needed. Vitest only collects tests/vitest/**.
 */

const path = require('path')

module.exports = {
	test: {
		environment: 'node',
		globals: false,
		include: ['tests/vitest/**/*.spec.{js,ts}'],
		exclude: ['tests/e2e/**', 'tests/integration/**', 'node_modules/**'],
		// The playground's code editor (src/codeEditor.js) imports
		// @conduction/nextcloud-vue, whose components import their own .css
		// files. Node cannot load those; processed by Vitest instead, the imports
		// resolve, and a test can mock what the component imports in turn.
		server: {
			deps: {
				inline: [/@conduction\/nextcloud-vue/],
			},
		},
	},
	resolve: {
		alias: [{ find: '@', replacement: path.resolve(__dirname, 'js') }],
	},
}
