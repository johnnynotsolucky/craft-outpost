<?php

namespace johnnynotsolucky\outpost\migrations;

use Craft;
use craft\db\Migration;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createOutpostTable('{{%outpost_requests}}', [
            'hostname' => $this->string()->notNull(),
            'method' => $this->string()->notNull(),
            'path' => $this->string()->notNull(),
            'statusCode' => $this->string()->notNull(),
            'requestHeaders' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->notNull(),
            'responseHeaders' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->notNull(),
            'session' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->notNull(),
            'response' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->notNull(),
            'route' => $this->string()->notNull(),
            'action' => $this->string()->notNull(),
            'actionParams' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->notNull(),
            'isAjax' => $this->boolean()->notNull(),
            'isPjax' => $this->boolean()->notNull(),
            'isFlash' => $this->boolean()->notNull(),
            'isSecureConnection' => $this->boolean()->notNull(),
            'sampleSize' => $this->integer()->defaultValue(1),
            'startTime' => $this->integer()->notNull(),
            'endTime' => $this->integer()->notNull(),
            'duration' => $this->integer()->notNull(),
            'memory' => $this->integer()->notNull(),
            'querystring' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->notNull(),
            'params' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->notNull(),
            'hash' => $this->string()->notNull()
        ]);

        $this->createOutpostTable('{{%outpost_exceptions}}', [
            'class' => $this->string()->notNull(),
            'shortClass' => $this->string()->notNull(),
            'classHash' => $this->string()->notNull(),
            'message' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->notNull(),
            'code' => $this->string(),
            'file' => $this->string(),
            'line' => $this->string(),
            'simpleTrace' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->notNull(),
            'trace' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->notNull()
        ]);

        $this->createOutpostTable('{{%outpost_logs}}', [
            'message' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->notNull(),
            'level' => $this->string()->notNull(),
            'category' => $this->string()->notNull()
        ]);

        $this->createOutpostTable('{{%outpost_events}}', [
            'eventName' => $this->string()->notNull(),
            'eventClass' => $this->string()->notNull(),
            'isStatic' => $this->boolean()->notNull(),
            'senderClass' => $this->string()->notNull(),
            'senderData' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext'),
            'data' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')
        ]);

        $this->createOutpostTable('{{%outpost_profiles}}', [
            'duration' => $this->string()->notNull(),
            'category' => $this->string()->notNull(),
            'info' => $this->getDb()->getSchema()->createColumnSchemaBuilder('longtext')->notNull(),
            'level' => $this->string()->notNull(),
            'seq' => $this->string()->notNull()
        ]);
    }

    private function createOutpostTable($name, $fields)
    {
        $fields = array_merge([
            'id' => $this->primaryKey(),
            'requestId' => $this->string()->notNull(),
            'timestamp' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ], $fields);

        $this->createTable($name, $fields);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTableIfExists('{{%outpost_requests}}');
        $this->dropTableIfExists('{{%outpost_exceptions}}');
        $this->dropTableIfExists('{{%outpost_logs}}');
        $this->dropTableIfExists('{{%outpost_events}}');
        $this->dropTableIfExists('{{%outpost_profiles}}');
    }
}
