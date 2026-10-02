<?php

/**
 * Thematiq middleware: refuse web configuration changes while the lock is on.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Middleware
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Middleware;

use Exception;
use OCA\Thematiq\Service\ConfigSourceService;
use OCA\Thematiq\Service\Exception\ConfigSourceLockedException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IL10N;
use OCP\IRequest;

/**
 * While `thematiq.config_source_lock` is true, every request that changes the
 * configuration answers 423 naming the source. A request changes the
 * configuration when it is not a GET and it is not one of the few actions
 * that only touch the admin's own session (a preview, a restore preview, a
 * dismissed notice).
 *
 * Runs after Nextcloud's security middleware, so a non-admin still gets the
 * usual 403 first.
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
class ConfigSourceLockMiddleware extends Middleware {

	/**
	 * Actions that write no configuration, as `ControllerShortName::method`.
	 *
	 * @var array<int, string>
	 */
	public const SESSION_ONLY = [
		'PreviewController::start',
		'PreviewController::discard',
		'AuditController::previewVersion',
		'SettingsController::dismissUpstreamNotice',
		'ContrastController::evaluate',
	];

	/**
	 * Constructor.
	 *
	 * @param IRequest            $request The request.
	 * @param ConfigSourceService $source  The configuration source service.
	 * @param IL10N               $l10n    Translations.
	 */
	public function __construct(
		private readonly IRequest $request,
		private readonly ConfigSourceService $source,
		private readonly IL10N $l10n,
	) {
	}//end __construct()

	/**
	 * Refuse a configuration change while the lock is on.
	 *
	 * @param Controller $controller The controller.
	 * @param string $methodName The method.
	 *
	 * @return void
	 *
	 * @throws ConfigSourceLockedException When the request would change a locked configuration.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 */
	public function beforeController(Controller $controller, string $methodName): void {
		if (strtoupper($this->request->getMethod()) === 'GET' || $this->source->isLocked() === false) {
			return;
		}

		$class = get_class($controller);
		if (str_starts_with($class, 'OCA\\Thematiq\\Controller\\') === false) {
			return;
		}

		$short = substr($class, (strrpos($class, '\\') + 1));
		if (in_array($short . '::' . $methodName, self::SESSION_ONLY, true) === true) {
			return;
		}

		throw new ConfigSourceLockedException(
			$this->l10n->t(
				'The house style is managed from deployment configuration (%s). Change it there.',
				[(string)$this->source->getSourcePath()]
			)
		);
	}//end beforeController()

	/**
	 * Turn the lock refusal into a 423 response.
	 *
	 * @param Controller $controller The controller.
	 * @param string $methodName The method.
	 * @param Exception $exception The exception.
	 *
	 * @return Response The 423 response.
	 *
	 * @throws Exception Any other exception, unchanged.
	 *
	 * @spec openspec/specs/theme-as-code/spec.md
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) - Middleware's signature.
	 */
	public function afterException(Controller $controller, string $methodName, Exception $exception): Response {
		if ($exception instanceof ConfigSourceLockedException) {
			return new JSONResponse(['error' => $exception->getMessage(), 'locked' => true], Http::STATUS_LOCKED);
		}

		throw $exception;
	}//end afterException()
}//end class
