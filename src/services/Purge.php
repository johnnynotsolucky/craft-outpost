<?php
namespace johnnynotsolucky\outpost\services;

use Craft;
use craft\db\Query;
use yii\base\Component;
use johnnynotsolucky\outpost\Plugin;
use johnnynotsolucky\outpost\models\Request;

class Purge extends Component
{
    public function all()
    {
        foreach (Plugin::TABLES as $table) {
            Craft::$app->db->createCommand()
                ->delete($table::TABLE_NAME)
                ->execute();
        }
    }

    public function old($keep = 100)
    {
        $settings = Plugin::getInstance()->getSettings();

        $queryA = (new Query())
            ->select(['requestId'])
            ->from(Request::TABLE_NAME)
            ->orderBy(['timestamp' => SORT_DESC])
            ->groupBy('requestId')
            ->limit($keep);

        $queryB = (new Query())
            ->select(['requestId'])
            ->from(['r' => $queryA]);

        foreach (Plugin::TABLES as $model) {
            if ($model !== Request::class) {
                Craft::$app->db->createCommand()
                    ->delete($model::TABLE_NAME, ['not in', 'requestId', $queryB])
                    ->execute();
            }
        }

        Craft::$app->db->createCommand()
            ->delete(Request::TABLE_NAME, ['not in', 'requestId', $queryB])
            ->execute();
    }
}
