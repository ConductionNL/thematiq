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
 * @spec openspec/specs/token-deprecations/spec.md#requirement-consuming-apps-can-read-every-deprecation
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use InvalidArgumentException;
use OCA\Thematiq\Service\AuditAndTranslation;
use OCA\Thematiq\Service\DeprecationSources;
use OCA\Thematiq\Service\OwnTokenService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use RuntimeException;

/**
 * Token deprecations over HTTP, for administrators.
 *
 * @spec openspec/specs/token-deprecations/spec.md#requirement-consuming-apps-can-read-every-deprecation
 */
class TokenDeprecationController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request.
	 * @param DeprecationSources $sources The deprecation records and the custom overrides.
	 * @param AuditAndTranslation $answers The audit trail and the translator.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly DeprecationSources $sources,
		private readonly AuditAndTranslation $answers,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Every record, each with `own` (an own token, which can be removed) or a notice-only shipped name.
	 *
	 * @return JSONResponse Shape: {deprecations: [...]}.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - OwnTokenService::isOwnName() is a pure check
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-consuming-apps-can-read-every-deprecation
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function list(): JSONResponse {
		$rows = [];
		foreach ($this->sources->deprecations->publicList() as $row) {
			$row['own'] = OwnTokenService::isOwnName(name: $row['token']);
			$rows[] = $row;
		}

		return new JSONResponse(['deprecations' => $rows]);
	}//end list()

	/**
	 * Record or change the deprecation of the posted `token`.
	 *
	 * @return JSONResponse The stored record, or 400 naming the field.
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-consuming-apps-can-read-every-deprecation
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function save(): JSONResponse {
		$token = trim((string)$this->request->getParam('token', ''));
		$old = ($this->sources->deprecations->list()[$token] ?? null);
		try {
			$record = $this->sources->deprecations->deprecate(
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
	 * @return JSONResponse Shape: {status: ok}, or 404.
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-consuming-apps-can-read-every-deprecation
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function delete(string $token): JSONResponse {
		try {
			$old = $this->sources->deprecations->withdraw(token: $token);
		} catch (InvalidArgumentException $e) {
			return $this->refused(exception: $e);
		}

		return $this->changed(token: $token, old: $old, new: null);
	}//end delete()

	/**
	 * Record the deprecation notices an upload returned, on the administrator's click.
	 *
	 * @return JSONResponse Shape: {status: ok, recorded: [names]}.
	 *
	 * @spec openspec/specs/token-deprecations/spec.md#requirement-imported-deprecation-notices-can-be-recorded
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function adopt(): JSONResponse {
		$notices = $this->request->getParam('notices', []);
		if (is_array($notices) === false) {
			return new JSONResponse(['error' => $this->answers->l->t('The notices were not recorded.')], 400);
		}

		$recorded = $this->sources->deprecations->adoptImportNotices(notices: array_values(array_filter($notices, 'is_array')));
		foreach ($recorded as $token) {
			$this->answers->audit->log(
				action: 'token_deprecation_changed',
				context: ['token' => $token, 'old' => null, 'new' => ($this->sources->deprecations->list()[$token] ?? null)]
			);
		}

		$this->rewrite();

		return new JSONResponse(['status' => 'ok', 'recorded' => $recorded]);
	}//end adopt()

	/**
	 * After a successful change: rewrite the files, audit, answer.
	 *
	 * @param string $token The token name.
	 * @param array<string, mixed>|null $old The record before.
	 * @param array<string, mixed>|null $new The record after.
	 *
	 * @return JSONResponse
	 */
	private function changed(string $token, ?array $old, ?array $new): JSONResponse {
		$this->answers->audit->log(action: 'token_deprecation_changed', context: ['token' => $token, 'old' => $old, 'new' => $new]);
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
			$this->sources->overrides->ensureExists();
			$this->sources->overrides->rewriteAll();
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
			'token' => $this->answers->l->t('Only a thematiq token, starting with --nldesign-, can be deprecated.'),
			'severity' => $this->answers->l->t('Choose a severity: info, warning or critical.'),
			'replacement' => $this->answers->l->t('The replacement must be an existing token, other than the deprecated one.'),
			'removalDate' => $this->answers->l->t('The removal date must be a date, today or later.'),
			'message' => $this->answers->l->t('Keep the message under 500 characters, without braces or semicolons.'),
			'unknown' => $this->answers->l->t('This token has no deprecation.'),
		];
		$status = 400;
		if ($exception->getCode() === 404) {
			$status = 404;
		}

		$error = ($texts[$exception->getMessage()] ?? $this->answers->l->t('The deprecation was not saved.'));

		return new JSONResponse(['error' => $error, 'field' => $exception->getMessage()], $status);
	}//end refused()
}//end class
