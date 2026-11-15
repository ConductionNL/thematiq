/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Types of check-shell-canon.mjs, for the Playwright spec that runs it in CI.
 */
import type { Page } from '@playwright/test'

export const CANON: Record<string, number>
export const KNOWN: Record<string, number>
export function measure(page: Page): Promise<Record<string, number | null>>
export function judge(measured: Record<string, number | null>): { matches: string[]; gaps: string[]; failures: string[] }
