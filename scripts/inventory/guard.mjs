/**
 * The checks behind `npm run test:inventory`, as pure functions.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Every function returns a list of findings, each naming the entry it is
 * about, so a failing test says which variable to look at. An empty list is
 * a pass. The vitest guard runs them on the committed files and on fixtures
 * that must fail.
 *
 * @spec openspec/changes/nc-variable-inventory/specs/nextcloud-variable-inventory/spec.md
 */

import fs from 'fs'
import path from 'path'
import { createHash } from 'crypto'
import { scanSource } from './scan.mjs'

/** Classes whose status the class rule itself decides; every other class needs its own status line. */
export const DEFAULTABLE = ['icon', 'runtime', 'unread']

/** The statuses that count towards coverage. */
export const COVERED = ['mapped', 'settable']

/**
 * The status of one inventory entry: its own line, else its class default.
 *
 * @param {string} name   Custom property name.
 * @param {object} entry  Inventory entry.
 * @param {object} status The parsed variable-status.json.
 * @return {object|null} `{status, reason?, token?, via?, note?}` or null.
 */
export function statusOf(name, entry, status) {
	if (status.variables[name] !== undefined) {
		return status.variables[name]
	}
	if (DEFAULTABLE.includes(entry.class) === true) {
		return status.classDefaults?.[entry.class] ?? null
	}
	return null
}

/**
 * Every entry has a status, and every exclusion a reason.
 *
 * @param {object} inventory The parsed nextcloud-variables.json.
 * @param {object} status    The parsed variable-status.json.
 * @return {string[]} Findings.
 */
export function checkStatuses(inventory, status) {
	const findings = []
	for (const [name, entry] of Object.entries(inventory.variables)) {
		const own = statusOf(name, entry, status)
		if (own === null) {
			findings.push(`${name} (${entry.class}) has no status in variable-status.json`)
			continue
		}
		if (['mapped', 'settable', 'excluded'].includes(own.status) === false) {
			findings.push(`${name} has an unknown status "${own.status}"`)
		}
		if (own.status === 'excluded' && (typeof own.reason !== 'string' || own.reason.trim() === '')) {
			findings.push(`${name} is excluded without a reason`)
		}
	}
	return findings
}

/**
 * Every status line is about a name the inventory has.
 *
 * @param {object} inventory The parsed nextcloud-variables.json.
 * @param {object} status    The parsed variable-status.json.
 * @return {string[]} Findings.
 */
export function checkStale(inventory, status) {
	return Object.keys(status.variables)
		.filter((name) => inventory.variables[name] === undefined)
		.map((name) => `${name} has a status but Nextcloud ${inventory.nextcloud} has no such variable (stale)`)
}

/**
 * The names per class that are mapped or settable now.
 *
 * @param {object} inventory The parsed nextcloud-variables.json.
 * @param {object} status    The parsed variable-status.json.
 * @return {Object<string, {mapped: string[], settable: string[]}>} Sorted names per class and status.
 */
export function coverage(inventory, status) {
	const out = {}
	for (const [name, entry] of Object.entries(inventory.variables)) {
		const own = statusOf(name, entry, status)
		if (own === null || COVERED.includes(own.status) === false) {
			continue
		}
		out[entry.class] = out[entry.class] ?? { mapped: [], settable: [] }
		out[entry.class][own.status].push(name)
	}
	for (const lists of Object.values(out)) {
		lists.mapped.sort()
		lists.settable.sort()
	}
	return out
}

/**
 * Coverage equals the recorded baseline, in both directions.
 *
 * A name that lost its status fails unless `removals` records why. A name
 * that gained one fails until the baseline lists it, so the higher number is
 * written down in the same change and can never quietly drop again.
 *
 * @param {object} inventory The parsed nextcloud-variables.json.
 * @param {object} status    The parsed variable-status.json.
 * @return {string[]} Findings.
 */
export function checkBaseline(inventory, status) {
	const findings = []
	const now = coverage(inventory, status)
	const classes = new Set([...Object.keys(now), ...Object.keys(status.baseline ?? {})])
	for (const klass of [...classes].sort()) {
		for (const kind of COVERED) {
			const before = new Set(status.baseline?.[klass]?.[kind] ?? [])
			const after = new Set(now[klass]?.[kind] ?? [])
			for (const name of before) {
				if (after.has(name) === false && status.removals?.[name] === undefined) {
					findings.push(`${name} lost its "${kind}" status (${klass}: ${after.size} now, baseline ${before.size}) and removals records no reason`)
				}
			}
			for (const name of after) {
				if (before.has(name) === false) {
					findings.push(`${name} is ${kind} but the ${klass} baseline does not list it: add it to baseline.${klass}.${kind}`)
				}
			}
		}
	}
	return findings
}

