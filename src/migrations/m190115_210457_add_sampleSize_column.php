<?php

namespace johnnynotsolucky\outpost\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190115_210457_add_sampleSize_column migration.
 */
class m190115_210457_add_sampleSize_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%outpost_requests}}', 'sampleSize', $this->integer()->defaultValue(1));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('{{%outpost_requests}}', 'sampleSize');
    }
}
