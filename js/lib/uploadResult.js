/**
 * The result of a token set upload as the TqSetUploaden board draws it: what was saved, one row
 * per token thematiq reports on, the warnings, the deprecations the file names, or the refusal.
 * js/admin.js draws the model this returns in the upload result region of the own sets panel.
 *
 * Dual-mode like `multiBrandSource.js`: `module.exports` under Node, `window.ThematiqUploadResult`
 * in the browser.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function (root, factory) {
	var api = factory()
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api
	} else {
		root.ThematiqUploadResult = api
	}
})(typeof window !== 'undefined' ? window : this, function () {
	'use strict'

	/**
	 * One row per token the upload reports on: skipped, refused per token, moved into sRGB, or
	 * changed by the converter. A token the response lists twice for one reason is one row.
	 *
	 * @param {object} data The upload response.
	 * @param {function(string, object): string} label The text for a reason code.
	 * @return {Array<{token: string, reason: string}>} The rows.
	 */
	function notices(data, label) {
		var reasons = (data && data.reasons) || {}
		var rows = []
		var seen = {}

		/**
		 * Add a row once.
		 *
		 * @param {string} token The token as the file names it.
		 * @param {string} code The reason code.
		 * @return {void}
		 */
		function add(token, code) {
			var key = token + '\u0000' + code
			if (token === '' || seen[key] === true) {
				return
			}
			seen[key] = true
			rows.push({ token: token, reason: label(code, reasons) })
		}

		;[]
			.concat((data && data.skipped) || [], (data && data.errors) || [])
			.forEach(function (entry) {
				if (typeof entry === 'string') {
					add(entry, 'unmapped-path')
					return
				}
				add(
					String((entry && entry.path) || ''),
					String((entry && entry.reason) || 'unknown'),
				)
			})
		;((data && data.report) || []).forEach(function (entry) {
			if (!entry || entry.action === 'applied' || !entry.reason) {
				return
			}
			add(String(entry.source || ''), String(entry.reason))
		})

		return rows
	}

	/**
	 * The model of a stored upload.
	 *
	 * @param {object} data The upload response.
	 * @param {object} context `{name, fileName, label}`: the set's name, the file's name (empty
	 *                         for a paste) and the text for a reason code.
	 * @return {object} `{state, name, fileName, version, imported, skipped, darkValues, notices,
	 *                  contrast, deprecations, adoptable}`.
	 */
	function stored(data, context) {
		var rows = notices(data, context.label)
		var deprecations = (data && data.importWarnings) || []
		return {
			state: 'stored',
			name: context.name,
			fileName: context.fileName || '',
			version: (data && data.version) || '',
			imported: Number((data && data.imported) || 0),
			skipped: ((data && data.skipped) || []).length,
			darkValues: Number((data && data.darkValues) || 0),
			notices: rows,
			contrast: ((data && data.warnings) || []).filter(function (warning) {
				return warning && warning.kind !== 'incomplete'
			}).length,
			deprecations: deprecations,
			adoptable: deprecations.filter(function (warning) {
				return Boolean(warning && warning.token)
			}),
		}
	}

	/**
	 * The model of a refused upload.
	 *
	 * @param {object} data The error response.
	 * @param {object} context `{status, fileName, label}`.
	 * @return {object} `{state, status, fileName, error, notices}`.
	 */
	function refused(data, context) {
		return {
			state: 'refused',
			status: context.status,
			fileName: context.fileName || '',
			error: (data && data.error) || '',
			notices: notices(data, context.label),
		}
	}

	/**
	 * How many tokens of a listed set fall back to the design system, from its incomplete warning.
	 *
	 * @param {object|undefined} set The set as the list of own sets carries it.
	 * @return {number} The count, 0 for a complete set.
	 */
	function missing(set) {
		var warning = ((set && set.warnings) || []).find(function (entry) {
			return entry && entry.kind === 'incomplete'
		})
		return warning !== undefined && Array.isArray(warning.missing) === true
			? warning.missing.length
			: 0
	}

	return { notices: notices, stored: stored, refused: refused, missing: missing }
})
