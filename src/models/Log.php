<?php
namespace johnnynotsolucky\outpost\models;

use Craft;

class Log extends Base
{
    const TABLE_NAME = '{{%outpost_logs}}';
    const TYPE = 'log';

    public $message;
    public $level;
    public $category;
}
