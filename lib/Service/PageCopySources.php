<?php

/**
 * The collaborators a page's own words are read and handed to the page through.
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
 * @spec openspec/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\L10N\IFactory;

/**
 * The administrator's override in the config, a set's manifest entry, the initial state channel and the language of
 * this request. Shared by the login page and the not-found page copy.
 *
 * @spec openspec/specs/workplace-layout/spec.md
 */
final class PageCopySources {

	/**
	 * Constructor.
	 *
	 * @param IConfig $config Reads the administrator's override.
	 * @param DesignSystemService $designSystemService Reads a set's manifest entry.
	 * @param IInitialState $initialState The initial state channel.
	 * @param IFactory $l10nFactory The language of this request.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	public function __construct(
		public readonly IConfig $config,
		public readonly DesignSystemService $designSystemService,
		public readonly IInitialState $initialState,
		public readonly IFactory $l10nFactory,
	) {
	}//end __construct()
}//end class
