<?php
namespace Bitrix\AIKaraoke\Table;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

/**
 * Class SongsTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> USER_ID int mandatory
 * <li> ORIGINAL_FILENAME string(255) mandatory
 * <li> STORAGE_PATH string(255) mandatory
 * <li> TITLE string(255) optional
 * <li> ARTIST string(255) optional
 * <li> DURATION int optional
 * <li> STATUS string(255) optional default 'uploaded'
 * <li> PROCESSING_STARTED datetime optional
 * <li> PROCESSING_COMPLETED datetime optional
 * <li> DATE_CREATED datetime optional default current datetime
 * </ul>
 *
 * @package Bitrix\Songs
 **/

class SongsTable extends DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'aikaraoke_songs';
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
					'title' => Loc::getMessage('SONGS_ENTITY_ID_FIELD'),
				]
			),
			new IntegerField(
				'USER_ID',
				[
					'required' => true,
					'title' => Loc::getMessage('SONGS_ENTITY_USER_ID_FIELD'),
				]
			),
			new StringField(
				'ORIGINAL_FILENAME',
				[
					'required' => true,
					'validation' => function()
					{
						return[
							new LengthValidator(null, 255),
						];
					},
					'title' => Loc::getMessage('SONGS_ENTITY_ORIGINAL_FILENAME_FIELD'),
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
					'title' => Loc::getMessage('SONGS_ENTITY_STORAGE_PATH_FIELD'),
				]
			),
			new StringField(
				'TITLE',
				[
					'validation' => function()
					{
						return[
							new LengthValidator(null, 255),
						];
					},
					'title' => Loc::getMessage('SONGS_ENTITY_TITLE_FIELD'),
				]
			),
			new StringField(
				'ARTIST',
				[
					'validation' => function()
					{
						return[
							new LengthValidator(null, 255),
						];
					},
					'title' => Loc::getMessage('SONGS_ENTITY_ARTIST_FIELD'),
				]
			),
			new IntegerField(
				'DURATION',
				[
					'title' => Loc::getMessage('SONGS_ENTITY_DURATION_FIELD'),
				]
			),
			new StringField(
				'STATUS',
				[
					'default' => 'uploaded',
					'validation' => function()
					{
						return[
							new LengthValidator(null, 255),
						];
					},
					'title' => Loc::getMessage('SONGS_ENTITY_STATUS_FIELD'),
				]
			),
			new DatetimeField(
				'PROCESSING_STARTED',
				[
					'title' => Loc::getMessage('SONGS_ENTITY_PROCESSING_STARTED_FIELD'),
				]
			),
			new DatetimeField(
				'PROCESSING_COMPLETED',
				[
					'title' => Loc::getMessage('SONGS_ENTITY_PROCESSING_COMPLETED_FIELD'),
				]
			),
			new DatetimeField(
				'DATE_CREATED',
				[
					'default' => function()
					{
						return new DateTime();
					},
					'title' => Loc::getMessage('SONGS_ENTITY_DATE_CREATED_FIELD'),
				]
			),
		];
	}
}
