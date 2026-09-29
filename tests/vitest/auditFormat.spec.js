/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The audit log's value formatting.
 *
 * These four were written inside a closure in admin.js with no export, so the
 * only way to exercise them was to open the settings page and read it — which
 * is how "1 entries" reached review. They live in js/lib/auditFormat.js now,
 * and every shape ThemingAuditService can store has a case here.
 *
 * @spec openspec/specs/theming-audit/spec.md
 */

import { describe, it, expect } from 'vitest'
import format from '../../js/lib/auditFormat.js'

describe('a stored value, as the panel renders it', () => {
	it('says so when there is no before-side', () => {
		// A first write or an upload has no `old`. An empty cell reads as a
		// rendering failure; the dash is a statement.
		expect(format.formatAuditValue(null)).toBe('—')
		expect(format.formatAuditValue(undefined)).toBe('—')
	})

	it('renders a toggle as a state, not as a literal', () => {
		expect(format.formatAuditValue(true)).toBe('On')
		expect(format.formatAuditValue(false)).toBe('Off')
	})

	it('counts one entry in the singular', () => {
		// summarizeValue() reduces ANY array to {count}, so a one-key override
		// map is routine — and read "1 entries" until this.
		expect(format.formatAuditValue({ count: 1 })).toBe('1 entry')
		expect(format.formatAuditValue({ count: 0 })).toBe('0 entries')
		expect(format.formatAuditValue({ count: 12 })).toBe('12 entries')
	})

	it('renders a CSS payload as a size and the digest identifying it', () => {
		expect(format.formatAuditValue({ hash: 'sha256:a1b2c3', bytes: 1536 })).toBe(
			'1.5 kB · sha256:a1b2c3',
		)
		// The digest is the evidence half; a payload without one still sizes.
		expect(format.formatAuditValue({ bytes: 412 })).toBe('412 B')
	})

	it('scales bytes to something a reader can compare', () => {
		expect(format.formatAuditBytes(0)).toBe('0 B')
		expect(format.formatAuditBytes(1023)).toBe('1023 B')
		expect(format.formatAuditBytes(1024)).toBe('1.0 kB')
		expect(format.formatAuditBytes(2359296)).toBe('2.3 MB')
	})

	it('passes a scalar through', () => {
		expect(format.formatAuditValue('rotterdam')).toBe('rotterdam')
		expect(format.formatAuditValue(42)).toBe('42')
	})

	it('falls back to JSON for a shape it does not know', () => {
		expect(format.formatAuditValue({ weird: 1 })).toBe('{"weird":1}')
	})
})

describe('a stored timestamp, as the panel renders it', () => {
	it('drops the machine punctuation and keeps UTC', () => {
		// Kept in UTC deliberately: the exported log is the artefact that gets
		// filed, and a panel showing a different hour than the export cannot be
		// lined up against it.
		expect(format.formatAuditTimestamp('2026-09-17T09:14:02Z')).toBe(
			'2026-09-17 09:14:02 UTC',
		)
	})

	it('passes anything not in that exact shape through untouched', () => {
		expect(format.formatAuditTimestamp('not-a-date')).toBe('not-a-date')
		expect(format.formatAuditTimestamp('2026-09-17 09:14:02')).toBe(
			'2026-09-17 09:14:02',
		)
		expect(format.formatAuditTimestamp('')).toBe('')
		expect(format.formatAuditTimestamp(null)).toBe('')
	})
})

describe('which identities changed', () => {
	it('names them, because two counts do not answer the question', () => {
		// Both sides of a sync entry are snapshots reduced to a count, so From
		// and To both read "10 entries" — this column is the only one that says
		// what actually moved.
		expect(
			format.formatAuditChanged({ changed: ['primary_color', 'logo'] }),
		).toBe('primary_color, logo')
	})

	it('caps a long list, because one write can name forty tokens', () => {
		// Printed in full this cell was taller than the rest of the row and,
		// before the table layout was fixed, wider than the page. The count is
		// what a reader acts on; the names are in the exported log.
		const many = Array.from({ length: 40 }, (unused, i) => '--token-' + i)
		const out = format.formatAuditChanged({ changed: many })

		expect(out).toContain('--token-0')
		expect(out).toContain('--token-5')
		expect(out).not.toContain('--token-6')
		expect(out).toContain('+34 more')
	})

	it('does not cap a list that already fits', () => {
		const six = Array.from({ length: 6 }, (unused, i) => '--token-' + i)

		expect(format.formatAuditChanged({ changed: six })).toBe(six.join(', '))
		expect(format.formatAuditChanged({ changed: six })).not.toContain('more')
	})

	it('counts the remainder, not the whole list', () => {
		const seven = Array.from({ length: 7 }, (unused, i) => '--token-' + i)

		expect(format.formatAuditChanged({ changed: seven })).toContain('+1 more')
	})

	it('distinguishes "nothing changed" from "not applicable"', () => {
		expect(format.formatAuditChanged({ changed: [] })).toBe('Nothing')
		expect(format.formatAuditChanged({})).toBe('—')
		expect(format.formatAuditChanged({ changed: 'not-an-array' })).toBe('—')
	})
})
