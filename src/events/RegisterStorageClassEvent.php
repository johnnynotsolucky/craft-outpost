<?php
namespace johnnynotsolucky\outpost\events;

use yii\base\Event;

class RegisterStorageClassEvent extends Event
{
    public $storageClasses = [];
}
