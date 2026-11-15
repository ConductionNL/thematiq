<?php

/**
 * Thematiq Provides Licence Gate.
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

use OCP\IConfig;

/**
 * Hands out the licence gate from {@see TokenSetService}. Every service that
 * applies a set holds that service, so each of them asks here before it
 * writes, and none of them can be built without the gate.
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-every-path-that-applies-a-set-asks-the-same-gate
 */
trait ProvidesLicenceGate {

	/**
	 * The licence gate, built on first use.
	 *
	 * @var RestrictedSetGate|null
	 */
	private ?RestrictedSetGate $licenceGate = null;

	/**
	 * The app directory the manifest and the licence files are read from.
	 *
	 * @return string The path.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-every-path-that-applies-a-set-asks-the-same-gate
	 */
	abstract public function getAppPath(): string;

	/**
	 * The config service holding the confirmations.
	 *
	 * @return IConfig The config.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-every-path-that-applies-a-set-asks-the-same-gate
	 */
	abstract protected function licenceConfig(): IConfig;

	/**
	 * The gate that knows each shipped set's licence and whether a restricted
	 * set is confirmed.
	 *
	 * @return RestrictedSetGate The gate, one per request.
	 *
	 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md#requirement-every-path-that-applies-a-set-asks-the-same-gate
	 */
	public function licenceGate(): RestrictedSetGate {
		if ($this->licenceGate === null) {
			$this->licenceGate = new RestrictedSetGate(config: $this->licenceConfig(), appPath: $this->getAppPath());
		}

		return $this->licenceGate;
	}//end licenceGate()
}//end trait
