<?php

/**
 * NL Design Theme Preview Service.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/theme-preview/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use InvalidArgumentException;
use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Owns "proefdraaien" — the per-admin, session-scoped theme preview
 * lifecycle: start, read the active state, discard, and publish (promote to
 * the instance-wide active token set).
 *
 * State lives EXCLUSIVELY as IConfig *user* values (never app values, never a
 * DB table — this app has neither), so a preview is invisible to every other
 * user by construction. Expiry is lazy (read-time only): the app has no
 * background jobs and this feature does not justify one.
 *
 * {@see resolveEffectiveTokenSet()} is the single, testable home of the
 * "which token set actually renders for this request" contract that the CSS
 * injection layer consumes (currently `Application::injectThemeCSS()`; if
 * change `render-event-injection` lands, its render-event listener calls the
 * same method). Keeping the whole contract — user resolution, the
 * demotion-defence admin re-check, expiry, and validity — inside one service
 * method means a future relocation of the CALL SITE never has to re-derive
 * the RULES.
 *
 * @spec openspec/specs/theme-preview/spec.md
 */
class ThemePreviewService {

	/**
	 * IConfig user-value key: the previewed token set id (empty/absent = no preview).
	 *
	 * @var string
	 */
	private const USER_KEY_TOKEN_SET = 'preview_token_set';

	/**
	 * IConfig user-value key: unix timestamp (as string) the preview expires at.
	 *
	 * @var string
	 */
	private const USER_KEY_EXPIRES_AT = 'preview_expires_at';

	/**
	 * Preview lifetime in seconds (24 hours).
	 *
	 * @var int
	 */
	private const PREVIEW_DURATION_SECONDS = 86400;

	/**
	 * The application configuration service (also stores per-user preview state).
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The group manager — backs the render-time admin re-check (demotion defence).
	 *
	 * @var IGroupManager
	 */
	private IGroupManager $groupManager;

