<?php

/**
 * Binds every Kernel contract to the service that implements it.
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
 * @spec exclude Composition root wiring, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Kernel\Contracts\ComplianceReportServiceInterface;
use OCA\Thematiq\Kernel\Contracts\ConfigBundleServiceInterface;
use OCA\Thematiq\Kernel\Contracts\ContrastServiceInterface;
use OCA\Thematiq\Kernel\Contracts\DarkPaletteServiceInterface;
use OCA\Thematiq\Kernel\Contracts\FontServiceInterface;
use OCA\Thematiq\Kernel\Contracts\ShippedTokenSetAuditServiceInterface;
use OCA\Thematiq\Kernel\Contracts\ThemingAuditServiceInterface;
use OCA\Thematiq\Kernel\Contracts\TokenReferenceServiceInterface;
use OCA\Thematiq\Kernel\Contracts\UpstreamFreshnessServiceInterface;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * The aliases from each contract in lib/Kernel/Contracts to its implementation.
 *
 * @spec exclude Composition root wiring, no functional requirement.
 */
final class ContractBindings {

	/**
	 * Alias every contract to its implementation.
	 *
	 * @param IRegistrationContext $context Registration context
	 *
	 * @return void
	 *
	 * @spec exclude Composition root wiring, no functional requirement.
	 */
	public function register(IRegistrationContext $context): void {
		$context->registerServiceAlias(ComplianceReportServiceInterface::class, ComplianceReportService::class);
		$context->registerServiceAlias(ConfigBundleServiceInterface::class, ConfigBundleService::class);
		$context->registerServiceAlias(ContrastServiceInterface::class, ContrastService::class);
		$context->registerServiceAlias(DarkPaletteServiceInterface::class, DarkPaletteService::class);
		$context->registerServiceAlias(FontServiceInterface::class, FontService::class);
		$context->registerServiceAlias(ShippedTokenSetAuditServiceInterface::class, ShippedTokenSetAuditService::class);
		$context->registerServiceAlias(ThemingAuditServiceInterface::class, ThemingAuditService::class);
		$context->registerServiceAlias(TokenReferenceServiceInterface::class, TokenReferenceService::class);
		$context->registerServiceAlias(UpstreamFreshnessServiceInterface::class, UpstreamFreshnessService::class);
	}//end register()
}//end class
