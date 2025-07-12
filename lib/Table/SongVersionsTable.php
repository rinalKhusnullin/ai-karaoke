<?php
namespace Bitrix\AIKaraoke\Table;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

/**
 * Class VersionsTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> SONG_ID int mandatory
 * <li> VERSION_TYPE string(255) optional default 'original'
 * <li> STORAGE_PATH string(255) mandatory
 * <li> METADATA text optional
 * <li> DATE_CREATED datetime optional default current datetime
 * </ul>
 *
 * @package Bitrix\Song
 **/

class SongVersionsTable extends DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'aikaraoke_song_versions';
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
					'title' => Loc::getMessage('VERSIONS_ENTITY_ID_FIELD'),
				]
			),
			new IntegerField(
				'SONG_ID',
				[
					'required' => true,
					'title' => Loc::getMessage('VERSIONS_ENTITY_SONG_ID_FIELD'),
				]
			),
			new StringField(
				'VERSION_TYPE',
				[
					'default' => 'original',
					'validation' => function()
					{
						return[
							new LengthValidator(null, 255),
						];
					},
					'title' => Loc::getMessage('VERSIONS_ENTITY_VERSION_TYPE_FIELD'),
				]
			),
			new StringField(
				'STORAGE_PATH',
				[
					'required' => true,
					'validation' => function()
					{
						return[
							new LengthValidator(null, 255),
						];
					},
					'title' => Loc::getMessage('VERSIONS_ENTITY_STORAGE_PATH_FIELD'),
				]
			),
			new TextField(
				'METADATA',
				[
					'title' => Loc::getMessage('VERSIONS_ENTITY_METADATA_FIELD'),
				]
			),
			new DatetimeField(
				'DATE_CREATED',
				[
					'default' => function()
					{
						return new DateTime();
					},
					'title' => Loc::getMessage('VERSIONS_ENTITY_DATE_CREATED_FIELD'),
				]
			),
		];
	}
}