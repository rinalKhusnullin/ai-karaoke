<?php
namespace Bitrix\AIKaraoke\Table;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\Type\DateTime;

/**
 * Class LyricsTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> SONG_ID int mandatory
 * <li> LINE_NUMBER int mandatory
 * <li> TEXT text mandatory
 * <li> START_TIME double mandatory
 * <li> END_TIME double mandatory
 * <li> DATE_CREATED datetime optional default current datetime
 * </ul>
 *
 * @package Bitrix\Lyrics
 **/

class LyricsTable extends DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'aikaraoke_lyrics';
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
					'title' => Loc::getMessage('LYRICS_ENTITY_ID_FIELD'),
				]
			),
			new IntegerField(
				'SONG_ID',
				[
					'required' => true,
					'title' => Loc::getMessage('LYRICS_ENTITY_SONG_ID_FIELD'),
				]
			),
			new IntegerField(
				'LINE_NUMBER',
				[
					'required' => true,
					'title' => Loc::getMessage('LYRICS_ENTITY_LINE_NUMBER_FIELD'),
				]
			),
			new TextField(
				'TEXT',
				[
					'required' => true,
					'title' => Loc::getMessage('LYRICS_ENTITY_TEXT_FIELD'),
				]
			),
			new FloatField(
				'START_TIME',
				[
					'required' => true,
					'title' => Loc::getMessage('LYRICS_ENTITY_START_TIME_FIELD'),
				]
			),
			new FloatField(
				'END_TIME',
				[
					'required' => true,
					'title' => Loc::getMessage('LYRICS_ENTITY_END_TIME_FIELD'),
				]
			),
			new DatetimeField(
				'DATE_CREATED',
				[
					'default' => function()
					{
						return new DateTime();
					},
					'title' => Loc::getMessage('LYRICS_ENTITY_DATE_CREATED_FIELD'),
				]
			),
		];
	}
}