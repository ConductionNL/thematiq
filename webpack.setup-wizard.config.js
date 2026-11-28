/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Bundles src/setupWizard.js, the first-time setup wizard (CnWizardDialog from
 * @conduction/nextcloud-vue), into js/vendor/setupWizard.js. js/setup-wizard-loader.js adds that
 * script only to an administrator's page while setup is due, so every other page loads none of it.
 * Gitignored, like the walkthrough's bundle. Styles travel in the script; of the package's
 * stylesheet only the wizard dialog's rules are kept.
 *
 * Run with `npm run build:setup-wizard`.
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
 */

const path = require('path')
const webpack = require('webpack')

const loader = path.join(__dirname, 'scripts', 'webpack', 'style-inject-loader.js')
const packageCss = /@conduction[\\/]nextcloud-vue[\\/]dist[\\/]nextcloud-vue\.css$/

/**
 * The board look's dialog frame, which nextcloud-vue ships unscoped (TqSetup boards): the board
 * tokens on `.cn-look-board`, and the `.cn-dialog-board`, `.cn-dialog-header` and `.cn-stepper` rules
 * that name no other component.
 *
 * @param {string} selector A rule's selector.
 * @return {boolean} Whether the wizard keeps it.
 * @spec openspec/changes/first-time-setup/tasks.md#6-live-check
 */
function boardDialogRule(selector) {
	const plain = selector.replace(/\/\*[\s\S]*?\*\//g, '').trim()
	if (plain === '.cn-look-board') {
		return true
	}
	return /\.cn-(dialog-board|dialog-header|stepper)(?![a-z-])/.test(plain) && !/\.cn-(?!dialog\b|dialog[-_]|look-board|wizard-dialog|stepper)[a-z]/.test(plain)
}

module.exports = {
	mode: 'production',
	entry: path.join(__dirname, 'src', 'setupWizard.js'),
	output: {
		path: path.join(__dirname, 'js', 'vendor'),
		filename: 'setupWizard.js',
	},
	resolve: {
		// sax (through @nextcloud/vue) names Node's stream for a code path the browser never takes.
		fallback: { stream: false },
		alias: {
						// Single-file components this app has no loader for (src/walkthroughIcons.js).
			'vue-material-design-icons/Close.vue$': path.join(__dirname, 'src', 'icons', 'Close.js'),
			'vue-material-design-icons/ChevronLeft.vue$': path.join(__dirname, 'src', 'icons', 'ChevronLeft.js'),
			'vue-material-design-icons/ChevronRight.vue$': path.join(__dirname, 'src', 'icons', 'ChevronRight.js'),
			'vue-material-design-icons/Check.vue$': path.join(__dirname, 'src', 'icons', 'Check.js'),
			'vue-material-design-icons/Magnify.vue$': path.join(__dirname, 'src', 'icons', 'Magnify.js'),
			'vue-material-design-icons/HelpCircleOutline.vue$': path.join(__dirname, 'src', 'icons', 'HelpCircleOutline.js'),
			'vue-material-design-icons/CircleOutline.vue$': path.join(__dirname, 'src', 'icons', 'CircleOutline.js'),
			'vue-material-design-icons/AlertCircleOutline.vue$': path.join(__dirname, 'src', 'icons', 'AlertCircleOutline.js'),
		},
	},
	module: {
		rules: [
			{
				test: packageCss,
				use: [{
					loader,
					options: {
						scopeOf: ['CnWizardDialog/CnWizardDialog', 'CnStepper/CnStepper', 'CnDialog/CnDialog'].map((name) =>
							require.resolve('@conduction/nextcloud-vue/dist/esm/components/' + name + '.vue.js')),
						alsoKeep: boardDialogRule,
					},
				}],
			},
			{
				test: /\.css$/,
				exclude: packageCss,
				use: [{ loader }],
			},
		],
	},
	plugins: [
		new webpack.DefinePlugin({
			__VUE_OPTIONS_API__: 'true',
			__VUE_PROD_DEVTOOLS__: 'false',
			__VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'false',
		}),
	],
	performance: {
		hints: false,
	},
}
