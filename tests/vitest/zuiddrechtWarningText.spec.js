/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Zuiddrecht's warning text is its own warning ink #7A4F00, the colour the
 * boards draw on a warning note ("Advies gevraagd", DqZaaktypeOverzicht).
 * Left unset, Nextcloud derives #664700 from the warning colour.
 *
 * @spec openspec/changes/zuiddrecht-warning-text/specs/zuiddrecht-warning-text/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const ROOT = path.resolve(__dirname, '..', '..')
const light = fs.readFileSync(path.join(ROOT, 'css/tokens/zuiddrecht.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '')
const dark = fs.readFileSync(path.join(ROOT, 'css/tokens/dark/zuiddrecht.css'), 'utf8')

describe('the Zuiddrecht warning text', () => {
	it('is the set\'s warning ink in the light scheme', () => {
		expect(light).toMatch(/--nldesign-nc-color-warning-text:\s*#7A4F00;/i)
		expect(light).toMatch(/--nldesign-color-warning:\s*#7A4F00;/i)
	})

	it('has a generated dark value, so the light ink stays out of dark mode', () => {
		expect(dark).toMatch(/--nldesign-nc-color-warning-text:\s*#[0-9a-f]{6};/i)
		expect(dark).not.toMatch(/--nldesign-nc-color-warning-text:\s*#7A4F00;/i)
	})
})
