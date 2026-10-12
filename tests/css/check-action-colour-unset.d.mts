/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Types of check-action-colour-unset.mjs, for the Playwright spec that runs it in CI.
 */
import type { Browser } from '@playwright/test'

export function unwrap(css: string): string
export function compare(
	browser: Browser,
	options?: { workers?: number },
): Promise<{
	sets: number
	values: number
	differences: string[]
	blindControls: string[]
	leftovers: string[]
}>
