<?php
namespace johnnynotsolucky\outpost\models;

use Craft;
use craft\base\Model;

abstract class Base extends Model
{
    public $requestId;
    public $timestamp;
}
