/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/lib/afterPaint.js and the boot reads that use it: the
 * Theming admin page reads computed style only once it has painted.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

async function loadHelper() {
	vi.resetModules()
	delete window.ThematiqAfterPaint
	await import('../../js/lib/afterPaint.js?t=' + Math.random())
}

describe('ThematiqAfterPaint', () => {
	let savedIdle

	beforeEach(async () => {
		savedIdle = window.requestIdleCallback
		await loadHelper()
	})

	afterEach(() => {
		window.requestIdleCallback = savedIdle
		vi.useRealTimers()
	})

	it('does not run the work at once', () => {
		window.requestIdleCallback = vi.fn()
		const run = vi.fn()
		window.ThematiqAfterPaint(run)
		expect(run).not.toHaveBeenCalled()
	})

	it('runs the work once in an idle callback, waiting at most one second', () => {
		const callbacks = []
		window.requestIdleCallback = vi.fn((cb, options) => {
			callbacks.push([cb, options])
			return 1
		})
		const run = vi.fn()
		window.ThematiqAfterPaint(run)
		expect(window.requestIdleCallback).toHaveBeenCalledTimes(1)
		expect(callbacks[0][1]).toEqual({ timeout: 1000 })
		callbacks[0][0]({ didTimeout: false, timeRemaining: () => 10 })
		expect(run).toHaveBeenCalledTimes(1)
	})

	it('falls back to a timeout where the browser has no idle callback', () => {
		vi.useFakeTimers()
		window.requestIdleCallback = undefined
		const run = vi.fn()
		window.ThematiqAfterPaint(run)
		expect(run).not.toHaveBeenCalled()
		vi.advanceTimersByTime(5)
		expect(run).toHaveBeenCalledTimes(1)
	})
})
