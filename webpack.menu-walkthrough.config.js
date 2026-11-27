/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Bundles src/menuWalkthrough.js, the apps menu walkthrough (CnWalkthrough and
 * useWalkthrough from @conduction/nextcloud-vue), into js/vendor/menuWalkthrough.js.
 * js/app-menu.js adds that script to a page only when a tour is due or asked
 * for, so a page without a tour loads none of it. Gitignored, like the code
 * editor's bundle.
 *
 * Styles travel in the script (scripts/webpack/style-inject-loader.js): of the
 * package's one big stylesheet only CnWalkthrough's rules are kept.
 *
 * Run with `npm run build:menu-walkthrough`; `npm run build` runs it too.
 *
 * @spec openspec/specs/apps-menu-walkthrough/spec.md#requirement-the-tour-starts-on-the-first-open-of-the-apps-menu-req-tqwt-002
 */

const path = require('path')
const webpack = require('webpack')

const loader = path.join(__dirname, 'scripts', 'webpack', 'style-inject-loader.js')
const packageCss = /@conduction[\\/]nextcloud-vue[\\/]dist[\\/]nextcloud-vue\.css$/

module.exports = {
	mode: 'production',
	entry: path.join(__dirname, 'src', 'menuWalkthrough.js'),
	output: {
		path: path.join(__dirname, 'js', 'vendor'),
		filename: 'menuWalkthrough.js',
	},
	resolve: {
		alias: {
			// Every component's board-look rules; the walkthrough needs none (src/walkthroughBoardLook.js).
			[require.resolve('@conduction/nextcloud-vue/dist/esm/utils/boardLookSheet.js')]: path.join(__dirname, 'src', 'walkthroughBoardLook.js'),
			// Single-file components this app has no loader for (src/walkthroughIcons.js).
			'vue-material-design-icons/Close.vue$': path.join(__dirname, 'src', 'icons', 'Close.js'),
			'vue-material-design-icons/ChevronLeft.vue$': path.join(__dirname, 'src', 'icons', 'ChevronLeft.js'),
			'vue-material-design-icons/ChevronRight.vue$': path.join(__dirname, 'src', 'icons', 'ChevronRight.js'),
		},
	},
	module: {
		rules: [
			{
				test: packageCss,
				use: [{
					loader,
					options: {
						scopeOf: require.resolve('@conduction/nextcloud-vue/dist/esm/components/CnWalkthrough/CnWalkthrough.vue.js'),
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
