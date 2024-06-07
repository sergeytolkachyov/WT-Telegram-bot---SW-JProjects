<?php
/**
 * @package     System - WT Telegram bot - SW JProjects
 * @version     1.0.0
 * @Author      Sergey Tolkachyov, https://web-tolk.ru
 * @copyright   Copyright (C) 2024 Sergey Tolkachyov
 * @license     GNU/GPL 3
 * @since       1.0.0
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\System\Wttelegrambotswjprojects\Extension\Wttelegrambotswjprojects;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param Container $container The DI container.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $subject = $container->get(DispatcherInterface::class);
                $config = (array)PluginHelper::getPlugin('system', 'wttelegrambotswjprojects');
                $plugin = new Wttelegrambotswjprojects($subject, $config);
                $plugin->setApplication(\Joomla\CMS\Factory::getApplication());
                return $plugin;
            }
        );
    }
};