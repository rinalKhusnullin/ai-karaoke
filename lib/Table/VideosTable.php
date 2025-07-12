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
 * Class VideosTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> SONG_ID int mandatory
 * <li> USER_ID int mandatory
 * <li> STORAGE_PATH string(255) mandatory
 * <li> VIDEO_STYLE string(100) optional default 'default'
 * <li> STATUS string(255) optional default 'processing'
 * <li> DATE_CREATED datetime optional default current datetime
 * <li> DATE_COMPLETED datetime optional
 * </ul>
 *
 * @package Bitrix\Videos
 **/

class VideosTable extends DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'aikaraoke_videos';
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
					'title' => Loc::getMessage('VIDEOS_ENTITY_ID_FIELD'),
				]
			),
			new IntegerField(
				'SONG_ID',
				[
					'required' => true,
					'title' => Loc::getMessage('VIDEOS_ENTITY_SONG_ID_FIELD'),
				]
			),
			new IntegerField(
				'USER_ID',
				[
					'required' => true,
					'title' => Loc::getMessage('VIDEOS_ENTITY_USER_ID_FIELD'),
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
					'title' => Loc::getMessage('VIDEOS_ENTITY_STORAGE_PATH_FIELD'),
				]
			),
			new StringField(
				'VIDEO_STYLE',
				[
					'default' => 'default',
					'validation' => function()
					{
						return[
							new LengthValidator(null, 100),
						];
					},
					'title' => Loc::getMessage('VIDEOS_ENTITY_VIDEO_STYLE_FIELD'),
				]
			),
			new StringField(
				'STATUS',
				[
					'default' => 'processing',
					'validation' => function()
					{
						return[
							new LengthValidator(null, 255),
						];
					},
					'title' => Loc::getMessage('VIDEOS_ENTITY_STATUS_FIELD'),
				]
			),
			new DatetimeField(
				'DATE_CREATED',
				[
					'default' => function()
					{
						return new DateTime();
					},
					'title' => Loc::getMessage('VIDEOS_ENTITY_DATE_CREATED_FIELD'),
				]
			),
			new DatetimeField(
				'DATE_COMPLETED',
				[
					'title' => Loc::getMessage('VIDEOS_ENTITY_DATE_COMPLETED_FIELD'),
				]
			),
		];
	}
}