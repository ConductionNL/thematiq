<?php
/**
 * Pins the WCAG cache prefix shared by Capabilities and TokenSetService.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Service
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Capabilities;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * `Capabilities` and `TokenSetService` must resolve the WCAG level through ONE
 * cache entry per set id — the public catalogue and the active-theme capability
 * are answering the same question, and computing it twice is both wasted work
 * and a chance for the two surfaces to disagree inside one TTL window.
 *
 * Both classes said so in a comment ("deliberately the same prefix") and nothing
 * checked it. The app-id rename then moved `Capabilities` to
 * `thematiq_wcag_level` and left `TokenSetService` on `nldesign_wcag_level`, so
 * the sharing quietly stopped. Nothing failed: two prefixes are two perfectly
 * valid caches, and a cache miss is indistinguishable from a cold cache.
 *
 * This test is the enforcement that comment assumed existed. It asserts the two
 * agree WITHOUT naming the value, so a future rename that moves both together
 * still passes and only a rename that moves one fails.
 *
 * @spec exclude No canonical spec covers this app's internal cache topology.
 *  The requirement is a code-level invariant between two classes, not a
 *  described behaviour, and pointing it at a theming spec would claim
 *  conformance to something that says nothing about caching.
 */
class WcagCachePrefixPairingTest extends TestCase {

	/**
	 * Capture every prefix handed to createDistributed().
	 *
	 * @var array<int, string>
	 */
	private array $prefixes = [];

	/**
	 * A cache factory that records the prefix instead of building a cache.
	 *
	 * @return ICacheFactory
	 */
	private function recordingFactory(): ICacheFactory {
		$factory = $this->createMock(ICacheFactory::class);
		$factory->method('createDistributed')
			->willReturnCallback(
				function (string $prefix): ICache {
					$this->prefixes[] = $prefix;
					return $this->createMock(ICache::class);
				}
			);
		return $factory;
	}

	/**
	 * The two classes must request the same distributed-cache prefix.
	 *
	 * @return void
	 */
	public function testBothClassesShareOneWcagCachePrefix(): void {
		$audit = $this->createMock(ShippedTokenSetAuditService::class);

		$tokenSetService = new TokenSetService(
			$this->createMock(IAppManager::class),
			$this->createMock(IConfig::class),
			$this->createMock(LoggerInterface::class),
			$audit,
			$this->recordingFactory(),
			$this->createMock(TokenSetVocabularyAuditService::class)
		);
		$this->assertCount(1, $this->prefixes, 'TokenSetService must create exactly one distributed cache');
		$tokenSetPrefix = $this->prefixes[0];

		$this->prefixes = [];
		new Capabilities(
			$this->createMock(IConfig::class),
			$this->createMock(IAppManager::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(DesignSystemService::class),
			$tokenSetService,
			$audit,
			$this->recordingFactory()
		);
		$this->assertCount(1, $this->prefixes, 'Capabilities must create exactly one distributed cache');
		$capabilitiesPrefix = $this->prefixes[0];

		$this->assertSame(
			$tokenSetPrefix,
			$capabilitiesPrefix,
			'Capabilities and TokenSetService must share one WCAG cache prefix. '
			. 'They are answering the same question for the same set id, and a split '
			. 'prefix computes it twice while looking exactly like a cold cache. '
			. 'If you renamed one, rename the other.'
		);
	}//end testBothClassesShareOneWcagCachePrefix()

	/**
	 * The shared prefix must carry the current app id, not a pre-rename one.
	 *
	 * Kept separate from the pairing assertion on purpose: two classes agreeing
	 * on a STALE prefix is a different defect from two classes disagreeing, and
	 * a single test that checked both would not say which had happened.
	 *
	 * @return void
	 */
	public function testTheSharedPrefixCarriesTheCurrentAppId(): void {
		new TokenSetService(
			$this->createMock(IAppManager::class),
			$this->createMock(IConfig::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(ShippedTokenSetAuditService::class),
			$this->recordingFactory(),
			$this->createMock(TokenSetVocabularyAuditService::class)
		);

		$this->assertStringStartsWith(
			'thematiq',
			$this->prefixes[0],
			'The WCAG cache prefix must carry the current app id'
		);
	}//end testTheSharedPrefixCarriesTheCurrentAppId()
}//end class
