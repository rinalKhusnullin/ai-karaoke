<?php
namespace Bitrix\AIKaraoke\Table;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\TextField;

/**
 * Class DetailsTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> SESSION_ID int mandatory
 * <li> LYRIC_ID int mandatory
 * <li> SCORE int optional
 * <li> ACCURACY_PERCENT double optional
 * <li> NOTES text optional
 * </ul>
 *
 * @package Bitrix\Session
 **/

class SessionDetailsTable extends DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'aikaraoke_session_details';
	}

	/**
	 * Returns entity map definition.
	 *
	 * @return array
	 */
	public static function getMap()
	{
		return [
			new IntegerField(
				'ID',
				[
					'primary' => true,
					'autocomplete' => true,
					'title' => Loc::getMessage('DETAILS_ENTITY_ID_FIELD'),
				]
			),
			new IntegerField(
				'SESSION_ID',
				[
					'required' => true,
					'title' => Loc::getMessage('DETAILS_ENTITY_SESSION_ID_FIELD'),
				]
			),
			new IntegerField(
				'LYRIC_ID',
				[
					'required' => true,
					'title' => Loc::getMessage('DETAILS_ENTITY_LYRIC_ID_FIELD'),
				]
			),
			new IntegerField(
				'SCORE',
				[
					'title' => Loc::getMessage('DETAILS_ENTITY_SCORE_FIELD'),
				]
			),
			new FloatField(
				'ACCURACY_PERCENT',
				[
					'title' => Loc::getMessage('DETAILS_ENTITY_ACCURACY_PERCENT_FIELD'),
				]
			),
			new TextField(
				'NOTES',
				[
					'title' => Loc::getMessage('DETAILS_ENTITY_NOTES_FIELD'),
				]
			),
		];
	}
}