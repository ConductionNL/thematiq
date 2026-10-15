<?php

/**
 * A throwaway app directory with one open and two restricted token sets, and
 * an IConfig that keeps what is written to it.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/token-set-licence-gate/specs/token-set-licences/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Support;

use OCA\Thematiq\Service\RestrictedSetGate;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Sets in the fixture: `nextcloud` (no declaration), `open` (declares an open
 * licence), `owned` and `owned-two` (restricted; `owned` ships its licence
 * text), and `sloppy` (a licence block without a `restricted` flag, which
 * counts as restricted).
 */
trait RestrictedSetFixture {

	/**
	 * App config values by key.
	 *
	 * @var array<string, string>
	 */
	protected array $appValues = [];

	/**
	 * User values by "uid/key".
	 *
	 * @var array<string, string>
	 */
	protected array $userValues = [];

	/**
	 * The fixture app directory.
	 *
	 * @var string
	 */
	protected string $appDir = '';

	/**
	 * Build the app directory.
	 *
	 * @return string Its path.
	 */
	protected function makeAppDir(): string {
		$this->appDir = sys_get_temp_dir() . '/thematiq-licence-' . bin2hex(random_bytes(6));
		mkdir($this->appDir . '/css/tokens', 0777, true);
		foreach (['nextcloud', 'open', 'owned', 'owned-two', 'sloppy'] as $id) {
			file_put_contents($this->appDir . '/css/tokens/' . $id . '.css', ":root {\n\t--nldesign-color-primary: #00679e;\n}\n");
		}

		file_put_contents($this->appDir . '/css/tokens/owned.LICENSE.md', "All rights reserved. Use is limited to Gemeente Voorbeeld.\n");
		file_put_contents(
			$this->appDir . '/token-sets.json',
			json_encode(
				[
					['id' => 'nextcloud', 'name' => 'Nextcloud', 'design_system' => 'none'],
					[
						'id' => 'open',
						'name' => 'Open set',
						'licence' => ['id' => 'EUPL-1.2', 'package' => '@example/open-tokens', 'version' => '2.0.0', 'holder' => 'Example', 'restricted' => false],
					],
					[
						'id' => 'owned',
						'name' => 'Gemeente Voorbeeld',
						'licence' => [
							'id' => 'LicenseRef-Voorbeeld',
							'package' => '@example/voorbeeld-design-tokens',
							'version' => '1.0.3',
							'holder' => 'Gemeente Voorbeeld',
							'restricted' => true,
						],
					],
					[
						'id' => 'owned-two',
						'name' => 'Gemeente Tweede',
						'licence' => ['id' => 'LicenseRef-Tweede', 'package' => '@example/tweede', 'version' => '1.0.0', 'holder' => 'Gemeente Tweede', 'restricted' => true],
					],
					['id' => 'sloppy', 'name' => 'Sloppy', 'licence' => ['id' => 'LicenseRef-Sloppy', 'package' => '@example/sloppy', 'version' => '0.1.0']],
				]
			)
		);

		return $this->appDir;
	}//end makeAppDir()

	/**
	 * Remove the app directory.
	 *
	 * @return void
	 */
	protected function removeAppDir(): void {
		if ($this->appDir === '' || is_dir($this->appDir) === false) {
			return;
		}

		foreach ((array)glob($this->appDir . '/css/tokens/*') as $file) {
			unlink((string)$file);
		}

		rmdir($this->appDir . '/css/tokens');
		rmdir($this->appDir . '/css');
		unlink($this->appDir . '/token-sets.json');
		rmdir($this->appDir);
	}//end removeAppDir()

	/**
	 * An IConfig that keeps app and user values in memory.
	 *
	 * @return IConfig The config.
	 */
	protected function memoryConfig(): IConfig {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => ($this->appValues[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, string $value): void {
				$this->appValues[$key] = $value;
			}
		);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $uid, string $app, string $key, mixed $default = ''): string => ($this->userValues[$uid . '/' . $key] ?? (string)$default)
		);
		$config->method('setUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, mixed $value): void {
				$this->userValues[$uid . '/' . $key] = (string)$value;
			}
		);
		$config->method('deleteUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key): void {
				unset($this->userValues[$uid . '/' . $key]);
			}
		);

		return $config;
	}//end memoryConfig()

	/**
	 * The real TokenSetService over the fixture directory.
	 *
	 * @param IConfig $config The config.
	 *
	 * @return TokenSetService The service.
	 */
	protected function fixtureTokenSets(IConfig $config): TokenSetService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->createMock(ICache::class));
		$cacheFactory->method('createLocal')->willReturn($this->createMock(ICache::class));

		return new TokenSetService(
			$appManager,
			$config,
			$this->createMock(LoggerInterface::class),
			$this->createMock(ShippedTokenSetAuditService::class),
			$cacheFactory,
			$this->createMock(TokenSetVocabularyAuditService::class)
		);
	}//end fixtureTokenSets()

	/**
	 * Store a confirmation for a set, the way an administrator's confirmation does.
	 *
	 * @param string $tokenSetId The set id.
	 *
	 * @return void
	 */
	protected function confirmSet(string $tokenSetId): void {
		$all = json_decode(($this->appValues[RestrictedSetGate::CONFIG_KEY] ?? '{}'), true);
		$all[$tokenSetId] = ['uid' => 'admin', 'at' => '2026-10-12T20:00:00+00:00'];
		$this->appValues[RestrictedSetGate::CONFIG_KEY] = (string)json_encode($all);
	}//end confirmSet()
}//end trait
