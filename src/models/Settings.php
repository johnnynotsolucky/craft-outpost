<?php
namespace johnnynotsolucky\outpost\models;

use Craft;
use craft\base\Model;
use yii\log\Logger;

class Settings extends Model
{
    public $includeCpRequests = true;

    public $purgeLimit = 100;

    public $requestSampling = false;

    public $minimumLogLevel = Logger::LEVEL_INFO;

    public $minimumExceptionLogLevel = Logger::LEVEL_TRACE;

    public $grouped = true;

    public function rules()
    {
        $rules = [
            [
                ['purgeLimit', 'minimumLogLevel', 'minimumExceptionLogLevel'],
                'number',
                'integerOnly' => true
            ],
            [
                ['purgeLimit'],
                'number',
                'min' => 0
            ]
        ];

        return $rules;
    }
}
