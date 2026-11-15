<?php

/**
 * The collaborators that find a token set's file.
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
use OCP\App\IAppManager;

/**
 * The token set list, the app directory for a shipped set and the optional runtime store for an uploaded one.
 *
 * @spec openspec/specs/token-sets/spec.md
 */
final class TokenSetFileLocation {

	/**
	 * Constructor.
	 *
	 * @param TokenSetService $tokenSets Says which ids exist, and their names.
	 * @param IAppManager $appManager The app path and version.
	 * @param RuntimeFileStore|null $store Where uploaded sets live; shipped sets come from the release.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function __construct(
		public readonly TokenSetService $tokenSets,
		public readonly IAppManager $appManager,
		public readonly ?RuntimeFileStore $store = null,
	) {
	}//end __construct()

	/**
	 * Whether an id names a token set the list has, in the shape a set id takes.
	 *
	 * @param string $id The token set id, as the route gives it.
	 *
	 * @return bool True for a known set.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function knows(string $id): bool {
		return preg_match('/^[a-z0-9][a-z0-9-]*$/', $id) === 1 && $this->tokenSets->isValidTokenSet(tokenSetId: $id) === true;
	}//end knows()

	/**
	 * A set's display name, as the token set list gives it; its id when the list has no name.
	 *
	 * @param string $id The token set id.
	 *
	 * @return string The name.
	 *
	 * @spec openspec/specs/token-sets/spec.md
	 */
	public function nameOf(string $id): string {
		$name = $id;
		foreach ($this->tokenSets->getAvailableTokenSets() as $set) {
			if (($set['id'] ?? null) === $id) {
				$name = (string)($set['name'] ?? $id);
			}
		}

		return $name;
	}//end nameOf()
}//end class
