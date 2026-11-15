<?php

/**
 * The collaborators the playground reads a token set's values through.
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

use OCP\App\IAppManager;

/**
 * The app directory, the resolved token values and their sources, the conversion reason vocabulary and the instance's own stock theme.
 *
 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
 */
final class PlaygroundTokenSources {

	/**
	 * Constructor.
	 *
	 * @param IAppManager $appManager Resolves the app directory.
	 * @param TokenSetPreviewService $previewValues Resolves token values and sources.
	 * @param TokenSetConverterService $converter The conversion reason vocabulary.
	 * @param StockTokensService $stockTokens The instance's own stock theme.
	 *
	 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
	 */
	public function __construct(
		public readonly IAppManager $appManager,
		public readonly TokenSetPreviewService $previewValues,
		public readonly TokenSetConverterService $converter,
		public readonly StockTokensService $stockTokens,
	) {
	}//end __construct()
}//end class
