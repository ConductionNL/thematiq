<?php

/**
 * Thematiq token deprecation controller.
 *
 * Admin-only list, record, withdraw and adopt-from-upload of token deprecations
 * on `/settings/tokens/deprecations`. Each successful change rewrites the overrides
 * files (the comment above a deprecated own token) and writes one
 * `token_deprecation_changed` audit entry; a refused request writes none.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Controller
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-3.2
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use InvalidArgumentException;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\OwnTokenService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenDeprecationService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use RuntimeException;

/**
 * Token deprecations over HTTP, for administrators.
 *
 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-3.2
 */
class TokenDeprecationController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string                  $appName      The app name.
	 * @param IRequest                $request      The request.
	 * @param TokenDeprecationService $deprecations The records.
	 * @param CustomOverridesService  $overrides    Rewrites the overrides files after a change.
	 * @param ThemingAuditService     $audit        The audit trail.
	 * @param IL10N                   $l            Translations for the error texts.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private TokenDeprecationService $deprecations,
		private CustomOverridesService $overrides,
		private ThemingAuditService $audit,
		private IL10N $l,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Every record, each with `own` (an own token, which can be removed) or a notice-only shipped name.
	 *
	 * @return JSONResponse {deprecations: [...]}.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - OwnTokenService::isOwnName() is a pure check
	 *
	 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-3.2
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function list(): JSONResponse {
		$rows = [];
		foreach ($this->deprecations->publicList() as $row) {
			$row['own'] = OwnTokenService::isOwnName(name: $row['token']);
			$rows[]     = $row;
		}

		return new JSONResponse(['deprecations' => $rows]);
	}//end list()

	/**
	 * Record or change the deprecation of the posted `token`.
	 *
	 * @return JSONResponse The stored record, or 400 naming the field.
	 *
	 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-3.2
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function save(): JSONResponse {
		$token = trim((string)$this->request->getParam('token', ''));
		$old   = ($this->deprecations->list()[$token] ?? null);
		try {
			$record = $this->deprecations->deprecate(
				token: $token,
				input: [
					'severity' => $this->request->getParam('severity', ''),
					'replacement' => $this->request->getParam('replacement', ''),
					'removalDate' => $this->request->getParam('removalDate', ''),
					'message' => $this->request->getParam('message', ''),
				]
			);
		} catch (InvalidArgumentException $e) {
			return $this->refused(exception: $e);
		}

		return $this->changed(token: $token, old: $old, new: $record);
	}//end save()

	/**
	 * Withdraw a deprecation.
	 *
	 * @param string $token The token name.
	 *
	 * @return JSONResponse {status: ok}, or 404.
	 *
	 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-3.2
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function delete(string $token): JSONResponse {
		try {
			$old = $this->deprecations->withdraw(token: $token);
		} catch (InvalidArgumentException $e) {
			return $this->refused(exception: $e);
		}

		return $this->changed(token: $token, old: $old, new: null);
	}//end delete()

	/**
	 * Record the deprecation notices an upload returned, on the administrator's click.
	 *
	 * @return JSONResponse {status: ok, recorded: [names]}.
	 *
	 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-3.4
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function adopt(): JSONResponse {
		$notices = $this->request->getParam('notices', []);
		if (is_array($notices) === false) {
			return new JSONResponse(['error' => $this->l->t('The notices were not recorded.')], 400);
		}

		$recorded = $this->deprecations->adoptImportNotices(notices: array_values(array_filter($notices, 'is_array')));
		foreach ($recorded as $token) {
			$this->audit->log(action: 'token_deprecation_changed', context: ['token' => $token, 'old' => null, 'new' => ($this->deprecations->list()[$token] ?? null)]);
		}

		$this->rewrite();

		return new JSONResponse(['status' => 'ok', 'recorded' => $recorded]);
	}//end adopt()

	/**
	 * After a successful change: rewrite the files, audit, answer.
	 *
	 * @param string                    $token The token name.
	 * @param array<string, mixed>|null $old   The record before.
	 * @param array<string, mixed>|null $new   The record after.
	 *
	 * @return JSONResponse
	 */
	private function changed(string $token, ?array $old, ?array $new): JSONResponse {
		$this->audit->log(action: 'token_deprecation_changed', context: ['token' => $token, 'old' => $old, 'new' => $new]);
		$this->rewrite();

		return new JSONResponse(['status' => 'ok', 'deprecation' => $new]);
	}//end changed()

	/**
	 * Rewrite the overrides files so a deprecated own token carries its comment. A file that
	 * cannot be written keeps the old comment until the next save; the record is stored either way.
	 *
	 * @return void
	 */
	private function rewrite(): void {
		try {
			$this->overrides->rewriteAll();
		} catch (RuntimeException) {
			// The record is the source of truth; the comment follows on the next write.
			return;
		}
	}//end rewrite()

	/**
	 * A refusal with a generic, translated text naming the field.
	 *
	 * @param InvalidArgumentException $exception What the store refused, its message the field.
	 *
	 * @return JSONResponse 400 or 404.
	 */
	private function refused(InvalidArgumentException $exception): JSONResponse {
		$texts = [
			'token' => $this->l->t('Only a thematiq token, starting with --nldesign-, can be deprecated.'),
			'severity' => $this->l->t('Choose a severity: info, warning or critical.'),
			'replacement' => $this->l->t('The replacement must be an existing token, other than the deprecated one.'),
			'removalDate' => $this->l->t('The removal date must be a date, today or later.'),
			'message' => $this->l->t('Keep the message under 500 characters, without braces or semicolons.'),
			'unknown' => $this->l->t('This token has no deprecation.'),
		];
		$status = 400;
		if ($exception->getCode() === 404) {
			$status = 404;
		}

		return new JSONResponse(['error' => ($texts[$exception->getMessage()] ?? $this->l->t('The deprecation was not saved.')), 'field' => $exception->getMessage()], $status);
	}//end refused()
}//end class
