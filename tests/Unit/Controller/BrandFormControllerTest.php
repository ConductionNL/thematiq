<?php

/**
 * Tests for BrandFormController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/simple-brand-form/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\BrandFormController;
use OCA\Thematiq\Service\BrandFormService;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCA\Thematiq\Settings\Admin;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * The endpoint over the real store path (CustomTokenSetService writing into a temp app dir)
 * and the real derivation (rules and defaults read from this repo).
 *
 * @spec openspec/specs/simple-brand-form/spec.md
 */
class BrandFormControllerTest extends TestCase {

	/**
	 * The temp app dir the store writes into.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * Stored app config.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * Request params.
	 *
	 * @var array<string, mixed>
	 */
	private array $params = [];

	/**
	 * The uploaded logo, or null.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $logo = null;

	/**
	 * The audit double.
	 *
	 * @var ThemingAuditService&MockObject
	 */
	private ThemingAuditService $audit;

	/**
	 * The store.
	 *
	 * @var CustomTokenSetService
	 */
	private CustomTokenSetService $store;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->appDir = sys_get_temp_dir() . '/thematiq-brand-form-controller-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = (string)$value;
			}
		);
		$this->store = new CustomTokenSetService(
			$appManager,
			$config,
			new CustomTokenSetValidator(),
			new ContrastService(),
			new DarkPaletteService(new ContrastService(), new CssParserService(), $appManager, $this->createMock(LoggerInterface::class))
		);
		$this->audit = $this->createMock(ThemingAuditService::class);
	}//end setUp()

	/**
	 * Remove the temp dir.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->appDir));
	}//end tearDown()

	/**
	 * The controller, reading rules and defaults from the repo.
	 *
	 * @return BrandFormController The controller.
	 */
	private function controller(): BrandFormController {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(fn (string $key, $default = null) => ($this->params[$key] ?? $default));
		$request->method('getUploadedFile')->willReturnCallback(fn (string $key) => $this->logo);
		$repo = $this->createMock(IAppManager::class);
		$repo->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(fn (string $text, $parameters = []) => vsprintf($text, (array)$parameters));

		return new BrandFormController(
			'thematiq',
			$request,
			new BrandFormService(new ContrastService(), new CssParserService()),
			$this->store,
			$repo,
			$this->audit,
			$l
		);
	}//end controller()

	/**
	 * Scenario: an administrator creates a house style from a red and a white, with a logo.
	 *
	 * @return void
	 */
	public function testCreatesACompleteSetWithThemingAndLogo(): void {
		$logoFile = tempnam(sys_get_temp_dir(), 'logo');
		file_put_contents($logoFile, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');
		$this->logo = ['tmp_name' => $logoFile, 'name' => 'logo.svg', 'size' => (int)filesize($logoFile), 'error' => UPLOAD_ERR_OK];
		$this->params = ['name' => 'Gemeente Voorbeeld', 'primary' => '#c8102e', 'background' => '#ffffff'];
		$this->audit->expects($this->once())->method('log')->with('custom_set_uploaded', $this->anything());

		$response = $this->controller()->create();

		$this->assertSame(200, $response->getStatus());
		$data = $response->getData();
		$this->assertSame('custom-gemeente-voorbeeld', $data['id']);
		$this->assertSame('#ffffff', $data['textOnPrimary']);
		$css = (string)file_get_contents($this->appDir . '/css/tokens/custom-gemeente-voorbeeld.css');
		foreach (TokenSetVocabularyAuditService::REQUIRED_TOKENS as $token) {
			$this->assertStringContainsString($token . ':', $css);
		}

		$entry = $this->store->list()[0];
		$this->assertSame('Gemeente Voorbeeld', $entry['name']);
		$this->assertSame('#c8102e', $entry['theming']['primary_color']);
		$this->assertSame('img/logos/custom-gemeente-voorbeeld.svg', $entry['theming']['logo']);
		$this->assertFileExists($this->appDir . '/img/logos/custom-gemeente-voorbeeld.svg');
		$this->assertFileExists($this->appDir . '/css/tokens/dark/custom-gemeente-voorbeeld.css');
	}//end testCreatesACompleteSetWithThemingAndLogo()

	/**
	 * Scenario: the name is already taken.
	 *
	 * @return void
	 */
	public function testTakenNameStoresNothing(): void {
		$this->params = ['name' => 'Gemeente Voorbeeld', 'primary' => '#c8102e', 'background' => '#ffffff'];
		$this->assertSame(200, $this->controller()->create()->getStatus());

		$this->params['primary'] = '#00ff00';
		$response = $this->controller()->create();

		$this->assertSame(409, $response->getStatus());
		$this->assertStringContainsString('#c8102e', (string)file_get_contents($this->appDir . '/css/tokens/custom-gemeente-voorbeeld.css'));
	}//end testTakenNameStoresNothing()

	/**
	 * A colour that is not hex gives 400 and stores nothing.
	 *
	 * @return void
	 */
	public function testInvalidColourIs400(): void {
		$this->params = ['name' => 'Rood', 'primary' => 'red', 'background' => '#ffffff'];

		$this->assertSame(400, $this->controller()->create()->getStatus());
		$this->assertSame([], $this->store->list());
	}//end testInvalidColourIs400()

	/**
	 * A logo that is not an image type gives 400 and stores nothing.
	 *
	 * @return void
	 */
	public function testLogoOfTheWrongTypeIs400(): void {
		$file = tempnam(sys_get_temp_dir(), 'logo');
		file_put_contents($file, '<?php echo 1;');
		$this->logo = ['tmp_name' => $file, 'name' => 'logo.php', 'size' => 13, 'error' => UPLOAD_ERR_OK];
		$this->params = ['name' => 'Rood', 'primary' => '#c8102e', 'background' => '#ffffff'];

		$this->assertSame(400, $this->controller()->create()->getStatus());
		$this->assertSame([], $this->store->list());
	}//end testLogoOfTheWrongTypeIs400()

	/**
	 * Scenario: a mid-tone colour is warned about, not refused (here: primary on background under 3:1).
	 *
	 * @return void
	 */
	public function testLowContrastIsStoredWithTheRatios(): void {
		$this->params = ['name' => 'Geel', 'primary' => '#ffd200', 'background' => '#ffffff'];

		$response = $this->controller()->create();

		$this->assertSame(200, $response->getStatus());
		$this->assertLessThan(3.0, $response->getData()['uiRatio']);
	}//end testLowContrastIsStoredWithTheRatios()

	/**
	 * Scenario: a non-admin cannot create a set. Both endpoints are admin-only.
	 *
	 * @return void
	 */
	public function testEndpointsAreAdminOnly(): void {
		foreach (['create', 'inputs'] as $method) {
			$attributes = (new ReflectionMethod(BrandFormController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(1, $attributes, $method);
			$this->assertSame(Admin::class, $attributes[0]->getArguments()['settings'] ?? $attributes[0]->getArguments()[0]);
		}
	}//end testEndpointsAreAdminOnly()

	/**
	 * The preview inputs are the rules and defaults the server derives with.
	 *
	 * @return void
	 */
	public function testInputsServeRulesAndDefaults(): void {
		$data = $this->controller()->inputs()->getData();

		$this->assertArrayHasKey('--nldesign-color-primary', $data['rules']);
		$this->assertArrayHasKey('--nldesign-color-text', $data['defaults']);
	}//end testInputsServeRulesAndDefaults()
}//end class
