<?php

/**
 * Static-analysis stubs for the server's theming app (`OCA\Theming`).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * lib/Service/ThemingService.php, lib/Service/BrandingCaptureService.php and
 * lib/Controller/SettingsController.php write core's theming settings through
 * the theming app's own ImageManager and ThemingDefaults, and read the stock
 * colours from its BackgroundService. Those classes ship in
 * `apps/theming/lib/` of the server, not in the `nextcloud/ocp` package the
 * analysers see, so phpstan carried 16 baseline entries and psalm an
 * UndefinedClass suppression for them. A suppression hides a typo in a method
 * name as readily as it hides the missing class; a declaration-only stub makes
 * the analysers CHECK every call against the real signature instead.
 *
 * Wired into analysis ONLY (phpstan.neon `scanDirectories`, psalm.xml
 * `<stubs>`) and never autoloaded at runtime: composer maps only
 * `OCA\Thematiq\` to lib/, so the server's real classes always execute.
 *
 * Only the members this app calls are declared. Signatures mirror
 * apps/theming/lib/{ImageManager,ThemingDefaults,Service/BackgroundService}.php
 * on server master (identical on stable32 to stable35 for these members);
 * keep them in sync if the server changes them.
 */

declare(strict_types=1);

// phpcs:disable

namespace OCA\Theming {
    use OCP\Files\SimpleFS\ISimpleFile;

    class ImageManager
    {
        /**
         * @param string $key The image key.
         *
         * @return string The image url.
         */
        public function getImageUrl(string $key): string
        {
            return '';
        }

        /**
         * @param string $key    The image key.
         * @param bool   $useSvg Prefer the svg variant.
         *
         * @return ISimpleFile The stored image.
         *
         * @throws \OCP\Files\NotFoundException
         * @throws \OCP\Files\NotPermittedException
         */
        public function getImage(string $key, bool $useSvg = true): ISimpleFile
        {
            throw new \OCP\Files\NotFoundException();
        }

        /**
         * @param string $key The image key.
         *
         * @return bool Whether a custom image is stored.
         */
        public function hasImage(string $key): bool
        {
            return false;
        }

        /**
         * @param string $key     The image key.
         * @param string $tmpFile Path of the uploaded file.
         *
         * @return string The detected mime type.
         */
        public function updateImage(string $key, string $tmpFile): string
        {
            return '';
        }
    }

    class ThemingDefaults
    {
        /**
         * @param string $setting The setting key.
         * @param string $value   The new value.
         *
         * @return void
         */
        public function set($setting, $value): void
        {
        }

        /**
         * @param string $setting The setting to revert.
         *
         * @return string The default value.
         */
        public function undo($setting): string
        {
            return '';
        }
    }
}

namespace OCA\Theming\Service {
    class BackgroundService
    {
        public const DEFAULT_COLOR = '#00679e';
        public const DEFAULT_BACKGROUND_COLOR = '#00679e';
    }
}
