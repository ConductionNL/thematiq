<?php

/**
 * The optional collaborators that give each token set its own overrides.
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
 * @spec openspec/specs/custom-css-overrides/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\IConfig;

/**
 * With the config and the design systems, each set reads its own file; without them every set reads the shared
 * file. Without a value grammar the default one applies, without own tokens the file holds none.
 *
 * @spec openspec/specs/custom-css-overrides/spec.md
 */
final class OverridesSetContext {

	/**
	 * Constructor.
	 *
	 * @param IConfig|null $config The app config.
	 * @param DesignSystemService|null $designSystems The token set metadata.
	 * @param TokenValueValidator|null $values The value grammar per token type.
	 * @param OwnTokenService|null $ownTokens The administrator's own tokens.
	 *
	 * @spec openspec/specs/custom-css-overrides/spec.md
	 */
	public function __construct(
		public readonly ?IConfig $config = null,
		public readonly ?DesignSystemService $designSystems = null,
		public readonly ?TokenValueValidator $values = null,
		public readonly ?OwnTokenService $ownTokens = null,
	) {
	}//end __construct()
}//end class
