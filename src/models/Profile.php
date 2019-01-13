<?php
namespace johnnynotsolucky\outpost\models;

use Craft;

class Profile extends Base
{
    const TABLE_NAME = '{{%outpost_profiles}}';
    const TYPE = 'profile';

    public $duration;
    public $category;
    public $info;
    public $level;
    public $seq;
}
