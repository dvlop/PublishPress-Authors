<?php
/**
 * @package PublishPress Authors
 * @author  PublishPress
 *
 * Copyright (C) 2018 PublishPress
 *
 * This file is part of PublishPress Authors
 *
 * PublishPress Authors is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */

use MultipleAuthors\Classes\Legacy\Module;
use MultipleAuthors\Factory;
use PublishPressAuthors\ElementorIntegration\Modules\Posts\Skins\PostsSkinCards;
use PublishPressAuthors\ElementorIntegration\Modules\Posts\Skins\PostsSkinClassic;
use PublishPressAuthors\ElementorIntegration\Modules\Posts\Skins\PostsSkinFullContent;
use PublishPressAuthors\ElementorIntegration\Modules\ThemeBuilder\Skins\ArchivePostsSkinCards;
use PublishPressAuthors\ElementorIntegration\Modules\ThemeBuilder\Skins\ArchivePostsSkinClassic;
use PublishPressAuthors\ElementorIntegration\Modules\ThemeBuilder\Skins\ArchivePostsSkinFullContent;

if (!class_exists('MA_Elementor_Integration')) {
    /**
     * class MA_Elementor_Integration
     */
    class MA_Elementor_Integration extends Module
    {
        public $module_name = 'elementor_integration';

        /**
         * Instance for the module
         *
         * @var stdClass
         */
        public $module;

        /**
         * Construct the MA_Elementor_Integration class
         */
        public function __construct()
        {
            $this->module_url = $this->get_module_url(__FILE__);

            // Register the module with PublishPress
            $args = [
                'title'             => __('Elementor Integration', 'publishpress-authors'),
                'short_description' => __(
                    'Add compatibility with the Elementor and Elementor Pro page builder',
                    'publishpress-authors'
                ),
                'module_url'        => $this->module_url,
                'icon_class'        => 'dashicons dashicons-feedback',
                'slug'              => 'elementor-integration',
                'default_options'   => [
                    'enabled' => 'on',
                ],
                'options_page'      => false,
                'autoload'          => true,
            ];

            // Apply a filter to the default options
            $args['default_options'] = apply_filters(
                'pp_elementor_integration_default_options',
                $args['default_options']
            );

            $this->module = Factory::getLegacyPlugin()->register_module($this->module_name, $args);

            parent::__construct();
        }

        private function isElementorInstalled()
        {
            // For now we only integrate with the Pro version because the Free one doesn't have the posts modules.

            if (!defined('ELEMENTOR_PRO_VERSION')) {
                return false;
            }

            $minRequiredVersion = '2.9.3';

            if (version_compare(ELEMENTOR_PRO_VERSION, $minRequiredVersion, '<')) {
                error_log(
                    sprintf(
                        '[PublishPress Authors] Elementor module is disabled because it requires Elementor Pro %s and we found %s',
                        $minRequiredVersion,
                        ELEMENTOR_PRO_VERSION
                    )
                );

                return false;
            }

            $abort = false;

            $requiredClasses = [
                '\\ElementorPro\\Modules\\Posts\\Skins\\Skin_Base',
                '\\ElementorPro\\Modules\\Posts\\Skins\\Skin_Cards',
                '\\ElementorPro\\Modules\\Posts\\Skins\\Skin_Classic',
                '\\ElementorPro\\Modules\\Posts\\Skins\\Skin_Full_Content',
                '\\ElementorPro\\Modules\\ThemeBuilder\\Skins\\Posts_Archive_Skin_Cards',
                '\\ElementorPro\\Modules\\ThemeBuilder\\Skins\\Posts_Archive_Skin_Classic',
                '\\ElementorPro\\Modules\\ThemeBuilder\\Skins\\Posts_Archive_Skin_Full_Content',
            ];

            foreach ($requiredClasses as $className) {
                if (!class_exists($className)) {
                    error_log(
                        sprintf(
                            '[PublishPress Authors] Elementor module did not find the class %s',
                            $className
                        )
                    );
                    $abort = true;
                }
            }

            $requiredTraits = [
                '\\ElementorPro\\Modules\\ThemeBuilder\\Skins\\Posts_Archive_Skin_Base',
                '\\ElementorPro\\Modules\\Posts\\Skins\\Skin_Content_Base',
            ];

            foreach ($requiredTraits as $traitName) {
                if (!trait_exists($traitName)) {
                    error_log(
                        sprintf(
                            '[PublishPress Authors] Elementor module did not find the trait %s',
                            $traitName
                        )
                    );
                    $abort = true;
                }
            }

            if ($abort) {
                return false;
            }


            return true;
        }

        /**
         * Initialize the module. Conditionally loads if the module is enabled
         */
        public function init()
        {
            if (!$this->isElementorInstalled()) {
                return;
            }

            add_action('elementor/widget/posts/skins_init', [$this, 'add_posts_skins'], 10, 2);
            add_action('elementor/widget/archive-posts/skins_init', [$this, 'add_archive_posts_skins'], 10, 2);
            add_filter( 'elementor/theme/posts_archive/query_posts/query_vars', [$this, 'filter_posts_archive_query_vars'], 15);
        }

        /**
         * @param $widget
         */
        public function add_posts_skins($widget)
        {
            $classes = [
                '\\PublishPressAuthors\\ElementorIntegration\\Modules\\Posts\\Skins\\PostsSkinCards'       =>
                    __DIR__ . '/Modules/Posts/Skins/PostsSkinCards.php',
                '\\PublishPressAuthors\\ElementorIntegration\\Modules\\Posts\\Skins\\PostsSkinClassic'     =>
                    __DIR__ . '/Modules/Posts/Skins/PostsSkinClassic.php',
                '\\PublishPressAuthors\\ElementorIntegration\\Modules\\Posts\\Skins\\PostsSkinFullContent' =>
                    __DIR__ . '/Modules/Posts/Skins/PostsSkinFullContent.php',
            ];

            foreach ($classes as $className => $path) {
                if (!class_exists($className)) {
                    require_once $path;
                }
            }

            $widget->add_skin(new PostsSkinCards($widget));
            $widget->add_skin(new PostsSkinClassic($widget));
            $widget->add_skin(new PostsSkinFullContent($widget));
        }

        /**
         * @param $widget
         */
        public function add_archive_posts_skins($widget)
        {
            $classes = [
                '\\PublishPressAuthors\\ElementorIntegration\\Modules\\Posts\\Skins\\PostsSkinCards'                     =>
                    __DIR__ . '/Modules/Posts/Skins/PostsSkinCards.php',
                '\\PublishPressAuthors\\ElementorIntegration\\Modules\\Posts\\Skins\\PostsSkinClassic'                   =>
                    __DIR__ . '/Modules/Posts/Skins/PostsSkinClassic.php',
                '\\PublishPressAuthors\\ElementorIntegration\\Modules\\Posts\\Skins\\PostsSkinFullContent'               =>
                    __DIR__ . '/Modules/Posts/Skins/PostsSkinFullContent.php',
                '\\PublishPressAuthors\\ElementorIntegration\\Modules\\ThemeBuilder\\Skins\\ArchivePostsSkinCards'       =>
                    __DIR__ . '/Modules/ThemeBuilder/Skins/ArchivePostsSkinCards.php',
                '\\PublishPressAuthors\\ElementorIntegration\\Modules\\ThemeBuilder\\Skins\\ArchivePostsSkinClassic'     =>
                    __DIR__ . '/Modules/ThemeBuilder/Skins/ArchivePostsSkinClassic.php',
                '\\PublishPressAuthors\\ElementorIntegration\\Modules\\ThemeBuilder\\Skins\\ArchivePostsSkinFullContent' =>
                    __DIR__ . '/Modules/ThemeBuilder/Skins/ArchivePostsSkinFullContent.php',
            ];

            foreach ($classes as $className => $path) {
                if (!class_exists($className)) {
                    require_once $path;
                }
            }

            $widget->add_skin(new ArchivePostsSkinCards
                              ($widget));
            $widget->add_skin(new ArchivePostsSkinClassic($widget));
            $widget->add_skin(new ArchivePostsSkinFullContent($widget));
        }

        public function filter_posts_archive_query_vars($query_vars)
        {
            return $query_vars;
        }
    }
}
