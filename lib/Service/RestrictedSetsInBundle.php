<?php

/**
 * Thematiq Restricted Sets In Bundle.
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
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-every-path-that-applies-a-set-asks-the-same-gate
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\Exception\RestrictedSetNotConfirmedException;
use OCP\IConfig;

/**
 * Refuses a configuration bundle that would apply a restricted token set
 * nobody confirmed on this server: as the active set, in a planned switch, or
 * as an app's brand. A confirmation does not travel in a bundle; it is given
 * per server. The set that is active here now may stay.
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-every-path-that-applies-a-set-asks-the-same-gate
 */
class RestrictedSetsInBundle {

	/**
	 * The import errors for the restricted sets a bundle names.
	 *
	 * @param array<string, mixed> $bundle The bundle.
	 * @param TokenSetService $tokenSets Hands out the licence gate.
	 * @param IConfig $config Names the set that is active here.
	 *
	 * @return list<array{section: string, message: string}> One error per refused set; empty when none is refused.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-every-path-that-applies-a-set-asks-the-same-gate
	 */
	public function errors(array $bundle, TokenSetService $tokenSets, IConfig $config): array {
		$active = (string)$config->getAppValue(Application::APP_ID, ActiveTokenSetService::CONFIG_KEY, ActiveTokenSetService::DEFAULT_SET);
		$gate = $tokenSets->licenceGate();
		$errors = [];
		foreach ($this->named(bundle: $bundle) as $id) {
			if ($gate->refuses(tokenSetId: $id, alreadyApplied: $active) === false) {
				continue;
			}

			$errors[] = [
				'section' => 'config',
				'message' => 'Token set "' . $id . '" is restricted to its owner (' . RestrictedSetNotConfirmedException::ERROR
					. '). Confirm on this server that your organisation may use it, then import again.',
			];
		}

		return $errors;
	}//end errors()

	/**
	 * Every set id a bundle names in a place that applies it.
	 *
	 * @param array<string, mixed> $bundle The bundle.
	 *
	 * @return list<string> The distinct ids.
	 */
	private function named(array $bundle): array {
		$config = ($bundle['config'] ?? null);
		if (is_array($config) === false) {
			$config = [];
		}

		$named = [($config['tokenSet'] ?? null)];
		foreach ((array)($config['scheduledSwitches'] ?? []) as $switch) {
			$named[] = (((array)$switch)['tokenSet'] ?? null);
		}

		foreach ((array)($bundle['appBrands'] ?? []) as $brand) {
			$named[] = (((array)$brand)['tokenSet'] ?? null);
		}

		return array_values(array_unique(array_filter($named, 'is_string')));
	}//end named()
}//end class
