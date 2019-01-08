<?php
namespace johnnynotsolucky\outpost\services;

use Craft;
use craft\db\Query;
use yii\base\Component;
use johnnynotsolucky\outpost\Plugin;

class Purge extends Component
{
    public function all()
    {
        foreach (Plugin::TABLES as $table) {
            Craft::$app->db->createCommand()
                ->delete($table['table'])
                ->execute();
        }
    }

    public function old($keep = 100)
    {
        $settings = Plugin::getInstance()->getSettings();

        $queryA = (new Query())
            ->select(['requestId'])
            ->from('{{%outpost_requests}}')
            ->orderBy(['timestamp' => SORT_DESC])
            ->groupBy('requestId')
            ->limit($keep);

        $queryB = (new Query())
            ->select(['requestId'])
            ->from(['r' => $queryA]);

        foreach (Plugin::TABLES as $key => $table) {
            if ($key !== Plugin::TYPE_REQUEST) {
                Craft::$app->db->createCommand()
                    ->delete($table['table'], ['not in', 'requestId', $queryB])
                    ->execute();
            }
        }

        Craft::$app->db->createCommand()
            ->delete('{{%outpost_requests}}', ['not in', 'requestId', $queryB])
            ->execute();
    }
}
