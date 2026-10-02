/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Nextcloud variable inventory and its guard (`npm run test:inventory`).
 *
 * The committed files must pass every check, and each check must fail on a
 * fixture built to break it, naming the entry, so a green run is a verdict
 * and not a silence.
 *
 * @spec openspec/changes/nc-variable-inventory/specs/nextcloud-variable-inventory/spec.md
 * @spec openspec/changes/nc-variable-inventory/specs/nextcloud-variable-mapping/spec.md
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { describe, it, expect } from 'vitest'
import { classify, scanSource } from '../../scripts/inventory/scan.mjs'
import {
	assignedByThematiq,
	checkBaseline,
	checkDefaultsLeaveSettableUnset,
	checkDigests,
	checkMappedAreAssigned,
	checkSettable,
	checkStale,
	checkStatuses,
	checkUnknownNames,
	coverage,
} from '../../scripts/inventory/guard.mjs'
import { firstDifferentRow, renderMappings, renderSettable, MAPPINGS, SETTABLE } from '../../scripts/inventory/generate-mappings.mjs'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8')
const inventory = JSON.parse(read('scripts/mapping/nextcloud-variables.json'))
const status = JSON.parse(read('scripts/mapping/variable-status.json'))
const digests = read('scripts/mapping/nextcloud-variables.sha')
const assigned = assignedByThematiq(root)

/** A deep copy to break without touching the shared objects. */
const copy = (value) => JSON.parse(JSON.stringify(value))

describe('the committed inventory', () => {
	it('names the Nextcloud and library versions it was read from', () => {
		expect(inventory.nextcloud).toMatch(/^\d+\.\d+\.\d+\.\d+$/)
		expect(inventory.library.name).toBe('@conduction/nextcloud-vue')
		expect(inventory.library.version).toMatch(/^\d+\.\d+\.\d+/)
	})

	it('lists the theming vocabulary, slots, icons, runtime writes and the library', () => {
		expect(inventory.variables['--color-mark'].class).toBe('theme')
		expect(inventory.variables['--plyr-audio-control-background-hover'].class).toBe('slot')
		expect(inventory.variables['--icon-download-dark'].class).toBe('icon')
		expect(inventory.variables['--systemtag-color'].class).toBe('runtime')
		expect(inventory.variables['--cn-kpi-accent'].class).toBe('conduction')
	})

	it('reads 111 theme entries from Nextcloud 34.0.0.12', () => {
		expect(inventory.counts.theme).toBe(111)
	})

	it('records a theme value per built-in theme', () => {
		const stock = inventory.variables['--color-main-text'].stock
		expect(Object.keys(stock)).toEqual(['default', 'dark', 'light-highcontrast', 'dark-highcontrast'])
		expect(stock.default).not.toBe(stock.dark)
	})

	it('records owner and selectors', () => {
		expect(inventory.variables['--dp-hover-color'].owner).toBe('date-picker')
		expect(inventory.variables['--color-mark'].selectors).toContain('[data-theme-dark]')
	})
})

