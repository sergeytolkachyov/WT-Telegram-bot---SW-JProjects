<?php
/**
 * @package     System - WT Telegram bot - SW JProjects
 * @version     1.0.0
 * @Author      Sergey Tolkachyov, https://web-tolk.ru
 * @copyright   Copyright (C) 2024 Sergey Tolkachyov
 * @license     GNU/GPL 3
 * @since       1.0.0
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(InstallerScriptInterface::class, new class ($container->get(AdministratorApplication::class)) implements InstallerScriptInterface {
            /**
             * The application object
             *
             * @var  AdministratorApplication
             *
             * @since  1.0.0
             */
            protected AdministratorApplication $app;

            /**
             * The Database object.
             *
             * @var   DatabaseDriver
             *
             * @since  1.0.0
             */
            protected DatabaseDriver $db;

            /**
             * Minimum Joomla version required to install the extension.
             *
             * @var  string
             *
             * @since  1.0.0
             */
            protected string $minimumJoomla = '4.3';

            /**
             * Minimum PHP version required to install the extension.
             *
             * @var  string
             *
             * @since  1.0.0
             */
            protected string $minimumPhp = '8.0';

            /**
             * @var array $providersInstallationMessageQueue
             * @since 2.0.3
             */
            protected $providersInstallationMessageQueue = [];

            /**
             * Constructor.
             *
             * @param AdministratorApplication $app The application object.
             *
             * @since 1.0.0
             */
            public function __construct(AdministratorApplication $app)
            {
                $this->app = $app;
                $this->db = Factory::getContainer()->get('DatabaseDriver');
            }

            /**
             * Function called after the extension is installed.
             *
             * @param InstallerAdapter $adapter The adapter calling this method
             *
             * @return  boolean  True on success
             *
             * @since   1.0.0
             */
            public function install(InstallerAdapter $adapter): bool
            {
                $this->enablePlugin($adapter);

                return true;
            }

            /**
             * Function called after the extension is updated.
             *
             * @param InstallerAdapter $adapter The adapter calling this method
             *
             * @return  boolean  True on success
             *
             * @since   1.0.0
             */
            public function update(InstallerAdapter $adapter): bool
            {
                return true;
            }

            /**
             * Function called after the extension is uninstalled.
             *
             * @param InstallerAdapter $adapter The adapter calling this method
             *
             * @return  boolean  True on success
             *
             * @since   1.0.0
             */
            public function uninstall(InstallerAdapter $adapter): bool
            {

                return true;
            }

            /**
             * Function called before extension installation/update/removal procedure commences.
             *
             * @param string $type The type of change (install or discover_install, update, uninstall)
             * @param InstallerAdapter $adapter The adapter calling this method
             *
             * @return  boolean  True on success
             *
             * @since   1.0.0
             */
            public function preflight(string $type, InstallerAdapter $adapter): bool
            {
                return true;
            }

            /**
             * Function called after extension installation/update/removal procedure commences.
             *
             * @param string $type The type of change (install or discover_install, update, uninstall)
             * @param InstallerAdapter $adapter The adapter calling this method
             *
             * @return  boolean  True on success
             *
             * @since   1.0.0
             */
            public function postflight(string $type, InstallerAdapter $adapter): bool
            {
                $html = "Plugin <code>WT Telegram bot - Content</code> works only with the main plugin <a href=\"https://web-tolk.ru/dev/joomla-plugins/wt-telegram-bot\">WT Telegram bot</a>.";
                $this->app->enqueueMessage($html, 'info');

                return true;
            }


            /**
             * Enable plugin after installation.
             *
             * @param InstallerAdapter $adapter Parent object calling object.
             *
             * @since  1.0.0
             */
            protected function enablePlugin(InstallerAdapter $adapter)
            {
                // Prepare plugin object
                $plugin = new \stdClass();
                $plugin->type = 'plugin';
                $plugin->element = $adapter->getElement();
                $plugin->folder = (string)$adapter->getParent()->manifest->attributes()['group'];
                $plugin->enabled = 1;

                // Update record
                $this->db->updateObject('#__extensions', $plugin, ['type', 'element', 'folder']);
            }


            /**
             * @param $adapter
             *
             * @return bool
             * @throws Exception
             *
             *
             * @since 1.0.0
             */
            protected function installDependencies($adapter, $url)
            {
                // Load installer plugins for assistance if required:
                PluginHelper::importPlugin('installer');

                $package = null;

                // This event allows an input pre-treatment, a custom pre-packing or custom installation.
                // (e.g. from a JSON description).
//                $results = $this->app->triggerEvent('onInstallerBeforeInstallation', array($this, &$package));
//
//                if (in_array(true, $results, true))
//                {
//                    return true;
//                }
//
//                if (in_array(false, $results, true))
//                {
//                    return false;
//                }


                // Download the package at the URL given.
                $p_file = InstallerHelper::downloadPackage($url);

                // Was the package downloaded?
                if (!$p_file) {
                    $this->app->enqueueMessage(Text::_('COM_INSTALLER_MSG_INSTALL_INVALID_URL'), 'error');

                    return false;
                }

                $config = Factory::getContainer()->get('config');
                $tmp_dest = $config->get('tmp_path');

                // Unpack the downloaded package file.
                $package = InstallerHelper::unpack($tmp_dest . '/' . $p_file, true);

                // This event allows a custom installation of the package or a customization of the package:
//                $results = $this->app->triggerEvent('onInstallerBeforeInstaller', array($this, &$package));

//                if (in_array(true, $results, true))
//                {
//                    return true;
//                }

//                if (in_array(false, $results, true))
//                {
//                    InstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);
//
//                    return false;
//                }

                // Get an installer instance.
                $installer = new Installer();

                /*
                 * Check for a Joomla core package.
                 * To do this we need to set the source path to find the manifest (the same first step as JInstaller::install())
                 *
                 * This must be done before the unpacked check because JInstallerHelper::detectType() returns a boolean false since the manifest
                 * can't be found in the expected location.
                 */
                if (is_array($package) && isset($package['dir']) && is_dir($package['dir'])) {
                    $installer->setPath('source', $package['dir']);

                    if (!$installer->findManifest()) {
                        InstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);
                        $this->app->enqueueMessage(Text::sprintf('COM_INSTALLER_INSTALL_ERROR', '.'), 'warning');

                        return false;
                    }
                }

                // Was the package unpacked?
                if (!$package || !$package['type']) {
                    InstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);
                    $this->app->enqueueMessage(Text::_('COM_INSTALLER_UNABLE_TO_FIND_INSTALL_PACKAGE'), 'error');

                    return false;
                }

                // Install the package.
                if (!$installer->install($package['dir'])) {
                    // There was an error installing the package.
                    $msg = Text::sprintf('COM_INSTALLER_INSTALL_ERROR',
                        Text::_('COM_INSTALLER_TYPE_TYPE_' . strtoupper($package['type'])));
                    $result = false;
                    $msgType = 'error';
                } else {
                    // Package installed successfully.
                    $msg = Text::sprintf('COM_INSTALLER_INSTALL_SUCCESS',
                        Text::_('COM_INSTALLER_TYPE_TYPE_' . strtoupper($package['type'])));
                    $result = true;
                    $msgType = 'message';
                }

                // This event allows a custom a post-flight:
