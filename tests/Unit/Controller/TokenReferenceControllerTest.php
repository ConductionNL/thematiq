<?php

/**
 * Unit tests for TokenReferenceController: the reference of any available set, for signed-in users.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/token-reference/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\TokenReferenceController;
use OCA\Thematiq\Service\TokenReferenceService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the reference endpoint.
 */
class TokenReferenceControllerTest extends TestCase {

	/**
	 * Query parameters of the request.
	 *
	 * @var array<string, string>
	 */
	private array $params = [];

	/**
	 * Build the controller with one custom set available.
	 *
	 * @return TokenReferenceController The controller.
	 */
	private function controller(): TokenReferenceController {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(fn (string $key, $default = null) => ($this->params[$key] ?? $default));

		$tokenSets = $this->createMock(TokenSetService::class);
		$tokenSets->method('getAvailableTokenSets')->willReturn(
			[
				['id' => 'amsterdam', 'name' => 'Gemeente Amsterdam', 'theming' => []],
				['id' => 'custom-gemeente-x', 'name' => 'Gemeente X', 'theming' => [], 'custom' => true],
			]
		);

		$reference = $this->createMock(TokenReferenceService::class);
		$reference->method('render')->willReturnCallback(
			fn (string $appPath, array $set, string $format) => $format . ':' . $set['id'] . ':' . $appPath
		);

		$apps = $this->createMock(IAppManager::class);
		$apps->method('getAppPath')->willReturn('/srv/apps/thematiq');

		return new TokenReferenceController('thematiq', $request, $tokenSets, $reference, $apps);
	}//end controller()

	/**
	 * The headers the controller set, read without Response::getHeaders(), which needs a server.
	 *
	 * @param \OCP\AppFramework\Http\Response $response The response.
	 *
	 * @return array<string, string> The headers.
	 */
	private function headers(\OCP\AppFramework\Http\Response $response): array {
		return (new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers'))->getValue($response);
	}//end headers()

	/**
	 * A signed-in user gets the HTML reference of a custom set.
	 *
	 * @return void
	 */
	public function testTheHtmlReferenceOfACustomSet(): void {
		$this->params = ['format' => 'html'];

		$response = $this->controller()->show(id: 'custom-gemeente-x');

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('html:custom-gemeente-x:/srv/apps/thematiq', $response->render());
		$this->assertStringStartsWith('text/html', $this->headers($response)['Content-Type']);
	}//end testTheHtmlReferenceOfACustomSet()

	/**
	 * The Markdown download is an attachment named after the set.
	 *
	 * @return void
	 */
	public function testTheMarkdownDownload(): void {
		$this->params = ['format' => 'md', 'download' => '1'];

		$response = $this->controller()->show(id: 'custom-gemeente-x');

		$this->assertSame('md-plain:custom-gemeente-x:/srv/apps/thematiq', $response->render());
		$this->assertStringStartsWith('text/markdown', $this->headers($response)['Content-Type']);
		$this->assertSame('attachment; filename="custom-gemeente-x-tokens.md"', $this->headers($response)['Content-Disposition']);
	}//end testTheMarkdownDownload()

	/**
	 * Scenario "The downloaded Markdown reads as text": the file is rendered without swatch images.
	 *
	 * @return void
	 */
	public function testTheMarkdownDownloadCarriesNoSwatchImages(): void {
		$this->params = ['format' => 'md', 'download' => '1'];

		$response = $this->controller()->show(id: 'custom-gemeente-x');

		$this->assertStringStartsWith('md-plain:', $response->render());
	}//end testTheMarkdownDownloadCarriesNoSwatchImages()

	/**
	 * An unknown id is a 404 and an unknown format falls back to HTML.
	 *
	 * @return void
	 */
	public function testUnknownIdAndFormat(): void {
		$this->assertSame(404, $this->controller()->show(id: 'nope')->getStatus());

		$this->params = ['format' => 'pdf'];
		$this->assertStringStartsWith('html:', $this->controller()->show(id: 'amsterdam')->render());
	}//end testUnknownIdAndFormat()

	/**
	 * Scenario "The reference is not public": signed-in only, never a public page.
	 *
	 * @return void
	 */
	public function testSignedInOnlyNeverPublic(): void {
		$method = new \ReflectionMethod(TokenReferenceController::class, 'show');

		$this->assertNotEmpty($method->getAttributes(NoAdminRequired::class));
		$this->assertEmpty($method->getAttributes(PublicPage::class));
	}//end testSignedInOnlyNeverPublic()

	/**
	 * The route exists.
	 *
	 * @return void
	 */
	public function testTheRouteIsRegistered(): void {
		$routes = require __DIR__ . '/../../../appinfo/routes.php';
		$found = [];
		foreach ($routes['routes'] as $route) {
			if (str_starts_with($route['name'], 'tokenReference#') === true) {
				$found[$route['name']] = $route['verb'] . ' ' . $route['url'];
			}
		}

		$this->assertSame(['tokenReference#show' => 'GET /api/token-sets/{id}/reference'], $found);
	}//end testTheRouteIsRegistered()
}//end class