describe('the scanner', () => {
	it('does not count a commented-out mapping', () => {
		const { declared } = scanSource(':root {\n\t/* --color-mark: var(--nldesign-color-mark); */\n\t--color-main-text: red;\n}')
		expect([...declared.keys()]).toEqual(['--color-main-text'])
	})

	it('does not count a self-reference', () => {
		const { declared, read: reads } = scanSource('body { --x: var(--x); --y: 1px }')
		expect([...declared.keys()]).toEqual(['--y'])
		expect([...reads]).toEqual(['--x'])
	})

	it('reads CSS that a JavaScript bundle carries in a string', () => {
		const { declared } = scanSource('o.push([e.id,".a{\\n\\t--dp-hover-color: #f3f3f3;\\n}",""])')
		expect(declared.get('--dp-hover-color').values).toEqual(['#f3f3f3'])
		expect(declared.get('--dp-hover-color').selectors).toEqual(['.a'])
	})

	it('takes a BEM modifier or an HTML comment for what they are', () => {
		const { declared } = scanSource('.list--open:hover{color:red} <!--SPDX-FileCopyrightText: x --> &--not-unlimited :deep(.a){}')
		expect([...declared.keys()]).toEqual([])
	})

	it('classes a JavaScript write as runtime', () => {
		const { runtime } = scanSource('el.style.setProperty("--systemtag-color", color)')
		expect([...runtime]).toEqual(['--systemtag-color'])
		const facts = { name: '--systemtag-color', theme: false, declared: false, read: true, runtime: true, values: [], conduction: false }
		expect(classify(facts)).toBe('runtime')
	})

	it('classes an image as icon, a read-only name as slot, a Vue v-bind as runtime', () => {
		const base = { theme: false, declared: true, read: true, runtime: false, values: [], conduction: false }
		expect(classify({ ...base, name: '--icon-download-dark' })).toBe('icon')
		expect(classify({ ...base, name: '--loading-icon', values: ['url(images/loading.svg)'] })).toBe('icon')
		expect(classify({ ...base, name: '--plyr-x', declared: false })).toBe('slot')
		expect(classify({ ...base, name: '--a475b540' })).toBe('runtime')
		expect(classify({ ...base, name: '--x', read: false })).toBe('unread')
		expect(classify({ ...base, name: '--x' }, { '--x': 'slot' })).toBe('slot')
	})
})

describe('the guard on the committed files', () => {
	it('every entry has a status and every exclusion a reason', () => {
		expect(checkStatuses(inventory, status)).toEqual([])
	})

	it('no status is stale', () => {
		expect(checkStale(inventory, status)).toEqual([])
	})

	it('coverage equals the baseline', () => {
		expect(checkBaseline(inventory, status)).toEqual([])
	})

	it('records the measured starting point: 62 theme and 12 component entries mapped', () => {
		const now = coverage(inventory, status)
		expect(now.theme.mapped).toHaveLength(62)
		expect(now.component.mapped).toHaveLength(12)
	})

	it('every theme entry is mapped or settable (theme-vocabulary-complete)', () => {
		const now = coverage(inventory, status)
		expect(now.theme.mapped.length + now.theme.settable.length).toBe(inventory.counts.theme)
		expect(checkSettable(inventory, status)).toEqual([])
	})

	it('flags exactly the 13 structural variables advanced', () => {
		const advanced = Object.entries(status.variables).filter(([, own]) => own.advanced === true).map(([name]) => name).sort()
		expect(advanced).toEqual([
			'--body-container-margin', '--body-height', '--breakpoint-mobile', '--clickable-area-large',
			'--clickable-area-small', '--default-clickable-area', '--default-grid-baseline', '--filter-background-blur',
			'--header-height', '--header-menu-item-height', '--navigation-width', '--sidebar-max-width', '--sidebar-min-width',
		])
	})

	it('no defaults.css gives a settable token a value', () => {
		expect(checkDefaultsLeaveSettableUnset(root, status)).toEqual([])
	})

	it('thematiq assigns no Nextcloud-looking name the inventory does not know', () => {
		expect(checkUnknownNames(inventory, status, assigned)).toEqual([])
	})

	it('every entry mapped through thematiq is assigned by a thematiq stylesheet', () => {
		expect(checkMappedAreAssigned(inventory, status, assigned)).toEqual([])
	})

	it('the inventory is what the extractor wrote', () => {
		expect(checkDigests(inventory, digests)).toEqual([])
	})

	it('mappings.md is generated from the inventory', () => {
		expect(firstDifferentRow(renderMappings(inventory, status), fs.readFileSync(MAPPINGS, 'utf8'))).toBeNull()
	})

	it('the settable theme variables page is generated from the status file', () => {
		expect(firstDifferentRow(renderSettable(inventory, status), fs.readFileSync(SETTABLE, 'utf8'))).toBeNull()
	})

	it('mappings.md shows class, status, token and owner for --color-primary-element', () => {
		const row = fs.readFileSync(MAPPINGS, 'utf8').split('\n').find((line) => line.startsWith('| `--color-primary-element` |'))
		expect(row).toBe('| `--color-primary-element` | theme | mapped | `--nldesign-color-primary` | theming | Set by thematiq. |')
	})
})

