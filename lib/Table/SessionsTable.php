<?php
namespace Bitrix\AIKaraoke\Table;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

/**
 * Class SessionsTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> USER_ID int mandatory
 * <li> SONG_ID int mandatory
 * <li> VIDEO_ID int optional
 * <li> AUDIO_RECORDING_PATH string(255) optional
 * <li> SCORE int optional
 * <li> ACCURACY_PERCENT double optional
 * <li> DATE_STARTED datetime optional default current datetime
 * <li> DATE_COMPLETED datetime optional
 * </ul>
 *
 * @package Bitrix\Sessions
 **/

class SessionsTable extends DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'aikaraoke_sessions';
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
					'title' => Loc::getMessage('SESSIONS_ENTITY_ID_FIELD'),
				]
			),
			new IntegerField(
				'USER_ID',
				[
					'required' => true,
					'title' => Loc::getMessage('SESSIONS_ENTITY_USER_ID_FIELD'),
				]
			),
			new IntegerField(
				'SONG_ID',
				[
					'required' => true,
					'title' => Loc::getMessage('SESSIONS_ENTITY_SONG_ID_FIELD'),
				]
			),
			new IntegerField(
				'VIDEO_ID',
				[
					'title' => Loc::getMessage('SESSIONS_ENTITY_VIDEO_ID_FIELD'),
				]
			),
			new StringField(
				'AUDIO_RECORDING_PATH',
				[
					'validation' => function()
					{
						return[
							new LengthValidator(null, 255),
						];
					},
					'title' => Loc::getMessage('SESSIONS_ENTITY_AUDIO_RECORDING_PATH_FIELD'),
				]
			),
			new IntegerField(
				'SCORE',
				[
					'title' => Loc::getMessage('SESSIONS_ENTITY_SCORE_FIELD'),
				]
			),
			new FloatField(
				'ACCURACY_PERCENT',
				[
					'title' => Loc::getMessage('SESSIONS_ENTITY_ACCURACY_PERCENT_FIELD'),
				]
			),
			new DatetimeField(
				'DATE_STARTED',
				[
					'default' => function()
					{
						return new DateTime();
					},
					'title' => Loc::getMessage('SESSIONS_ENTITY_DATE_STARTED_FIELD'),
				]
			),
			new DatetimeField(
				'DATE_COMPLETED',
				[
					'title' => Loc::getMessage('SESSIONS_ENTITY_DATE_COMPLETED_FIELD'),
				]
			),
		];
	}
}