//                $this->app->triggerEvent('onInstallerAfterInstaller', array($adapter, &$package, $installer, &$result, &$msg));

                $this->app->enqueueMessage($msg, $msgType);

                // Cleanup the install files.
                if (!is_file($package['packagefile'])) {
                    $package['packagefile'] = $config->get('tmp_path') . '/' . $package['packagefile'];
                }

                InstallerHelper::cleanupInstall($package['packagefile'], $package['extractdir']);

                return $result;
            }

            private function enqueueProvidersInstallationMessage(string $header, string $description, $install_result): void
            {
                $this->providersInstallationMessageQueue[] = [
                    'header' => $header,
                    'description' => $description,
                    'install_result' => $install_result,
                ];
            }

            private function prepareProvidersInstallationMessage(): string
            {
                if (is_array($this->providersInstallationMessageQueue) && count($this->providersInstallationMessageQueue) > 0) {
                    $messages = [
                        "<div class=\"bg-light p-4\"><h4>Supported third-party extensions was found</h4>",
                        "<ul class=\"list-group list-group-flush\">"
                    ];
                    foreach ($this->providersInstallationMessageQueue as $message) {


                        $messages[] = "<li class=\"list-group-item d-flex justify-content-between align-items-center\">
                                <div><h4>" . $message['header'] . "</h4>
                                <p>" . $message['description'] . "</p>
                                </div>
                                " . (($message['install_result'] == true) ? "<span class=\"badge bg-success\">installed</span>" : "<span class=\"badge bg-danger\">not installed</span>") . "                                
                        </li>";
                    }
                    $messages[] = '</ul></div>';

                    $this->providersInstallationMessageQueue = [];
                    return implode('', $messages);
                }

                return '';
            }

        });
    }
};