describe('the guard fails, and names the entry', () => {
	it('on an entry without a status', () => {
		const broken = copy(inventory)
		broken.variables['--color-new-in-a-later-release'] = { class: 'theme', owner: 'theming', selectors: [], declared: true, read: true }
		expect(checkStatuses(broken, status)).toEqual(['--color-new-in-a-later-release (theme) has no status in variable-status.json'])
	})

	it('on an exclusion without a reason', () => {
		const broken = copy(status)
		broken.variables['--color-mark'] = { status: 'excluded' }
		expect(checkStatuses(inventory, broken)).toEqual(['--color-mark is excluded without a reason'])
	})

	it('on a status for a variable the release does not have', () => {
		const broken = copy(status)
		broken.variables['--color-gone'] = { status: 'excluded', reason: 'x' }
		expect(checkStale(inventory, broken)).toEqual([`--color-gone has a status but Nextcloud ${inventory.nextcloud} has no such variable (stale)`])
	})

	it('on a removed mapping with no recorded removal', () => {
		const broken = copy(status)
		broken.variables['--color-favorite'] = { status: 'excluded', reason: 'x' }
		const findings = checkBaseline(inventory, broken)
		expect(findings).toHaveLength(1)
		expect(findings[0]).toMatch(/^--color-favorite lost its "mapped" status \(theme: 61 now, baseline 62\)/)
	})

	it('but not when the removal is recorded', () => {
		const broken = copy(status)
		broken.variables['--color-favorite'] = { status: 'excluded', reason: 'x' }
		broken.removals['--color-favorite'] = 'A reason.'
		expect(checkBaseline(inventory, broken)).toEqual([])
	})

	it('on a new mapping the baseline does not list yet', () => {
		const broken = copy(status)
		broken.variables['--color-mark'] = { status: 'settable', token: '--nldesign-color-mark' }
		expect(checkBaseline(inventory, broken)).toEqual(['--color-mark is settable but the theme baseline does not list it: add it to baseline.theme.settable'])
	})

	it('on a mapped name the inventory does not know', () => {
		const broken = new Map(assigned)
		broken.set('--color-primary-element-ligth', ['css/systems/nldesign/overrides.css'])
		expect(checkUnknownNames(inventory, status, broken)).toEqual([
			`--color-primary-element-ligth is assigned in css/systems/nldesign/overrides.css but Nextcloud ${inventory.nextcloud} has no such variable`,
		])
	})

	it('on a mapping whose stylesheet line is gone', () => {
		const broken = new Map(assigned)
		broken.delete('--color-favorite')
		expect(checkMappedAreAssigned(inventory, status, broken)).toEqual(['--color-favorite is "mapped" via thematiq, but no thematiq stylesheet assigns it'])
	})

	it('on a settable token declared in a defaults.css', () => {
		const broken = copy(status)
		broken.variables['--color-primary'] = { status: 'settable', token: '--nldesign-color-primary', tab: 'login', type: 'color', label: 'x' }
		expect(checkDefaultsLeaveSettableUnset(root, broken)).toContain('css/systems/nldesign/defaults.css declares --nldesign-color-primary, a settable token that must stay without a default')
	})

	it('on a settable icon', () => {
		const broken = copy(status)
		broken.variables['--icon-download-dark'] = { status: 'settable', token: '--nldesign-icon-download-dark', tab: 'content', type: 'text', label: 'x' }
		expect(checkSettable(inventory, broken)).toEqual(['--icon-download-dark is settable but its class is icon'])
	})

	it('on a hand edit of the inventory', () => {
		const broken = copy(inventory)
		broken.variables['--color-mark'].stock.dark = '#000000'
		expect(checkDigests(broken, digests)).toEqual(['--color-mark differs from what the extractor wrote'])
	})

	it('on a hand edit of mappings.md, at the first differing row', () => {
		const text = renderMappings(inventory, status)
		const edited = text.replace('| `--color-mark` | theme | excluded |', '| `--color-mark` | theme | mapped |')
		const line = text.split('\n').findIndex((row) => row.startsWith('| `--color-mark` |')) + 1
		expect(firstDifferentRow(text, edited)).toMatch(new RegExp(`^line ${line}: expected "\\| \`--color-mark\` \\| theme \\| excluded`))
	})
})
