<?php
namespace johnnynotsolucky\outpost\models;

use Craft;

class Request extends Base
{
    const TABLE_NAME = '{{%outpost_requests}}';
    const TYPE = 'request';

    public $hostname;
    public $method;
    public $path;
    public $statusCode;
    public $requestHeaders;
    public $responseHeaders;
    public $session;
    public $response;
    public $route;
    public $action;
    public $actionParams;
    public $isAjax;
    public $isPjax;
    public $isFlash;
    public $isSecureConnection;
    public $sampleSize;
    public $startTime;
    public $endTime;
    public $memory;
    public $querystring;
    public $params;
    public $hash;
    public $duration;
}
