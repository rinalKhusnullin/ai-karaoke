<?php

use Bitrix\Main\Routing\RoutingConfigurator;
use Bitrix\Main\Routing\Controllers\PublicPageController;

return static function (RoutingConfigurator $routes) {
	$routes
		->prefix('aikaraoke')
		->group(function (RoutingConfigurator $routes) {
			$routes->any(
				'hub',
				static function () {
					global $APPLICATION;
					$APPLICATION->IncludeComponent('bitrix:aikaraoke.hub', '', []);
				}
			);
		});
};
