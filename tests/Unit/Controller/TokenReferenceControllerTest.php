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
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(fn (string $key, $default = null) => ($this->params[$key] ?? $default));

		$tokenSets = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSets->method('getAvailableTokenSets')->willReturn(
			[
				['id' => 'amsterdam', 'name' => 'Gemeente Amsterdam', 'theming' => []],
				['id' => 'custom-gemeente-x', 'name' => 'Gemeente X', 'theming' => [], 'custom' => true],
			]
		);

		$reference = $this->createMock(originalClassName: TokenReferenceService::class);
		$reference->method('render')->willReturnCallback(
			fn (string $appPath, array $set, string $format) => $format . ':' . $set['id'] . ':' . $appPath
		);

		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getAppPath')->willReturn('/srv/apps/thematiq');

		return new TokenReferenceController(appName: 'thematiq', request: $request, tokenSets: $tokenSets, reference: $reference, apps: $apps);
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

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 'html:custom-gemeente-x:/srv/apps/thematiq', actual: $response->render());
		$this->assertStringStartsWith(prefix: 'text/html', string: $this->headers(response: $response)['Content-Type']);
	}//end testTheHtmlReferenceOfACustomSet()

	/**
	 * The Markdown download is an attachment named after the set.
	 *
	 * @return void
	 */
	public function testTheMarkdownDownload(): void {
		$this->params = ['format' => 'md', 'download' => '1'];

		$response = $this->controller()->show(id: 'custom-gemeente-x');

		$this->assertSame(expected: 'md-plain:custom-gemeente-x:/srv/apps/thematiq', actual: $response->render());
		$this->assertStringStartsWith(prefix: 'text/markdown', string: $this->headers(response: $response)['Content-Type']);
		$this->assertSame(
			expected: 'attachment; filename="custom-gemeente-x-tokens.md"',
			actual: $this->headers(response: $response)['Content-Disposition']
		);
	}//end testTheMarkdownDownload()

	/**
	 * Scenario "The downloaded Markdown reads as text": the file is rendered without swatch images.
	 *
	 * @return void
	 */
	public function testTheMarkdownDownloadCarriesNoSwatchImages(): void {
		$this->params = ['format' => 'md', 'download' => '1'];

		$response = $this->controller()->show(id: 'custom-gemeente-x');

		$this->assertStringStartsWith(prefix: 'md-plain:', string: $response->render());
	}//end testTheMarkdownDownloadCarriesNoSwatchImages()

	/**
	 * An unknown id is a 404 and an unknown format falls back to HTML.
	 *
	 * @return void
	 */
	public function testUnknownIdAndFormat(): void {
		$this->assertSame(expected: 404, actual: $this->controller()->show(id: 'nope')->getStatus());

		$this->params = ['format' => 'pdf'];
		$this->assertStringStartsWith(prefix: 'html:', string: $this->controller()->show(id: 'amsterdam')->render());
	}//end testUnknownIdAndFormat()

	/**
	 * Scenario "The reference is not public": signed-in only, never a public page.
	 *
	 * @return void
	 */
	public function testSignedInOnlyNeverPublic(): void {
		$method = new \ReflectionMethod(TokenReferenceController::class, 'show');

		$this->assertNotEmpty(actual: $method->getAttributes(NoAdminRequired::class));
		$this->assertEmpty(actual: $method->getAttributes(PublicPage::class));
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

		$this->assertSame(expected: ['tokenReference#show' => 'GET /api/token-sets/{id}/reference'], actual: $found);
	}//end testTheRouteIsRegistered()
}//end class
