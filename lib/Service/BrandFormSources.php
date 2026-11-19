<?php

/**
 * The collaborators the brand form endpoint builds and stores a set through.
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
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\App\IAppManager;

/**
 * The brand form that turns answers into a token set, custom set storage, and the app manager for the app's files.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */
final class BrandFormSources {

	/**
	 * Constructor.
	 *
	 * @param BrandFormService $brandForm The derivation.
	 * @param CustomTokenSetService $store The custom set store.
	 * @param IAppManager $apps Resolves the app root (rules and defaults).
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function __construct(
		public readonly BrandFormService $brandForm,
		public readonly CustomTokenSetService $store,
		public readonly IAppManager $apps,
	) {
	}//end __construct()
}//end class
