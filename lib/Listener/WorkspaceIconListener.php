<?php

/**
 * Puts the workspace's mobile logo, touch icon and the set's favicon on every page.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Listener
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 */

declare(strict_types=1);

namespace OCA\Thematiq\Listener;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\WorkspaceIconService;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Called by ThemeInjectionListener on every page and the login page: a `<link rel="apple-touch-icon">` when a touch icon is set,
 * and a `<style>` that draws the mobile logo below 1024 px when one is set. Nothing when neither
 * is. Fails open: an image that cannot be read never breaks a page.
 *
 * When the active token set declares `theming.favicon`, a `<link rel="icon">` to that image follows
 * core's own. Nextcloud before 35 builds the core favicon from the instance logo even when a favicon
 * is uploaded, so a set whose logo carries a wordmark would otherwise show the wordmark in the tab.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-the-mobile-logo-and-the-touch-icon-reach-every-page
 */
class WorkspaceIconListener implements IEventListener {

	/**
	 * The id of the inline style block.
	 *
	 * @var string
	 */
	public const STYLE_ID = 'thematiq-mobile-logo';

	/**
	 * Constructor.
	 *
	 * @param WorkspaceIconService $icons The stored images.
	 * @param LoggerInterface $logger Logs an image that could not be read.
	 * @param IConfig $config Reads the active token set.
	 * @param DesignSystemService $designSystems Reads the active set's manifest entry.
	 * @param IURLGenerator $urls Builds the favicon's address.
	 */
	public function __construct(
		private readonly WorkspaceIconService $icons,
		private readonly LoggerInterface $logger,
		private readonly IConfig $config,
		private readonly DesignSystemService $designSystems,
		private readonly IURLGenerator $urls,
	) {
	}//end __construct()

	/**
	 * Add the tags for the images that are set.
	 *
	 * @param Event $event The template event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-the-mobile-logo-and-the-touch-icon-reach-every-page
	 */
	public function handle(Event $event): void {
		if (($event instanceof BeforeTemplateRenderedEvent) === false && ($event instanceof BeforeLoginTemplateRenderedEvent) === false) {
			return;
		}

		$favicon = $this->setFavicon();
		if ($favicon !== null) {
			$this->emit(tag: 'link', attributes: ['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => $favicon], text: '');
		}

		try {
			$status = $this->icons->status();
		} catch (Throwable $e) {
			$this->logger->warning('Thematiq: the workspace icons could not be read.', ['exception' => $e]);

			return;
		}

		if ($status['touch-icon'] !== null) {
			$this->emit(tag: 'link', attributes: ['rel' => 'apple-touch-icon', 'sizes' => '180x180', 'href' => $status['touch-icon']], text: '');
		}

		if ($status['mobile-logo'] !== null) {
			$this->emit(tag: 'style', attributes: ['id' => self::STYLE_ID], text: $this->icons->mobileLogoCss(url: $status['mobile-logo']));
		}
	}//end handle()

	/**
	 * The address of the active set's favicon, when it declares an SVG one.
	 *
	 * @return string|null The address, or null when the set declares none.
	 */
	private function setFavicon(): ?string {
		try {
			$setId = $this->config->getAppValue(appName: Application::APP_ID, key: 'token_set', default: 'nextcloud');
			$path = ($this->designSystems->getTokenSetMeta(tokenSetId: $setId)['theming']['favicon'] ?? null);
		} catch (Throwable $e) {
			$this->logger->warning('Thematiq: the set favicon could not be read.', ['exception' => $e]);

			return null;
		}

		if (is_string($path) === false
			|| str_starts_with(haystack: $path, needle: 'img/logos/') === false
			|| str_ends_with(haystack: $path, needle: '.svg') === false
			|| str_contains(haystack: $path, needle: '..') === true
		) {
			return null;
		}

		// IURLGenerator::imagePath() prepends "img/" itself.
		return $this->urls->imagePath(appName: Application::APP_ID, file: substr($path, 4));
	}//end setFavicon()

	/**
	 * Add one tag to the page head. Overridable, so a test can capture it.
	 *
	 * @param string $tag The tag.
	 * @param array<string, string> $attributes Its attributes.
	 * @param string $text Its content.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) \OCP\Util::addHeader() is the Nextcloud API for header injection.
	 *
	 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-the-mobile-logo-and-the-touch-icon-reach-every-page
	 */
	protected function emit(string $tag, array $attributes, string $text): void {
		\OCP\Util::addHeader(tag: $tag, attributes: $attributes, text: $text);
	}//end emit()
}//end class
