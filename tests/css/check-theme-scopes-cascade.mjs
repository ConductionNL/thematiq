#!/usr/bin/env node

/**
 * Prove the theme scopes in a real browser: unset tokens change nothing, set
 * tokens reach the page, and a light-only colour stays out of dark mode.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * Nextcloud's own theme values come from scripts/mapping/nextcloud-variables.json
 * (the stock value per theme the inventory records), declared under the same
 * `[data-theme-*]` selectors Nextcloud serves them with. thematiq's stylesheets
 * are the real files, in injection order. No Nextcloud instance is needed.
 *
 * Run: npm run test:theme-scopes   (needs a Chromium; set CHROME_PATH to use a system Chrome)
 *
 * @spec openspec/changes/theme-vocabulary-complete/specs/nextcloud-variable-mapping/spec.md
 */

import { chromium } from 'playwright'
import { readFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const read = (file) => readFileSync(join(ROOT, file), 'utf8')
const inventory = JSON.parse(read('scripts/mapping/nextcloud-variables.json')).variables
const names = Object.keys(inventory).filter((n) => inventory[n].class === 'theme').sort()

/** Nextcloud's default and dark theme, rebuilt from the recorded stock values. */
function nextcloudTheme(scheme, selector) {
	const lines = names
		.filter((n) => inventory[n].stock?.[scheme] !== undefined)
		.map((n) => '\t' + n + ': ' + inventory[n].stock[scheme] + ';')
	return selector + ' {\n' + lines.join('\n') + '\n}\n'
}

const STACK = [
	'css/systems/nldesign/defaults.css', 'css/systems/nldesign/utrecht-bridge.css', 'css/systems/nldesign/theme.css',
	'css/systems/nldesign/overrides.css', 'css/systems/nldesign/element-overrides.css',
	'css/tokens/rijkshuisstijl.css', 'css/tokens/dark/rijkshuisstijl.css', 'css/icon-contrast.css', 'css/error-contrast.css',
].map(read)

function page(sheets) {
	const css = [nextcloudTheme('default', '[data-theme-default]'), nextcloudTheme('dark', '[data-theme-dark]'), ...STACK, ...sheets]
	return '<!doctype html><html><head>' + css.map((c) => '<style>' + c + '</style>').join('')
		+ '</head><body data-theme-default data-themes="default"><div id="content"><p id="probe">x</p></div></body></html>'
}

async function measure(browser, html) {
	const tab = await browser.newPage()
	await tab.setContent(html)
	const sample = () => tab.evaluate((list) => {
		const probe = getComputedStyle(document.getElementById('probe'))
		const out = Object.fromEntries(list.map((n) => [n, probe.getPropertyValue(n).trim()]))
		const modal = document.createElement('div')
		document.body.appendChild(modal)
		out['modal:--color-mark'] = getComputedStyle(modal).getPropertyValue('--color-mark').trim()
		modal.remove()
		return out
	}, names)
	const light = await sample()
	await tab.evaluate(() => {
		document.body.removeAttribute('data-theme-default')
		document.body.setAttribute('data-theme-dark', '')
		document.body.setAttribute('data-themes', 'dark')
	})
	const dark = await sample()
	await tab.close()
	return { light, dark }
}

const browser = await chromium.launch({ headless: true, ...(process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}) })
const failures = []
const check = (label, got, want) => {
	if (got !== want) failures.push(label + ': got ' + got + ', wanted ' + want)
}

// 1. With no token set, the theme scopes change no theme variable, light or dark.
const without = await measure(browser, page([read('css/component-scopes.css')]))
const withScopes = await measure(browser, page([read('css/theme-scopes.css'), read('css/component-scopes.css')]))
for (const scheme of ['light', 'dark']) {
	for (const name of names) {
		check('unset ' + scheme + ' ' + name, withScopes[scheme][name], without[scheme][name])
	}
}

// 2. A set's tokens reach the page, per the rules in the spec.
const setLight = ':root{--nldesign-nc-color-mark:#ffe08a;--nldesign-nc-header-height:56px;--nldesign-nc-color-box-shadow:rgba(1, 2, 3, 0.5)}'
const setDark = 'body[data-theme-dark], body[data-themes*=dark]{--nldesign-nc-color-box-shadow:rgba(9, 9, 9, 0.5)}'
const set = await measure(browser, page([setLight, setDark, read('css/theme-scopes.css'), read('css/component-scopes.css')]))
check('a set gives the search highlight a colour', set.light['--color-mark'], '#ffe08a')
check('the highlight reaches a modal appended to body', set.light['modal:--color-mark'], '#ffe08a')
check('a light-only colour keeps Nextcloud\'s dark value', set.dark['--color-mark'], without.dark['--color-mark'])
check('a size applies in light', set.light['--header-height'], '56px')
check('a size applies in dark too', set.dark['--header-height'], '56px')
check('a light value applies in light', set.light['--color-box-shadow'], 'rgba(1, 2, 3, 0.5)')
check('a dark-file value wins in dark', set.dark['--color-box-shadow'], 'rgba(9, 9, 9, 0.5)')
await browser.close()

if (failures.length > 0) {
	process.stderr.write('theme-scopes cascade: ' + failures.length + ' check(s) failed\n  ' + failures.slice(0, 20).join('\n  ') + '\n')
	process.exit(1)
}

process.stdout.write('theme-scopes cascade: OK, ' + names.length + ' variables unchanged in light and dark, 7 set checks pass\n')
