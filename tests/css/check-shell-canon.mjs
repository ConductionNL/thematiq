#!/usr/bin/env node
/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Measures thematiq's shell against the canon the OpenProject plugin is built
 * to: `spike/OP-TWIN-canon.md` in ConductionNL/openproject-thematiq, measured
 * from the Nextcloud boards. Both products are held to the same sizes.
 *
 * It opens the Files app of a running Nextcloud at 1440 x 1000 and reads the
 * boxes of the top bar, the navigation, a navigation item, the primary button,
 * the table header row and a table row, and the body text size. Each is
 * compared with the canon value. Where thematiq differs on purpose or has not
 * caught up, the difference is written down in KNOWN with the size thematiq
 * draws today: the check then fails when that size moves, so the gap is
 * either closed (remove the line) or noticed.
 *
 * Usage, against an instance whose active set is the one to measure:
 *   node tests/css/check-shell-canon.mjs --url http://127.0.0.1:8080 --user admin --password secret
 *
 * The Playwright spec tests/e2e/spec-coverage/shell-canon.spec.ts runs measure()
 * in CI on the Zuiddrecht set, the set the boards draw.
 *
 * Exit codes: 0 every part matches the canon or its recorded gap; 1 a part
 * differs; 2 the page could not be opened.
 *
 * @spec openspec/changes/shell-canon-check/specs/shell-canon-check/spec.md#requirement-the-shell-is-measured-against-the-canon
 */

/** The canon, in CSS pixels: `spike/OP-TWIN-canon.md` of the plugin, 14 October 2026. */
export const CANON = {
	topBarHeight: 68,
	navigationWidth: 264,
	navigationItemHeight: 42,
	primaryButtonHeight: 40,
	tableHeaderRowHeight: 44,
	tableRowHeight: 48,
	bodyTextSize: 14,
}

/**
 * Where thematiq draws another size today, measured on Nextcloud 35.0.1 with
 * the Zuiddrecht set on 14 October 2026. Each line is a decision still to make:
 * move thematiq to the canon, or the canon to thematiq.
 */
export const KNOWN = {
	primaryButtonHeight: 48,
	tableRowHeight: 44,
	bodyTextSize: 15,
}

/** The selectors each part is read from, first match wins. */
const PARTS = {
	topBarHeight: [['#header'], 'height'],
	navigationWidth: [['#app-navigation', '.app-navigation'], 'width'],
	navigationItemHeight: [['.app-navigation-entry'], 'height'],
	primaryButtonHeight: [['.files-list__header .button-vue--vue-primary', '.button-vue--vue-primary'], 'height'],
	tableHeaderRowHeight: [['.files-list__thead tr'], 'height'],
	tableRowHeight: [['tr.files-list__row'], 'height'],
}

/**
 * Read the parts on an open, signed-in Files page.
 *
 * @param {import('playwright-core').Page} page The page.
 * @return {Promise<Record<string, number|null>>} Part to size, null when the part is not on the page.
 */
export async function measure(page) {
	await page.waitForSelector('tr.files-list__row', { timeout: 60000 })
	return page.evaluate((parts) => {
		const out = {}
		for (const [part, [selectors, side]] of Object.entries(parts)) {
			out[part] = null
			for (const selector of selectors) {
				const element = document.querySelector(selector)
				if (element !== null) {
					const box = element.getBoundingClientRect()
					out[part] = Math.round((side === 'width' ? box.width : box.height) * 10) / 10
					break
				}
			}
		}
		out.bodyTextSize = parseFloat(getComputedStyle(document.body).fontSize)
		return out
	}, PARTS)
}

/**
 * Compare measured sizes with the canon and the recorded gaps.
 *
 * @param {Record<string, number|null>} measured Part to size.
 * @return {{ matches: string[], gaps: string[], failures: string[] }} The outcome per part.
 */
export function judge(measured) {
	const matches = []
	const gaps = []
	const failures = []
	for (const [part, canon] of Object.entries(CANON)) {
		const size = measured[part]
		if (size === null || size === undefined) {
			failures.push(`${part}: not on the page`)
		} else if (part in KNOWN) {
			if (size === canon) {
				failures.push(`${part}: now ${size}, the canon; remove it from KNOWN`)
			} else if (size === KNOWN[part]) {
				gaps.push(`${part}: ${size} (canon ${canon})`)
			} else {
				failures.push(`${part}: ${size}, recorded ${KNOWN[part]}, canon ${canon}`)
			}
		} else if (size === canon) {
			matches.push(`${part}: ${size}`)
		} else {
			failures.push(`${part}: ${size}, canon ${canon}`)
		}
	}
	return { matches, gaps, failures }
}

/**
 * Run against an instance from the command line.
 *
 * @return {Promise<number>} The exit code.
 */
async function main() {
	const arg = (name) => {
		const at = process.argv.indexOf('--' + name)
		return at === -1 ? undefined : process.argv[at + 1]
	}
	const url = arg('url')
	if (!url) {
		console.error('Usage: node tests/css/check-shell-canon.mjs --url <base> --user <name> --password <password>')
		return 2
	}
	const { chromium } = await import('playwright-core')
	const browser = await chromium.launch()
	try {
		const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } })
		await page.goto(url + '/login')
		await page.fill('#user', arg('user') ?? 'admin')
		await page.fill('#password', arg('password') ?? '')
		await page.click('button[type=submit]')
		await page.waitForURL(/\/apps\//, { timeout: 60000 })
		await page.goto(url + '/apps/files/')
		const result = judge(await measure(page))
		for (const line of result.matches) console.log('canon  ' + line)
		for (const line of result.gaps) console.log('gap    ' + line)
		for (const line of result.failures) console.log('FAIL   ' + line)
		return result.failures.length === 0 ? 0 : 1
	} catch (error) {
		console.error(String(error))
		return 2
	} finally {
		await browser.close()
	}
}

if (import.meta.url === `file://${process.argv[1]}`) {
	process.exitCode = await main()
}
