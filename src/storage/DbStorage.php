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

                $data = $this->getModel($type, $items[0])->toArray();

                if ($id) {
                    $command->update(Request::TABLE_NAME, $data, ['id' => $id]);
                } else {
                    $command->insert(Request::TABLE_NAME, $data);
                }
                $command->execute();
            }
        } else {
            $modelClass = Plugin::getTableModel($type);
            $fields = array_values((new $modelClass())->fields());

            $rows = array_map(function ($item) use ($type) {
                $model = $this->getModel($type, $item);
                return array_values($model->toArray());
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

    private function getModel($type, $item)
    {
        $modelClass = Plugin::getTableModel($type);
        $model = new $modelClass($item);

        return $model;
    }
}