/**
 * The custom properties thematiq's stylesheets assign, with the files that assign them.
 *
 * Token set files (css/tokens/) are left out: they define a set's own
 * vocabulary, not a mapping onto Nextcloud.
 *
 * @param {string} root App root.
 * @return {Map<string, string[]>} Name → relative file paths.
 */
export function assignedByThematiq(root) {
	const files = []
	const walk = (rel) => {
		for (const entry of fs.readdirSync(path.join(root, rel), { withFileTypes: true })) {
			const child = `${rel}/${entry.name}`
			if (entry.isDirectory() === true) {
				if (child !== 'css/tokens') {
					walk(child)
				}
			} else if (entry.name.endsWith('.css') === true) {
				files.push(child)
			}
		}
	}
	walk('css')
	const assigned = new Map()
	for (const file of files.sort()) {
		for (const name of scanSource(fs.readFileSync(path.join(root, file), 'utf8')).declared.keys()) {
			assigned.set(name, [...(assigned.get(name) ?? []), file])
		}
	}
	return assigned
}

/**
 * thematiq assigns no Nextcloud-looking name the inventory does not know.
 *
 * "Nextcloud-looking" means the first word of the name is the first word of
 * an inventory variable (`--color-…`, `--border-…`, `--dp-…`), which is how a
 * typo such as `--color-primary-element-ligth` is told apart from a set's own
 * vocabulary (`--utrecht-…`, `--lasuite-…`). A name listed in `foreign` is
 * set on purpose and carries its reason there.
 *
 * @param {object} inventory The parsed nextcloud-variables.json.
 * @param {object} status    The parsed variable-status.json.
 * @param {Map<string, string[]>} assigned Output of assignedByThematiq().
 * @return {string[]} Findings.
 */
export function checkUnknownNames(inventory, status, assigned) {
	const families = new Set(
		Object.entries(inventory.variables)
			.filter(([, entry]) => entry.class !== 'icon' && entry.class !== 'conduction')
			.map(([name]) => name.split('-')[2]),
	)
	const findings = []
	for (const [name, files] of assigned) {
		if (/^--(nldesign|thematiq)-/.test(name) === true || inventory.variables[name] !== undefined) {
			continue
		}
		if (families.has(name.split('-')[2]) === true && status.foreign?.[name] === undefined) {
			findings.push(`${name} is assigned in ${files.join(', ')} but Nextcloud ${inventory.nextcloud} has no such variable`)
		}
	}
	return findings
}

/**
 * Every entry thematiq claims to map through its own stylesheets is assigned in one.
 *
 * @param {object} inventory The parsed nextcloud-variables.json.
 * @param {object} status    The parsed variable-status.json.
 * @param {Map<string, string[]>} assigned Output of assignedByThematiq().
 * @return {string[]} Findings.
 */
export function checkMappedAreAssigned(inventory, status, assigned) {
	return Object.entries(status.variables)
		.filter(([name, own]) => own.status === 'mapped' && own.via === 'thematiq' && assigned.has(name) === false)
		.map(([name]) => `${name} is "mapped" via thematiq, but no thematiq stylesheet assigns it`)
}

/**
 * Short, stable hash of a JSON value; the extractor writes the same.
 *
 * @param {unknown} value Any JSON value.
 * @return {string} 16 hex characters.
 */
export function hash(value) {
	return createHash('sha256').update(JSON.stringify(value)).digest('hex').slice(0, 16)
}

/**
 * Each entry still hashes to what the extractor wrote, so a hand edit is
 * caught without a Nextcloud release at hand.
 *
 * @param {object} inventory The parsed nextcloud-variables.json.
 * @param {string} digests   The text of nextcloud-variables.sha.
 * @return {string[]} At most one finding: the first differing entry.
 */
export function checkDigests(inventory, digests) {
	const expected = new Map(
		digests
			.trim()
			.split('\n')
			.map((line) => line.split('\t')),
	)
	const source = hash({ nextcloud: inventory.nextcloud, library: inventory.library, counts: inventory.counts })
	if (expected.get('@source') !== source) {
		return ['the version stamp or the per-class counts were edited by hand']
	}
	const names = [...new Set([...Object.keys(inventory.variables), ...[...expected.keys()].filter((key) => key !== '@source')])].sort()
	for (const name of names) {
		const entry = inventory.variables[name]
		if (entry === undefined) {
			return [`${name} was removed from nextcloud-variables.json by hand`]
		}
		if (expected.has(name) === false) {
			return [`${name} was added to nextcloud-variables.json by hand`]
		}
		if (expected.get(name) !== hash(entry)) {
			return [`${name} differs from what the extractor wrote`]
		}
	}
	return []
}
