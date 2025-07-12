<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

return [
	'css' => 'dist/karaoke-hub.bundle.css',
	'js' => 'dist/karaoke-hub.bundle.js',
	'rel' => [
		'main.core',
		'ui.btn',
	],
	'skip_core' => false,
];