	/**
	 * The token set service — validates ids at start and at every read (deleted-set defence).
	 *
	 * @var TokenSetService
	 */
	private TokenSetService $tokenSetService;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @param IConfig $config The config service.
	 * @param IGroupManager $groupManager The group manager.
	 * @param TokenSetService $tokenSetService The token set service.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		IConfig $config,
		IGroupManager $groupManager,
		TokenSetService $tokenSetService,
		LoggerInterface $logger,
	) {
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->tokenSetService = $tokenSetService;
		$this->logger = $logger;
	}//end __construct()

	/**
	 * Start a preview for the given user: writes the two user values and
	 * returns the preview state. Rejects ids that do not validate.
	 *
	 * @param string $uid The previewing admin's uid.
	 * @param string $tokenSetId The token set id to preview.
	 *
	 * @return array{tokenSet: string, expiresAt: int} The started preview state.
	 *
	 * @throws InvalidArgumentException When the token set id does not validate, or (as a
	 *                                  RestrictedSetNotConfirmedException) the set is restricted and not confirmed.
	 *
	 * @spec openspec/specs/theme-preview/spec.md#requirement-per-user-preview-state
	 */
	public function startPreview(string $uid, string $tokenSetId): array {
		if ($this->tokenSetService->isValidTokenSet(tokenSetId: $tokenSetId) === false) {
			$this->logger->warning('Refused to start a theme preview of an invalid token set.', ['uid' => $uid, 'tokenSet' => $tokenSetId]);
			throw new InvalidArgumentException('Invalid token set: ' . $tokenSetId);
		}

		// A preview draws the set on this instance, so it is a use of it.
		$this->tokenSetService->licenceGate()->assertMayApply(tokenSetId: $tokenSetId);

		$expiresAt = (time() + self::PREVIEW_DURATION_SECONDS);

		$this->config->setUserValue($uid, Application::APP_ID, self::USER_KEY_TOKEN_SET, $tokenSetId);
		$this->config->setUserValue($uid, Application::APP_ID, self::USER_KEY_EXPIRES_AT, (string)$expiresAt);

		return [
			'tokenSet' => $tokenSetId,
			'expiresAt' => $expiresAt,
		];
	}//end startPreview()

	/**
	 * Read the active preview for a user, or null when there is none.
	 *
	 * Treats an expired or no-longer-valid (e.g. a deleted custom set) preview
	 * as absent, and opportunistically clears the stale user values in that
	 * case — this method is called from the settings panel / controller
	 * context, never from the render/boot path, so writing here is safe.
	 *
	 * @param string $uid The uid to read the preview for.
	 *
	 * @return array{tokenSet: string, expiresAt: int}|null The active preview, or null.
	 *
	 * @spec openspec/specs/theme-preview/spec.md#requirement-per-user-preview-state
	 */
	public function getActivePreview(string $uid): ?array {
		$tokenSetId = $this->config->getUserValue($uid, Application::APP_ID, self::USER_KEY_TOKEN_SET, '');
		if ($tokenSetId === '') {
			return null;
		}

		$expiresAt = (int)$this->config->getUserValue($uid, Application::APP_ID, self::USER_KEY_EXPIRES_AT, '0');
		if ($expiresAt <= time()) {
			$this->clearPreview(uid: $uid);
			return null;
		}

		if ($this->tokenSetService->isValidTokenSet(tokenSetId: $tokenSetId) === false) {
			$this->clearPreview(uid: $uid);
			return null;
		}

		return [
			'tokenSet' => $tokenSetId,
			'expiresAt' => $expiresAt,
		];
	}//end getActivePreview()

	/**
	 * Discard a user's preview — deletes both user values.
	 *
	 * @param string $uid The uid to clear the preview for.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-preview/spec.md#requirement-per-user-preview-state
	 */
	public function clearPreview(string $uid): void {
		$this->config->deleteUserValue($uid, Application::APP_ID, self::USER_KEY_TOKEN_SET);
		$this->config->deleteUserValue($uid, Application::APP_ID, self::USER_KEY_EXPIRES_AT);
	}//end clearPreview()

	/**
	 * Promote a user's active preview to the instance-wide active token set,
	 * then clear the preview.
	 *
	 * @param string $uid The publishing admin's uid.
	 *
	 * @return string The published token set id.
	 *
	 * @throws RuntimeException When the caller has no active (non-expired, still-valid) preview.
	 * @throws RestrictedSetNotConfirmedException When the previewed set is restricted and its confirmation is gone.
	 *
	 * @spec openspec/specs/theme-preview/spec.md#requirement-preview-lifecycle-endpoints
	 */
	public function publishPreview(string $uid): string {
		$active = $this->getActivePreview(uid: $uid);
		if ($active === null) {
			throw new RuntimeException('No active preview to publish for uid: ' . $uid);
		}

		$this->tokenSetService->licenceGate()->assertMayApply(
			tokenSetId: $active['tokenSet'],
			alreadyApplied: (string)$this->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud')
		);
		$this->config->setAppValue(Application::APP_ID, 'token_set', $active['tokenSet']);
		$this->clearPreview(uid: $uid);

		return $active['tokenSet'];
	}//end publishPreview()

	/**
	 * Resolve the *effective* token set for the current request — the single
	 * contract the CSS injection layer relies on (see class docblock).
	 *
	 * The requesting user's preview substitutes `$activeTokenSet` only when
	 * ALL hold: a user session exists, a (non-empty) preview value is set,
	 * the user is CURRENTLY an admin (re-checked here, not cached from start
	 * time — the demotion defence), `preview_expires_at` is in the future,
	 * and the id still validates. The (potentially costlier) admin
	 * membership check only runs once a preview value is confirmed present,
	 * so every ordinary request without a preview pays for one cheap user-
	 * value read and nothing else. Any failure (no session, CLI/occ, cron,
	 * or an unexpected exception resolving the user) falls back to
	 * `$activeTokenSet` with `previewActive: false` — this is presentation,
	 * never security, and must never break page rendering.
	 *
	 * @param IUserSession $userSession The current user session.
	 * @param string $activeTokenSet The instance-wide active token set id.
	 *
	 * @return array{tokenSet: string, previewActive: bool, expiresAt: int|null} The effective token set.
	 *
	 * @spec openspec/specs/theme-preview/spec.md#requirement-preview-isolation
	 */
	public function resolveEffectiveTokenSet(IUserSession $userSession, string $activeTokenSet): array {
		try {
			$user = $userSession->getUser();
			if ($user === null) {
				return $this->noPreviewResult(activeTokenSet: $activeTokenSet);
			}

			$uid = $user->getUID();

			$previewTokenSet = $this->config->getUserValue($uid, Application::APP_ID, self::USER_KEY_TOKEN_SET, '');
			if ($previewTokenSet === '') {
				return $this->noPreviewResult(activeTokenSet: $activeTokenSet);
			}

			// Demotion defence: only reached when a preview value exists.
			if ($this->groupManager->isAdmin($uid) === false) {
				return $this->noPreviewResult(activeTokenSet: $activeTokenSet);
			}

			$expiresAt = (int)$this->config->getUserValue($uid, Application::APP_ID, self::USER_KEY_EXPIRES_AT, '0');
			if ($expiresAt <= time()) {
				return $this->noPreviewResult(activeTokenSet: $activeTokenSet);
			}

			if ($this->tokenSetService->isValidTokenSet(tokenSetId: $previewTokenSet) === false) {
				return $this->noPreviewResult(activeTokenSet: $activeTokenSet);
			}

			// A preview that outlived its set's confirmation is not drawn:
			// assertMayApply() throws, and the catch below falls back to the active set.
			$this->tokenSetService->licenceGate()->assertMayApply(tokenSetId: $previewTokenSet, alreadyApplied: $activeTokenSet);

			return [
				'tokenSet' => $previewTokenSet,
				'previewActive' => true,
				'expiresAt' => $expiresAt,
			];
		} catch (Throwable $e) {
			// Fail open to the active set: presentation, not security — a
			// broken resolve (no session, CLI/occ, cron) must not crash boot.
			$this->logger->debug(
				'Theme preview resolution failed; falling back to the active token set.',
				['exception' => $e]
			);

			return $this->noPreviewResult(activeTokenSet: $activeTokenSet);
		}//end try
	}//end resolveEffectiveTokenSet()

	/**
	 * Build the "no active preview" effective-token-set result.
	 *
	 * @param string $activeTokenSet The instance-wide active token set id.
	 *
	 * @return array{tokenSet: string, previewActive: bool, expiresAt: int|null}
	 */
	private function noPreviewResult(string $activeTokenSet): array {
		return [
			'tokenSet' => $activeTokenSet,
			'previewActive' => false,
			'expiresAt' => null,
		];
	}//end noPreviewResult()
}//end class
