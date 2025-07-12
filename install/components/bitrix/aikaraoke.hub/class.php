<?php

\Bitrix\Main\Loader::requireModule('aikaraoke');

class AIKaraokeHub extends CBitrixComponent
{
	public function executeComponent(): void
	{
		$this->includeComponentTemplate();
	}
}
