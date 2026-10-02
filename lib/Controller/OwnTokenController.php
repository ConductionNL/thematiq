<?php

/**
 * Thematiq own token controller.
 *
 * Admin-only list, create, update and delete of an administrator's own tokens on
 * `/settings/tokens/own`. Every change rewrites the overrides files, so the token
 * is on the next page load, and writes one `own_token_changed` audit entry.
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
 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-2.3
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
 * Own tokens over HTTP.
 *
 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-2.3
 */
class OwnTokenController extends Controller {

	/**
	 * Constructor.
	 *
	 * @param string                  $appName      The app name.
	 * @param IRequest                $request      The request.
	 * @param OwnTokenService         $ownTokens    The own token store.
	 * @param TokenDeprecationService $deprecations Marks a removed token's deprecation as removed.
	 * @param CustomOverridesService  $overrides    Rewrites the overrides files after a change.
	 * @param ThemingAuditService     $audit        The audit trail.
	 * @param IL10N                   $l            Translations for the error texts.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		private OwnTokenService $ownTokens,
		private TokenDeprecationService $deprecations,
		private CustomOverridesService $overrides,
		private ThemingAuditService $audit,
		private IL10N $l,
	) {
		parent::__construct(appName: $appName, request: $request);
	}//end __construct()

	/**
	 * Every own token, with its deprecation when it has one.
	 *
	 * @return JSONResponse {tokens: [{name, label, type, value, darkValue?, description?, deprecation?}], prefix}.
	 *
	 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-2.3
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function list(): JSONResponse {
		$deprecations = $this->deprecations->list();
		$tokens       = [];
		foreach ($this->ownTokens->list() as $name => $token) {
			$row = ['name' => $name] + $token;
			if (isset($deprecations[$name]) === true) {
				$row['deprecation'] = $deprecations[$name];
			}

			$tokens[] = $row;
		}

		return new JSONResponse(['tokens' => $tokens, 'prefix' => OwnTokenService::PREFIX]);
	}//end list()

	/**
	 * Add a token.
	 *
	 * @return JSONResponse The stored token, or 400 with a generic text naming the field.
	 *
	 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-2.3
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function create(): JSONResponse {
		try {
			$token = $this->ownTokens->create(input: $this->input());
		} catch (InvalidArgumentException $e) {
			return $this->refused(exception: $e);
		}

		return $this->changed(name: $token['name'], old: null, new: $token);
	}//end create()

	/**
	 * Change a token. The name stays.
	 *
	 * @param string $name The full token name.
	 *
	 * @return JSONResponse The stored token, 400 for a wrong field, 404 for an unknown name.
	 *
	 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-2.3
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function update(string $name): JSONResponse {
		$old = ($this->ownTokens->list()[$name] ?? null);
		try {
			$token = $this->ownTokens->update(name: $name, input: $this->input());
		} catch (InvalidArgumentException $e) {
			return $this->refused(exception: $e);
		}

		return $this->changed(name: $name, old: $old, new: $token);
	}//end update()

	/**
	 * Remove a token. Its deprecation, if any, stays with the state `removed`.
	 *
	 * @param string $name The full token name.
	 *
	 * @return JSONResponse {status: ok}, or 404 for an unknown name.
	 *
	 * @spec openspec/changes/authoring-token-lifecycle/tasks.md#task-2.3
	 */
	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function delete(string $name): JSONResponse {
		try {
			$old = $this->ownTokens->remove(name: $name);
		} catch (InvalidArgumentException $e) {
			return $this->refused(exception: $e);
		}

		$this->deprecations->markRemoved(token: $name);

		return $this->changed(name: $name, old: $old, new: null);
	}//end delete()

	/**
	 * The submitted fields.
	 *
	 * @return array<string, mixed>
	 */
	private function input(): array {
		$input = [];
		foreach (['slug', 'label', 'type', 'value', 'darkValue', 'description'] as $key) {
			$input[$key] = $this->request->getParam($key, '');
		}

		return $input;
	}//end input()

	/**
	 * After a successful change: rewrite the files, audit, answer.
	 *
	 * @param string                    $name The token name.
	 * @param array<string, mixed>|null $old  The token before, null when added.
	 * @param array<string, mixed>|null $new  The token after, null when removed.
	 *
	 * @return JSONResponse
	 */
	private function changed(string $name, ?array $old, ?array $new): JSONResponse {
		try {
			$this->overrides->ensureExists();
			$this->overrides->rewriteAll();
		} catch (RuntimeException) {
			return new JSONResponse(['error' => $this->l->t('The token was saved, but the stylesheet could not be written. Save the token editor once to write it.')], 500);
		}

		$this->audit->log(action: 'own_token_changed', context: ['token' => $name, 'old' => $old, 'new' => $new]);

		return new JSONResponse(['status' => 'ok', 'token' => $new]);
	}//end changed()

	/**
	 * A refusal with a generic, translated text naming the field.
	 *
	 * @param InvalidArgumentException $exception What the store refused, its message the field.
	 *
	 * @return JSONResponse 400 or 404.
	 */
	private function refused(InvalidArgumentException $exception): JSONResponse {
		$texts = [
			'name' => $this->l->t('Use lowercase letters, digits and single dashes for the name, at most 48 characters.'),
			'duplicate' => $this->l->t('A token with this name already exists.'),
			'label' => $this->l->t('Give the token a label of at most 80 characters.'),
			'type' => $this->l->t('Choose a type: colour, text, duration or easing.'),
			'value' => $this->l->t('The value does not fit the type.'),
			'darkValue' => $this->l->t('A dark value must be a colour, and only a colour token has one.'),
			'description' => $this->l->t('Keep the description under 500 characters.'),
			'unknown' => $this->l->t('There is no own token with this name.'),
		];
		$status = 400;
		if ($exception->getCode() === 404) {
			$status = 404;
		}

		return new JSONResponse(['error' => ($texts[$exception->getMessage()] ?? $this->l->t('The token was not saved.')), 'field' => $exception->getMessage()], $status);
	}//end refused()
}//end class
