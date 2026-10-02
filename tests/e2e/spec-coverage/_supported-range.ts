/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Nextcloud range this app supports, and the two expiry rules the
 * selector-liveness survey derives from it.
 *
 * Kept out of selector-liveness.spec.ts so a plain unit test can import it
 * (tests/vitest/supportedRange.spec.ts): a Playwright spec cannot be loaded
 * outside the Playwright runner. That test also pins both ends to
 * appinfo/info.xml, so the mirror cannot drift from the declaration it mirrors.
 */

/**
 * The oldest Nextcloud this app declares support for
 * (appinfo/info.xml `<nextcloud min-version="32" …/>`).
 */
export const MIN_SUPPORTED_NC = 32

/**
 * The newest Nextcloud this app declares support for
 * (appinfo/info.xml `<nextcloud … max-version="35"/>`). It is the expiry date
 * on every SINCE entry: once the survey reaches this major, nothing may be
 * deferred to a newer one.
 */
export const MAX_SUPPORTED_NC = 35

/**
 * An ALLOWED reason that excuses a selector as a fallback for an OLDER server.
 *
 * Derived from the reason TEXT rather than from a flag on the entry, so the
 * check cannot be bypassed by forgetting to set one (thematiq#270): an entry
 * that justifies itself with "older servers", "pre-NC34", "pre-Vue", "the
 * oldest server" or "fallback" has made a claim about a server older than the
 * newest, and that claim is testable.
 */
const OLDER_SERVER_EXCUSE =
	/\bolder\b|\boldest\b|\bpre-(?:NC ?\d+|Vue)\b|\bfallbacks?\b/i

/**
 * Whether an ALLOWED reason excuses its selector as a fallback for older servers.
 *
 * @param reason The ALLOWED entry's reason.
 * @return True when the reason claims an older server needs the selector.
 */
export function excusesAsOlderServerFallback(reason: string): boolean {
	return OLDER_SERVER_EXCUSE.test(reason)
}

/**
 * THE MIRROR OF THE MAX_SUPPORTED_NC EXPIRY.
 *
 * MAX_SUPPORTED_NC refuses to defer a selector to a NEWER server once the
 * survey reaches the newest one. This refuses to excuse a selector as a
 * fallback for an OLDER server once the survey runs on the oldest one: there
 * is no older server left for it to be a fallback for, so a selector that is
 * dead there is dead CSS with a reason that reads as justification.
 *
 * @param dead         Selectors that matched nothing on any surveyed surface.
 * @param reasonFor    The ALLOWED reason for a selector, or null when none applies.
 * @param surveyedMajor The Nextcloud major the survey ran on (0 when unknown).
 * @return The dead selectors whose older-server excuse has expired, sorted.
 */
export function expiredOlderServerExcuses(
	dead: string[],
	reasonFor: (selector: string) => string | null,
	surveyedMajor: number,
): string[] {
	if (surveyedMajor === 0 || surveyedMajor > MIN_SUPPORTED_NC) {
		return []
	}

	return dead
		.filter((selector) => {
			const reason = reasonFor(selector)
			return reason !== null && excusesAsOlderServerFallback(reason)
		})
		.sort()
}
