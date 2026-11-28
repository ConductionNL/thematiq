<?php

/**
 * The first reads of the Theming admin page, answered in its own render.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Settings
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Settings;

use OCA\Thematiq\Controller\BrandFormController;
use OCA\Thematiq\Controller\ConfigSourceController;
use OCA\Thematiq\Controller\CustomCssController;
use OCA\Thematiq\Controller\CustomTokenSetController;
use OCA\Thematiq\Controller\FontController;
use OCA\Thematiq\Controller\LayerController;
use OCA\Thematiq\Controller\OverridesController;
use OCA\Thematiq\Controller\OwnComponentController;
use OCA\Thematiq\Controller\OwnTokenController;
use OCA\Thematiq\Controller\ScheduledSwitchController;
use OCA\Thematiq\Controller\SettingsController;
use OCA\Thematiq\Controller\SetupController;
use OCA\Thematiq\Controller\TokenDeprecationController;
use OCA\Thematiq\Controller\TokenSetLicenceController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The answers to the GETs the Theming admin page makes as it opens, by the
 * path js/lib/bootReads.js matches them on.
 *
 * The page's blocks each ask the server for their first state. In the
 * browser those fifteen requests queue behind one another (one session),
 * which made the page usable only after 0.8 to 1.8 s although each answer
 * takes 40-70 ms on its own. The render already runs as the same admin, so
 * it asks the same controller actions once and hands their answers over as
 * initial state; the blocks still fetch, and js/lib/bootReads.js answers
 * them from this state until the admin first acts on the page.
 *
 * Every entry goes through the controller action the route serves, so the
 * answer is the one the request would have had. An action that throws or
 * answers anything but 200 is left out, and its block fetches as before.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */
final class AdminBootReads {

	/**
	 * The reads that take no argument: path under /apps/thematiq => [controller, action].
	 *
	 * @var array<string, array{0: class-string, 1: string}>
	 */
	private const PLAIN_READS = [
		'/settings/scheduled-switches' => [ScheduledSwitchController::class, 'index'],
		'/settings/app-theming' => [SettingsController::class, 'getAppTheming'],
		'/settings/playground/components' => [OwnComponentController::class, 'list'],
		'/settings/tokens/own' => [OwnTokenController::class, 'list'],
		'/settings/tokens/deprecations' => [TokenDeprecationController::class, 'list'],
		'/settings/custom-css' => [CustomCssController::class, 'getCustomCss'],
		'/settings/tokensets/from-colours' => [BrandFormController::class, 'inputs'],
		'/settings/tokensets/custom' => [CustomTokenSetController::class, 'list'],
		'/settings/tokensets/licences' => [TokenSetLicenceController::class, 'index'],
		'/settings/fonts' => [FontController::class, 'list'],
		'/settings/fonts/library' => [FontController::class, 'library'],
		'/settings/config-source' => [ConfigSourceController::class, 'status'],
		// The version card reads the setup status as the page opens (first-time-setup REQ-TQFS-012).
		'/api/setup/status' => [SetupController::class, 'status'],
	];

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container Builds the controllers, only when the page renders.
	 * @param LoggerInterface $logger Notes a read that could not be answered in the render.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The answers, by path, for a page that edits the given token set.
	 *
	 * @param string $tokenSetId The set the page opens on: its overrides and its stylesheets are read at boot.
	 *
	 * @return array<string, mixed> Path (with query, as the page asks it) => the JSON body.
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	public function forTokenSet(string $tokenSetId): array {
		$reads = [];
		foreach (self::PLAIN_READS as $path => [$class, $action]) {
			$this->add(reads: $reads, path: $path, read: fn (): JSONResponse => $this->container->get($class)->$action());
		}

		$this->add(
			reads: $reads,
			path: '/settings/overrides?tokenSet=' . rawurlencode($tokenSetId),
			read: fn (): JSONResponse => new JSONResponse($this->container->get(OverridesController::class)->overridesPayload(tokenSet: $tokenSetId))
		);
		$this->add(
			reads: $reads,
			path: '/settings/tokenset-stylesheets/' . rawurlencode($tokenSetId),
			read: fn (): JSONResponse => $this->container->get(LayerController::class)->getStylesheets(tokenSetId: $tokenSetId)
		);

		return $reads;
	}//end forTokenSet()

	/**
	 * Run one read and keep its body when it answered 200.
	 *
	 * @param array<string, mixed> $reads The answers so far.
	 * @param string $path The path the page asks.
	 * @param callable(): JSONResponse $read The controller action.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/admin-settings/spec.md
	 */
	private function add(array &$reads, string $path, callable $read): void {
		try {
			$response = $read();
		} catch (Throwable $e) {
			$this->logger->debug('thematiq: boot read ' . $path . ' left to the page: ' . $e->getMessage());
			return;
		}

		if ($response->getStatus() === Http::STATUS_OK) {
			$reads[$path] = $response->getData();
		}
	}//end add()
}//end class
