<?php

if (class_exists('AIKaraoke'))
{
	return;
}

class AIKaraoke extends CModule
{
	public $MODULE_ID = 'aikaraoke';
	public $MODULE_GROUP_RIGHTS = 'N';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;

	private array $eventsData = [];

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$arModuleVersion = [];

		include(__DIR__ . '/version.php');

		if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion))
		{
			$this->MODULE_VERSION = $arModuleVersion['VERSION'];
			$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
		}

		$this->MODULE_NAME = 'aikaraoke';
		$this->MODULE_DESCRIPTION = 'aikaraoke desc';
	}

	private function getDocumentRoot(): string
	{
		$context =
			\Bitrix\Main\Application::getInstance()
				->getContext()
		;

		return $context ? $context->getServer()
			->getDocumentRoot() : $_SERVER['DOCUMENT_ROOT'];
	}

	/**
	 * Calls all install methods.
	 * @returm void
	 */
	public function doInstall()
	{
		global $APPLICATION;

		$this->installFiles();
		$this->installDB();
		$this->installEvents();

		$APPLICATION->includeAdminFile(
			"Установить aikaraoke",
			$this->getDocumentRoot() . '/bitrix/modules/aikaraoke/install/step1.php'
		);
	}

	/**
	 * Calls all uninstall methods, include several steps.
	 * @returm void
	 */
	public function DoUninstall()
	{
		return true;
	}

	/**
	 * Installs DB, events, etc.
	 * @return bool
	 */
	public function installDB()
	{
		global $DB, $APPLICATION;
		$application = \Bitrix\Main\HttpApplication::getInstance();

		$connectionType = $application->getConnection()->getType();

		$errors = $DB->runSQLBatch(
			$this->getDocumentRoot() .'/bitrix/modules/' . $this->MODULE_ID . '/install/db/' . $connectionType . '/install.sql'
		);
		if ($errors !== false)
		{
			$APPLICATION->throwException(implode('', $errors));
			return false;
		}

		// module
		registerModule($this->MODULE_ID);

		return true;
	}

	/**
	 * Installs files.
	 * @return bool
	 */
	public function installFiles()
	{
		CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/install/components", $_SERVER["DOCUMENT_ROOT"]."/bitrix/components", true, true);
		CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/install/js", $_SERVER["DOCUMENT_ROOT"]."/bitrix/js", true, true);
		CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/aikaraoke/install/routes/AIKaraoke.php", $_SERVER["DOCUMENT_ROOT"]."/bitrix/routes/AIKaraoke.php");

		return true;
	}

	/**
	 * Uninstalls DB, events, etc.
	 * @param array $uninstallParameters Some params.
	 * @return bool
	 */
	public function uninstallDB(array $uninstallParameters = [])
	{
		return true;
	}

	public function installEvents(): void
	{
		$eventManager = Bitrix\Main\EventManager::getInstance();
		foreach ($this->eventsData as $module => $events)
		{
			foreach ($events as $eventCode => $callback)
			{
				$eventManager->registerEventHandler(
					$module,
					$eventCode,
					$this->MODULE_ID,
					$callback[0],
					$callback[1]
				);
			}
		}

		$this->installAgents();
	}

	/**
	 * Uninstalls files.
	 * @return bool
	 */
	public function uninstallFiles()
	{
		return true;
	}

	private function installAgents(): void
	{
	}
}
