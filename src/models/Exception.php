<?php
namespace johnnynotsolucky\outpost\models;

use Craft;

class Exception extends Base
{
    const TABLE_NAME = '{{%outpost_exceptions}}';
    const TYPE = 'exception';

    public $class;
    public $shortClass;
    public $classHash;
    public $message;
    public $code;
    public $file;
    public $line;
    public $simpleTrace;
    public $trace;
}
