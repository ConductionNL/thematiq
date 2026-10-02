#!/usr/bin/env node

/**
 * Prove the internal scopes in a real browser: every selector they carry is one
 * the browser accepts, a set token reaches its component even when the
 * component's own declaration arrives later, and nothing set means nothing written.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * The rules come from the server itself (tests/css/internal-scopes.php calls
 * InternalScopesService::build()). Each component's own declaration is written
 * from the inventory's recorded selector and stock value, and injected AFTER
 * thematiq's rules, the way Vue injects component styles at runtime.
 *
 * Run: npm run test:internal-scopes   (needs php and a Chromium; set CHROME_PATH to use a system Chrome)
 *
 * @spec openspec/changes/internal-variable-tokens/specs/component-tokens/spec.md
 */

import { chromium } from 'playwright'
import { readFileSync } from 'node:fs'
import { execFileSync } from 'node:child_process'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const map = JSON.parse(readFileSync(join(ROOT, 'scripts/mapping/internal-tokens.json'), 'utf8')).tokens
const scopes = (light, any = []) =>
	execFileSync('php', [join(ROOT, 'tests/css/internal-scopes.php'), JSON.stringify({ light, any })], { encoding: 'utf8' })

const browser = await chromium.launch({ headless: true, ...(process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}) })
const tab = await browser.newPage()
const failures = []
const check = (label, got, want) => {
	if (JSON.stringify(got) !== JSON.stringify(want)) failures.push(label + ': got ' + JSON.stringify(got) + ', wanted ' + JSON.stringify(want))
}

// 1. Every selector the scopes carry is valid. `:is()` drops an invalid one in
// silence, so an invalid selector would be a token that never reaches anything.
const selectors = [...new Set(Object.values(map).flatMap((t) => t.selectors ?? []))]
const invalid = await tab.evaluate((list) => list.filter((s) => {
	try {
		document.querySelectorAll(s)
		return false
	} catch {
		return true
	}
}), selectors)
check('invalid selectors', invalid, [])

// 2. Nothing set means nothing written.
check('nothing set', scopes([]), '')

// 3. Reach, with the component's own declaration after thematiq's rules.
async function measure({ set, scopesCss, component, dom, probe, variable, dark = false }) {
	await tab.setContent('<!doctype html><html><head>'
		+ '<style>' + set + '</style><style>' + scopesCss + '</style><style>' + component + '</style>'
		+ '</head><body data-theme-default data-themes="default">' + dom + '</body></html>')
	if (dark) {
		await tab.evaluate(() => {
			document.body.removeAttribute('data-theme-default')
			document.body.setAttribute('data-theme-dark', '')
			document.body.setAttribute('data-themes', 'dark')
		})
	}
	return tab.evaluate(([id, name]) => getComputedStyle(document.getElementById(id)).getPropertyValue(name).trim(), [probe, variable])
}

const dp = map['--nldesign-nc-dp-hover-color']
const dpDom = '<div class="vue-date-time-picker__wrapper" data-v-02e90461><div class="dp__theme_light" id="probe"></div></div>'
const dpOwn = '.vue-date-time-picker__wrapper[data-v-02e90461] .dp__theme_light { --dp-hover-color: ' + dp.stock + ' }'
check('a declared variable keeps its own value with nothing set',
	await measure({ set: '', scopesCss: scopes([]), component: dpOwn, dom: dpDom, probe: 'probe', variable: '--dp-hover-color' }), dp.stock)
check('a variable the component declares itself is reached',
	await measure({ set: ':root{--nldesign-nc-dp-hover-color:#e8eef5}', scopesCss: scopes(['--nldesign-nc-dp-hover-color']), component: dpOwn, dom: dpDom, probe: 'probe', variable: '--dp-hover-color' }), '#e8eef5')

const plyrDom = '<div class="plyr"><button id="probe"></button></div>'
const plyrRead = '.plyr button:hover { background: var(--plyr-audio-control-background-hover, #00b2ff) }'
check('a read-only slot is reached',
	await measure({ set: ':root{--nldesign-nc-plyr-audio-control-background-hover:#f4f1ea}', scopesCss: scopes(['--nldesign-nc-plyr-audio-control-background-hover']), component: plyrRead, dom: plyrDom, probe: 'probe', variable: '--plyr-audio-control-background-hover' }), '#f4f1ea')

const kpiDom = '<div class="cn-kpi-card--success" id="probe"></div>'
const kpiOwn = '.cn-kpi-card--success { --cn-kpi-accent: var(--color-success, #2d7b41) }'
check('a Conduction dashboard tile is reached',
	await measure({ set: ':root{--nldesign-cn-kpi-accent:#24578f}', scopesCss: scopes(['--nldesign-cn-kpi-accent']), component: kpiOwn, dom: kpiDom, probe: 'probe', variable: '--cn-kpi-accent' }), '#24578f')

// A variable Nextcloud declares on :root: the rule sits on body, and wins there.
const rootToken = Object.keys(map).find((k) => map[k].mode === 'selectors' && map[k].selectors.length === 1 && map[k].selectors[0] === 'body')
const rootVar = map[rootToken].variable
check('a :root variable is reached',
	await measure({ set: ':root{' + rootToken + ':7px}', scopesCss: scopes([rootToken]), component: ':root{' + rootVar + ':1px}', dom: '<p id="probe"></p>', probe: 'probe', variable: rootVar }), '7px')

// A token declared only in the dark file leaves light alone and applies in dark.
const darkSet = 'body[data-theme-dark], body[data-themes*=dark]{--nldesign-nc-dp-hover-color:#333333}'
const darkScopes = scopes([], ['--nldesign-nc-dp-hover-color'])
check('a dark-only token leaves light untouched',
	await measure({ set: darkSet, scopesCss: darkScopes, component: dpOwn, dom: dpDom, probe: 'probe', variable: '--dp-hover-color' }), dp.stock)
check('a dark-only token applies in dark',
	await measure({ set: darkSet, scopesCss: darkScopes, component: dpOwn, dom: dpDom, probe: 'probe', variable: '--dp-hover-color', dark: true }), '#333333')

await browser.close()

if (failures.length > 0) {
	process.stderr.write('internal scopes: ' + failures.length + ' check(s) failed\n  ' + failures.slice(0, 20).join('\n  ') + '\n')
	process.exit(1)
}

process.stdout.write('internal scopes: OK, ' + selectors.length + ' selectors valid, 8 reach checks pass\n')
