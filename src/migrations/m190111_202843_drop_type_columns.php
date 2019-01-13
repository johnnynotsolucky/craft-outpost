<?php

namespace johnnynotsolucky\outpost\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190111_202843_drop_type_columns migration.
 */
class m190111_202843_drop_type_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->dropColumn('{{%outpost_requests}}', 'type');
        $this->dropColumn('{{%outpost_exceptions}}', 'type');
        $this->dropColumn('{{%outpost_logs}}', 'type');
        $this->dropColumn('{{%outpost_events}}', 'type');
        $this->dropColumn('{{%outpost_profiles}}', 'type');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->addColumn('{{%outpost_requests}}', 'type', $this->string()->notNull());
        $this->addColumn('{{%outpost_exceptions}}', 'type', $this->string()->notNull());
        $this->addColumn('{{%outpost_logs}}', 'type', $this->string()->notNull());
        $this->addColumn('{{%outpost_events}}', 'type', $this->string()->notNull());
        $this->addColumn('{{%outpost_profiles}}', 'type', $this->string()->notNull());

        $this->update('{{%outpost_requests}}', ['type' => 'request']);
        $this->update('{{%outpost_exceptions}}', ['type' => 'exception']);
        $this->update('{{%outpost_logs}}', ['type' => 'log']);
        $this->update('{{%outpost_events}}', ['type' => 'event']);
        $this->update('{{%outpost_profiles}}', ['type' => 'profile']);
    }
}
