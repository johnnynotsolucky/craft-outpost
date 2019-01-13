<?php
namespace johnnynotsolucky\outpost\storage;

use Craft;
use craft\db\Query;
use johnnynotsolucky\outpost\Plugin;
use johnnynotsolucky\outpost\models\Request;

class DbStorage extends BaseStorage
{
    public static function displayName(): string
    {
        return Craft::t('outpost', 'DB Storage');
    }

    public function store(String $type, Array $items)
    {
        if ($type === Request::TYPE) {
            if (sizeof($items) > 0) {
                $id = (new Query())
                    ->select(['id'])
                    ->from(Request::TABLE_NAME)
                    ->where(['requestId' => Plugin::getRequestId()])
                    ->scalar();

                $command = Craft::$app->db->createCommand();
                if ($id) {
                    $command->update(Request::TABLE_NAME, $items[0]->toArray(), ['id' => $id]);
                } else {
                    $command->insert(Request::TABLE_NAME, $items[0]->toArray());
                }
                $command->execute();
            }
        } else {
            $modelClass = Plugin::getTableModel($type);
            $fields = array_values((new $modelClass())->fields());

            $rows = array_map(function ($item) {
                return array_values($item->toArray());
            }, $items);

            Craft::$app->db->createCommand()
                ->batchInsert(
                    $modelClass::TABLE_NAME,
                    $fields,
                    $rows
                )
                ->execute();
        }
    }
}