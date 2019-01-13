<?php
namespace johnnynotsolucky\outpost\models;

use Craft;

class Event extends Base
{
    const TABLE_NAME = '{{%outpost_events}}';
    const TYPE = 'event';

    public $eventName;
    public $eventClass;
    public $isStatic;
    public $senderClass;
    public $senderData;
    public $data;
}
