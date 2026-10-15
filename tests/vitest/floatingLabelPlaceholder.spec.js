/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A theme that makes every placeholder opaque must still let Nextcloud hide
 * the placeholder under a resting floating label. Without that, the label and
 * the placeholder of every labelled NcSelect and NcTextField draw on top of
 * each other (Zuiddrecht live round 3, dossiq's timeline filters). Reads the
 * shipped CSS: the defect was a rule order, so the order is what is checked.
 *
 * @spec openspec/changes/floating-label-hides-placeholder/specs/floating-label-hides-placeholder/spec.md#requirement-a-resting-floating-label-hides-the-placeholder
 */

import { describe, it, expect } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'

const ROOT = path.resolve(__dirname, '..', '..')
const RESTING =
	'.input-field:not(.input-field--label-outside) .input-field__input:not(:focus)::placeholder'

/**
 * Every top-level `selector { body }` pair of a stylesheet, in source order.
 *
 * @param {string} css The stylesheet.
 * @return {Array<{selector: string, body: string}>} The rules.
 */
function rulesOf(css) {
	const plain = css.replace(/\/\*[\s\S]*?\*\//g, '')
	return [...plain.matchAll(/([^{}]+)\{([^{}]*)\}/g)].map((m) => ({
		// Prettier wraps a long selector over two lines; compare it as one.
		selector: m[1].trim().replace(/\s+/g, ' '),
		body: m[2],
	}))
}

describe.each([
	'css/systems/nldesign/theme.css',
	'css/systems/high-contrast/element-overrides.css',
])('%s', (file) => {
	const rules = rulesOf(fs.readFileSync(path.join(ROOT, file), 'utf8'))

	it('hides the placeholder under a resting floating label after making placeholders opaque', () => {
		const opaque = rules.findIndex(
			(r) =>
				r.selector === '::placeholder'
				&& /opacity:\s*1\s*!important/.test(r.body),
		)
		const resting = rules.findIndex(
			(r) =>
				r.selector === RESTING && /opacity:\s*0\s*!important/.test(r.body),
		)
		expect(opaque).toBeGreaterThanOrEqual(0)
		expect(resting).toBeGreaterThan(opaque)
	})
})
