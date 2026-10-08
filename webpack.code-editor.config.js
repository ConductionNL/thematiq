/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Bundles src/codeEditor.js — the playground's code editor, CnJsonViewer from
 * @conduction/nextcloud-vue — into one script, js/vendor/codeEditor.js, which the
 * admin template loads when it is there. The rest of js/ is hand-written source
 * served as it is; this is the only bundled file, and it is gitignored.
 *
 * Webpack, as the other Conduction apps build with: it is plain JavaScript, so
 * `npm run build` works on every Node an `npm ci` here installs on.
 *
 * Run with `npm run build:code-editor`; `npm run build` runs it too.
 */

const path = require('path')
const webpack = require('webpack')

module.exports = {
	mode: 'production',
	entry: path.join(__dirname, 'src', 'codeEditor.js'),
	output: {
		path: path.join(__dirname, 'js', 'vendor'),
		filename: 'codeEditor.js',
	},
	resolve: {
		alias: {
			// CnJsonViewer needs NcButton only; the package index brings all of
			// @nextcloud/vue. See src/nextcloudVue.js.
			'@nextcloud/vue$': path.join(__dirname, 'src', 'nextcloudVue.js'),
		},
	},
	module: {
		rules: [
			{
				// NcButton imports its stylesheet. CnJsonViewer draws NcButton
				// only for JSON, which the playground never edits, so the
				// stylesheet is carried as text and never put on the page.
				test: /\.css$/,
				type: 'asset/source',
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
