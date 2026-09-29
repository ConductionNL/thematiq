/**
 * Audit-log value formatting — the four pure functions the panel renders with.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * Lifted out of admin.js so they can be unit-tested. They were written inside
 * a closure with no export, which is why three of them shipped with no test at
 * all and "1 entries" reached review: the only way to exercise them was to
 * open the settings page and read it.
 *
 * ThemingAuditService never stores a whole value — an array is reduced to its
 * count and a CSS payload to a length plus a digest — so everything here is
 * turning an ALREADY-SUMMARISED value into a sentence, not summarising.
 *
 * Dual-mode like js/lib/layerSwap.js: module.exports under Node, a global in
 * the browser. No import/export, because this app has no bundler.
 *
 * @spec openspec/specs/theming-audit/spec.md
 */
;(function (root, factory) {
	var api = factory()
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api
	} else {
		root.ThematiqAuditFormat = api
	}
})(typeof window !== 'undefined' ? window : this, function () {
	'use strict'

	/**
	 * How many changed identities a cell prints before summarising the rest.
	 *
	 * Six fits one line at the column's width without the row growing taller
	 * than its neighbours, which is the whole reason this cap exists.
	 */
	var CHANGED_SHOWN = 6

	/**
	 * Nextcloud's own translators, shadowed locally so this module runs under
	 * Node with no globals.
	 *
	 * They keep the names t and n on purpose. tests/l10n/check-l10n.js extracts
	 * by matching the literal CALL SHAPE — the helper's name, the app id and
	 * then quoted source strings — so a differently-named wrapper makes every
	 * string in this file invisible to it. That is not hypothetical: with the
	 * wrappers named tr and plural, the +%n more key reached review absent from
	 * en.json while the check still reported OK.
	 *
	 * For the same reason this comment spells no example arguments: the scan
	 * reads comments as well as code, and a quoted sample invents a key.
	 */
	var t = function (app, key) {
		if (typeof globalThis.t === 'function') {
			return globalThis.t(app, key)
		}

		return key
	}

	var n = function (app, one, many, count) {
		if (typeof globalThis.n === 'function') {
			return globalThis.n(app, one, many, count)
		}

		return (count === 1 ? one : many).replace('%n', String(count))
	}
	/**
	 * WHICH identities changed, which is the only cell that answers the
	 * question an audit is read for.
	 *
	 * Both sides of a sync entry are keyed snapshots, and summarizeValue()
	 * reduces any array to a count — so From and To both read "10 entries"
	 * on every sync, which is true and says nothing. The service computes
	 * the changed list precisely so a reader does not have to diff two
	 * counts in their head; without this column it reached only the
	 * exported JSONL.
	 *
	 * Absent on an entry whose sides are not both plain arrays, which is
	 * most of them, and an em dash says so rather than an empty cell.
	 */
	function formatAuditChanged(entry) {
		if (Array.isArray(entry.changed) === false) {
			return '—'
		}
		if (entry.changed.length === 0) {
			return t('thematiq', 'Nothing')
		}
		if (entry.changed.length <= CHANGED_SHOWN) {
			return entry.changed.join(', ')
		}

		// One overrides_written entry names forty tokens. Printed in full it
		// made this cell taller than the rest of the row put together and,
		// before the table layout was fixed, wider than the page. The count is
		// the part a reader acts on — "did this write touch more than I
		// expected" — and the names themselves are in the exported log, which
		// is the artefact that gets filed.
		return (
			entry.changed.slice(0, CHANGED_SHOWN).join(', ')
			+ ' '
			+ n(
				'thematiq',
				'+%n more',
				'+%n more',
				entry.changed.length - CHANGED_SHOWN,
			)
		)
	}

	/**
	 * The stored timestamp, with the machine punctuation taken out.
	 *
	 * Entries are written by `gmdate('Y-m-d\TH:i:s\Z')`, so
	 * `2026-09-17T09:14:02Z` — exact, and hard to scan down a column
	 * because of the T and the Z. The T becomes a space and the Z becomes
	 * the word, and that is the whole change.
	 *
	 * It stays in UTC on purpose. This panel is evidence an accessibility
	 * audit is shown, and a local time rendered without naming its zone
	 * cannot be lined up against anything — a reader in another zone would
	 * read a different hour off the same record, with nothing on screen
	 * saying so. Converting AND naming the zone would be honest, but then
	 * the exported log and the panel would disagree about when something
	 * happened, and the export is the artefact that gets filed.
	 *
	 * Anything not in that exact shape is passed through untouched rather
	 * than guessed at.
	 */
	function formatAuditTimestamp(ts) {
		if (!ts) {
			return ''
		}

		var iso = String(ts)
		var match = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})Z$/.exec(iso)
		if (match === null) {
			return iso
		}

		return match[1] + ' ' + match[2] + ' UTC'
	}

	/**
	 * One side of a change, in words rather than in JSON.
	 *
	 * The service never stores a whole value: an array is reduced to its
	 * count and a CSS payload to a length plus a digest, so what arrives
	 * here is already a summary. It was being printed as the literal
	 * `{"count":3}`, which is the summary spelled as its own data
	 * structure — true, and unreadable in a column.
	 */
	function formatAuditValue(value) {
		// An entry that has no before-side (a first write, an upload) is a
		// real fact about the change, so the cell says so rather than
		// sitting empty and reading as a rendering failure.
		if (value === null || value === undefined) {
			return '—'
		}
		if (typeof value === 'boolean') {
			return value === true ? t('thematiq', 'On') : t('thematiq', 'Off')
		}
		if (typeof value === 'object') {
			// An array the service counted. Deliberately "entries" and not
			// "tokens": the same shape carries override maps, per-app
			// exclusion lists and group→set mappings.
			if (typeof value.count === 'number') {
				return n('thematiq', '%n entry', '%n entries', value.count)
			}
			// A CSS payload: its length, and the digest that identifies
			// which payload it was — the evidence half of the record.
			if (typeof value.bytes === 'number') {
				return (
					formatAuditBytes(value.bytes)
					+ (value.hash ? ' · ' + value.hash : '')
				)
			}
			try {
				return JSON.stringify(value)
			} catch (e) {
				return String(value)
			}
		}
		return String(value)
	}

	/**
	 * A byte count at a readable scale. Not translated: the output is a
	 * number and an SI unit, which do not change between locales here.
	 */
	function formatAuditBytes(bytes) {
		if (bytes < 1024) {
			return bytes + ' B'
		}
		if (bytes < 1024 * 1024) {
			return (bytes / 1024).toFixed(1) + ' kB'
		}
		return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
	}

	return {
		formatAuditChanged: formatAuditChanged,
		formatAuditTimestamp: formatAuditTimestamp,
		formatAuditValue: formatAuditValue,
		formatAuditBytes: formatAuditBytes,
	}
})
