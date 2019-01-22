<?php

namespace johnnynotsolucky\outpost\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190122_123856_add_isCpRequest_column migration.
 */
class m190122_123856_add_isCpRequest_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%outpost_requests}}', 'isCpRequest', $this->boolean());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('{{%outpost_requests}}', 'isCpRequest');
    }
}
