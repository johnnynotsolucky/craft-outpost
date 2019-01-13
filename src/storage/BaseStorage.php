<?php
namespace johnnynotsolucky\outpost\storage;

abstract class BaseStorage
{
    abstract public function store(String $type, Array $items);
}
