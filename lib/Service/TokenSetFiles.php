<?php

/**
 * The collaborators the token set list finds set files through.
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
 * @spec openspec/specs/token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;
use OCP\App\IAppManager;

/**
 * The app directory for the shipped sets, the runtime store for the uploaded ones, and the reader that lists and checks a set's file in either.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
final class TokenSetFiles {

	/**
	 * Constructor.
	 *
	 * @param IAppManager $appManager The app manager for resolving paths.
	 * @param RuntimeFileStore|null $store Where uploaded sets are kept (app data).
	 * @param SetFileReader $files Lists and checks a set's file in the release or the store.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function __construct(
		public readonly IAppManager $appManager,
		public readonly ?RuntimeFileStore $store = null,
		public readonly SetFileReader $files = new SetFileReader(),
	) {
	}//end __construct()
}//end class
