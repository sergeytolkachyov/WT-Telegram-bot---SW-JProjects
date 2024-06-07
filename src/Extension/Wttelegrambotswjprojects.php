<?php
/**
 * @package     System - WT Telegram bot - SW JProjects
 * @version     1.0.0
 * @Author      Sergey Tolkachyov, https://web-tolk.ru
 * @copyright   Copyright (C) 2024 Sergey Tolkachyov
 * @license     GNU/GPL 3
 * @since       1.0.0
 */

namespace Joomla\Plugin\System\Wttelegrambotswjprojects\Extension;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Toolbar\Button\BasicButton;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;


final class Wttelegrambotswjprojects extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Карта вызовов моделей для разных view
	 * @var array|string[]
	 * @since 1.0.0
	 */
	protected static array $model_map = [
		'versions'      => 'Version',
		'version'       => 'Version',
		'projects'      => 'Project',
		'project'       => 'Project',
		'documentation' => 'Document',
		'document'      => 'Document'
	];
	protected $autoloadLanguage = true;

	public function __construct(DispatcherInterface $dispatcher, array $config)
	{

		\JLoader::register('SWJProjectsHelperRoute', JPATH_SITE . '/components/com_swjprojects/helpers/route.php');
		\JLoader::register('SWJProjectsHelperImages', JPATH_SITE . '/components/com_swjprojects/helpers/images.php');
		\JLoader::register('SWJProjectsHelperTranslation', JPATH_ADMINISTRATOR . '/components/com_swjprojects/helpers/translation.php');

		parent::__construct($dispatcher, $config);
	}

	/**
	 *
	 * @return array
	 *
	 * @throws \Exception
	 * @since 4.1.0
	 *
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onAfterDispatch'                => 'onAfterDispatch',
			'onAjaxWttelegrambotswjprojects' => 'onAjaxWttelegrambotswjprojects'
		];

	}


	/**
	 * Add a button to Joomla Toolbar for sending to Telegram via ajax
	 *
	 * @since 1.0.0
	 */
	public function onAfterDispatch(): void
	{
		if (!$this->getApplication()->isClient('administrator'))
		{
			return;
		}

		if ($this->getApplication()->getInput()->get('option') !== 'com_swjprojects')
		{
			return;
		}

		$allowed_view = ['version', 'project', 'document', 'versions', 'projects', 'documentation'];
		if (!in_array($this->getApplication()->getInput()->get('view'), $allowed_view))
		{
			return;
		}

		$toolbar = $this->getApplication()->getDocument()->getToolbar('toolbar');
		$lang    = $this->getApplication()->getLanguage('site');
		$tag     = $lang->getTag();
		$this->getApplication()->getLanguage()->load('plg_system_wttelegrambotswjprojects', JPATH_ADMINISTRATOR, $tag, true);

		if (isset(Factory::$language))
		{
			Factory::getLanguage()->load('plg_system_wttelegrambotswjprojects', JPATH_ADMINISTRATOR, $tag, true);
		}

		$button = (new BasicButton('send-to-telegram'))
			->text(Text::_('PLG_WTTELEGRAMBOTSWJPROJECTS_BUTTON_LABEL'))
			->icon('fa-brands fa-telegram')
			->onclick("window.Wttelegrambotswjprojects()");
		$toolbar->appendButton($button);

		/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
		$wa = $this->getApplication()->getDocument()->getWebAssetManager();
		$wa->registerAndUseScript('wttelegrambot.swjprojects.ajax.send', 'plg_system_wttelegrambotswjprojects/ajaxsend.js');
	}

	/**
	 * Main ajax job. Send to Telegram via ajax.
	 * Send the array $article_ids here
	 *
	 * @param   Event  $event
	 *
	 *
	 * @since 1.0.0
	 */
	public function onAjaxWttelegrambotswjprojects(Event $event): void
	{
		if (!$this->getApplication()->isClient('administrator'))
		{
			return;
		}

		$data     = $this->getApplication()->getInput()->json->getArray();
		$item_ids = $data['item_ids'];
		$type     = self::$model_map[$data['type']];
		if (!count($item_ids))
		{
			$event->setArgument('result', false);

			return;
		}


		$sent_items = [];
		foreach ($item_ids as $item_id)
		{
			$language = $this->getApplication()->getLanguage();
			$language->load('com_swjprojects', JPATH_SITE, $language->getTag(), true);

			BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_swjprojects/models');
			Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_swjprojects/tables');

			$model = BaseDatabaseModel::getInstance($type, 'SWJProjectsModel', ['ignore_request' => true]);
			$model->setState('params', (new Registry()));
			$item = $model->getItem($item_id);

			if ($item->state)
			{

				if ($type != 'Project')
				{
					$projectModel = BaseDatabaseModel::getInstance('Project', 'SWJProjectsModel', ['ignore_request' => true]);
					$projectModel->setState('params', (new Registry()));
					$item->project = $projectModel->getItem($item->project_id);
				}
				$this->prepareItem($item, $type);
				$sent_items[] = $item->id;
			}
			else
			{
				$this->getApplication()->enqueueMessage(ucfirst($type) . ' ' . $item->title . ' is not published. It was not sent to Telegram', 'danger');
			}
		}

		$event->setArgument('result', ['sent_items' => $sent_items]);

	}

	/**
	 * @param $item object SW JProjects item
	 * @param $type string Project, version or document
	 *
	 *
	 * @since version
	 */
	public function prepareItem($item, $type): void
	{

		$default_language = $this->params->get('language_for_message', 'ru-RU');
		$title            = '';
		$text             = '';
		$images           = [];
		$link             = '';

		$this->getApplication()->getLanguage()->load('com_swjprojects', JPATH_SITE, $default_language, true);
		if (isset(Factory::$language))
		{
			Factory::getLanguage()->load('com_swjprojects', JPATH_SITE, $default_language, true);
		}

		// Process message title
		if ($type == 'Project')
		{
			if (array_key_exists($default_language, $item->translates) && !empty($item->translates[$default_language]))
			{
				$title = $item->translates[$default_language]->title;
				$text  = (!empty($item->translates[$default_language]->introtext) ? $item->translates[$default_language]->introtext : $item->translates[$default_language]->fulltext);

			}
			else
			{
				$title = $item->translates[0]->title;
				$text  = (!empty($item->translates[0]->introtext) ? $item->translates[0]->introtext : $item->translates[0]->fulltext);
			}

		}
		elseif ($type == 'Version')
		{
			if (array_key_exists($default_language, $item->project->translates) && !empty($item->project->translates[$default_language]))
			{
				$hotfifx   = (property_exists($item, 'hotfix') && !empty($item->hotfix)) ? '.' . $item->hotfix : '';
				$title     = $item->project->translates[$default_language]->title . ' v.' . $item->major . '.' . $item->minor . '.' . $item->patch . $hotfifx;
				$text      = $item->project->translates[$default_language]->introtext;
				$changelog = $item->translates[$default_language]->changelog;

			}
			else
			{
				$title     = $item->translates[0]->title;
				$text      = $item->translates[0]->introtext;
				$changelog = $item->translates[0]->changelog;
			}

			if (is_array($changelog) && !empty($changelog))
			{
				$text .= PHP_EOL . '<b>'.Text::_('COM_SWJPROJECTS_VERSION_CHANGELOG').'</b>';
				foreach ($changelog as $changelog_data)
				{
					$text .=  PHP_EOL.'- ';
					if (!empty($changelog_data['title']) || !empty($changelog_data['description']))
					{
						if (!empty($changelog_data['title']))
						{
							$text .= '<b>' . $changelog_data['title'] . '</b>. ';
						}
						if (!empty($changelog_data['description']))
						{
							$text .= $changelog_data['description'];
						}
					}
				}
				$text .= PHP_EOL;
			}


		}
		elseif ($type == 'Document')
		{
			if (array_key_exists($default_language, $item->translates) && !empty($item->translates[$default_language]))
			{
				$title =  Text::sprintf('COM_SWJPROJECTS_DOCUMENTATION_TITLE', $item->project->translates[$default_language]->title).': '.$item->translates[$default_language]->title;
				$text  = (!empty($item->translates[$default_language]->introtext) ? $item->translates[$default_language]->introtext : $item->translates[$default_language]->fulltext);

			}
			else
			{
				$title =  Text::sprintf('COM_SWJPROJECTS_DOCUMENTATION_TITLE', $item->project->translates[0]->title).': '.$item->translates[0]->title;
				$text  = (!empty($item->translates[0]->introtext) ? $item->translates[0]->introtext : $item->translates[0]->fulltext);
			}


		}

		$message  = '<b>' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</b>' . PHP_EOL;
		$message  .= $text;
		$message  = HTMLHelper::_('content.prepare', $message, '', 'com_swjprojects.' . $type);
		$linkMode = $this->getApplication()->get('force_ssl', 0) >= 1 ? Route::TLS_FORCE : Route::TLS_IGNORE;



		\JLoader::register('SWJProjectsHelperRoute', JPATH_SITE . '/components/com_swjprojects/helpers/route.php');

		if ($type == 'Project')
		{
			$item_id = $item->id;
			$item_catid = $item->catid;
		}
		else
		{
			$item_id = $item->project_id;
			$item_catid = $item->project->catid;
		}

		// Для версий и проектов ссылка на проект. Для документации - на документацию.

		if ($type == 'Document')
		{
			$link = \SWJProjectsHelperRoute::getDocumentRoute($item->id, $item->project_id);

		}
		else
		{

			$link = \SWJProjectsHelperRoute::getProjectRoute($item_id, $item_catid);
		}

		$link = HTMLHelper::link(
			Route::link(
				'site',
				$link,
				true,
				$linkMode,
				true
			), Text::_('COM_SWJPROJECTS_MORE'));

		$image = \SWJProjectsHelperImages::getImage('projects', $item_id, 'icon', $default_language);
		if (!$image)
		{
			$image = \SWJProjectsHelperImages::getImage('projects', $item_id, 'cover', $default_language);
		}

		if ($image)
		{
			$images[] = $image;
		}

		$message_params = [
			'context' => 'com_swjprojects.' . (strtolower($type)),
			'item_id' => $item->id
		];

		/** @var  object $result Telegram response */
		$result = $this->sendMessage($message, $images, $link, $message_params);

	}

	/**
	 * @param   string  $message         Text message with HTML markup
	 * @param   array   $images          Array of paths to images like '/images/image.jpg'
	 * @param   array   $link            Link HTML code: <a href='https://site.com'>Link text</a>
	 * @param   array   $message_params  Array of params. For upcoming features
	 *
	 * @return mixed
	 *
	 * @since 1.0.0
	 */
	private function sendMessage(string $message = '', array $images = [], string $link = '', array $message_params = [])
	{

		$event = \Joomla\CMS\Event\AbstractEvent::create('onWttelegrambotSendMessage',
			[
				'subject' => $this,
				'message' => $message,
				'images'  => $images,
				'link'    => $link,
				'params'  => $message_params
			]
		);
		$this->getApplication()->getDispatcher()->dispatch($event->getName(), $event);

		return $event->getArgument('result', []);
	}
}
