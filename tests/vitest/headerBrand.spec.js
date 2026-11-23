/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/header-brand.js: the brand block in the top bar is drawn
 * for an app without its own brand, taken out when the app's brand appears,
 * and a change elsewhere on the page (a clock ticking) does not re-run the
 * style check or rewrite the bar.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md
 */

import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const SOURCE = readFileSync(resolve('js/header-brand.js'), 'utf8')

let styleReads

beforeEach(() => {
	document.body.innerHTML = '<header id="header"><nav class="apps"></nav></header><main><span id="clock">10:00</span></main>'
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) => {
				if (key === 'apps') {
					return { mail: { active: true, name: 'Mail', href: '/apps/mail/' } }
				}
				if (key === 'capabilities') {
					return { theming: { name: 'Zuiddrecht' }, nldesign: { logos: { emblem: '/emblem.svg' } } }
				}
				return fallback
			},
		},
	}
	styleReads = 0
	vi.spyOn(window, 'getComputedStyle').mockImplementation(() => {
		styleReads++
		return { getPropertyValue: () => '1' }
	})
	vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
		callback(0)
		return 0
	})
	window.eval(SOURCE)
})

afterEach(() => {
	vi.restoreAllMocks()
	delete global.OCP
})

/**
 * Let the MutationObserver deliver its records.
 *
 * @return {Promise<void>}
 */
const flush = () => new Promise((resolveFlush) => setTimeout(resolveFlush, 0))

describe('header brand', () => {
	it('draws the block first in the bar and marks the bar', () => {
		const header = document.getElementById('header')
		expect(header.firstChild.className).toBe('thematiq-brand-host')
		expect(header.querySelector('.thematiq-brand__name').textContent).toBe('Mail')
		expect(header.hasAttribute('data-thematiq-brand')).toBe(true)
	})

	it('does not re-check the style or rewrite the bar when the page changes elsewhere', async () => {
		const header = document.getElementById('header')
		const setAttribute = vi.spyOn(header, 'setAttribute')
		const before = styleReads

		for (let tick = 1; tick <= 5; tick++) {
			document.getElementById('clock').textContent = `10:0${tick}`
			await flush()
		}

		expect(styleReads).toBe(before)
		expect(setAttribute).not.toHaveBeenCalled()
		expect(header.firstChild.className).toBe('thematiq-brand-host')
	})

	it('takes the block out when the app draws its own brand', async () => {
		const header = document.getElementById('header')
		const own = document.createElement('div')
		own.className = 'cn-brand-bar-host'
		header.appendChild(own)
		await flush()

		expect(header.querySelector('.thematiq-brand-host')).toBeNull()
		expect(header.hasAttribute('data-thematiq-brand')).toBe(false)
	})

	it('puts the block back first when a re-render of the bar moves it', async () => {
		const header = document.getElementById('header')
		header.insertBefore(document.createElement('div'), header.firstChild)
		await flush()

		expect(header.firstChild.className).toBe('thematiq-brand-host')
	})
})
