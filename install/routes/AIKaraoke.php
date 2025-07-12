<?php

use Bitrix\Main\Routing\RoutingConfigurator;
use Bitrix\Main\Routing\Controllers\PublicPageController;

function app(): CMain
{
	global $APPLICATION;
	return $APPLICATION;
}

function includeHeader(): void
{
	require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
}

function includeFooter(): void
{
	require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
}

return static function (RoutingConfigurator $routes) {
	$routes
		->prefix('aikaraoke')
		->group(function (RoutingConfigurator $routes) {
			$routes->any(
				'hub',
				static function () {
					includeHeader();

					app()->IncludeComponent('bitrix:aikaraoke.hub', '');

					includeFooter();
				}
			);
		});
};
