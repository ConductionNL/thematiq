<?php

/**
 * Component playground state.
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
 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\App\IAppManager;
use Throwable;

/**
 * Everything `js/playground.js` needs at boot, gathered in one place.
 *
 * The settings panel is already a long constructor, and the playground needs
 * three things it has no other reason to know about: the component inventory
 * file, the converter's reason vocabulary and the token values of the active
 * set. They are assembled here so `Settings\Admin` takes one collaborator
 * instead of three, and so the assembly can be tested without rendering a
 * settings form.
 *
 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
 */
class PlaygroundStateService {

	/**
	 * Resolves the app directory, so the inventory is read from the installed
	 * app rather than from a path guessed relative to this file.
	 *
	 * @var IAppManager
	 */
	private IAppManager $appManager;

	/**
	 * Resolves the token values and the variable-to-token map.
	 *
	 * @var TokenSetPreviewService
	 */
	private TokenSetPreviewService $previewValues;

	/**
	 * Owns the reason-code vocabulary the playground's token-less rows are
	 * worded from.
	 *
	 * @var TokenSetConverterService
	 */
	private TokenSetConverterService $converter;

	/**
	 * Constructor.
	 *
	 * @param IAppManager              $appManager    Resolves the app directory.
	 * @param TokenSetPreviewService   $previewValues Resolves token values and sources.
	 * @param TokenSetConverterService $converter     The conversion reason vocabulary.
	 */
	public function __construct(
		IAppManager $appManager,
		TokenSetPreviewService $previewValues,
		TokenSetConverterService $converter,
	) {
		$this->appManager = $appManager;
		$this->previewValues = $previewValues;
		$this->converter = $converter;
	}//end __construct()

	/**
	 * The four initial-state keys the instrument reads.
	 *
	 * @param string $tokenSetId The token set the page is wearing.
	 *
	 * @return array<string, mixed> Map of initial-state key to value.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	public function getInitialState(string $tokenSetId): array {
		return [
			'playgroundInventory' => $this->getInventory(),
			'playgroundReasons' => $this->getReasons(),
			'playgroundTokens' => $this->previewValues->getResolvedTokens(tokenSetId: $tokenSetId),
			'playgroundTokenSources' => $this->previewValues->getTokenSources(),
		];
	}//end getInitialState()

	/**
	 * The component inventory that decides which chips the instrument offers
	 * and which tokens each one filters the editor down to.
	 *
	 * A missing or malformed file yields an empty component list rather than an
	 * exception: the instrument then simply does not build, and the settings
	 * panel it is built into keeps working, which is the failure an admin can
	 * still do their job through.
	 *
	 * @return array<string, mixed> The decoded inventory, or an empty one.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	public function getInventory(): array {
		$path = $this->appManager->getAppPath(Application::APP_ID) . '/js/playground/components.json';

		$raw = false;
		if (is_file($path) === true) {
			$raw = file_get_contents($path);
		}

		if ($raw === false) {
			return ['version' => 0, 'tabs' => [], 'components' => []];
		}

		$decoded = json_decode($raw, true);
		if (is_array($decoded) === false || is_array($decoded['components'] ?? null) === false) {
			return ['version' => 0, 'tabs' => [], 'components' => []];
		}

		return $decoded;
	}//end getInventory()

	/**
	 * The reason-code vocabulary, so a row with no token and a token an import
	 * had to skip are explained in the same words.
	 *
	 * @return array<string, string> Map of reason code to sentence.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	public function getReasons(): array {
		try {
			return $this->converter->getReasons();
		} catch (Throwable $e) {
			// The mapping table is the converter's own dependency. Without it
			// every row still renders; only the explanatory sentence falls back
			// to the one the inventory carries itself.
			return [];
		}
	}//end getReasons()
}//end class
