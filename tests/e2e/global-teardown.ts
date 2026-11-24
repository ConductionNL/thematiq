/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Withdraws the licence confirmations global setup gave (see restricted-sets.ts).
 */
import type { FullConfig } from '@playwright/test'

import { resolveBaseUrl } from './base-url'
import { withdrawRecordedConfirmations } from './restricted-sets'

export default async function globalTeardown(config: FullConfig): Promise<void> {
	const baseURL =
		(config.projects[0]?.use?.baseURL as string | undefined) ?? resolveBaseUrl()
	await withdrawRecordedConfirmations(
		baseURL,
		process.env.NC_ADMIN_USER ?? 'admin',
		process.env.NC_ADMIN_PASS ?? 'admin',
	)
}
