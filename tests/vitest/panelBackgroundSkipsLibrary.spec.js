/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The solid-ground rule's `[class*='panel']` catch-all skips nextcloud-vue's
 * elements, which draw their own ground: it caught the index page with a side
 * panel and painted the page the card colour (dossiq's queue).
 *
 * @spec openspec/changes/panel-background-narrow/specs/panel-background-narrow/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const ROOT = path.resolve(__dirname, '..', '..')
const css = fs.readFileSync(path.join(ROOT, 'css/systems/nldesign/element-overrides.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '')

describe('the solid-ground panel catch-all', () => {
	it('skips nextcloud-vue elements', () => {
		expect(css).toContain("[class*='panel']:not(#header *):not([class*='cn-']),")
		expect(css).not.toContain("[class*='panel']:not(#header *),")
	})
})
