<?php
declare (strict_types=1);

namespace app\system\controller;

use Exception;
use think\facade\Db;
use app\system\redis\ResourceRedis;
use app\system\redis\RoleRedis;
use think\bit\common\AddModel;
use think\bit\common\DeleteModel;
use think\bit\common\EditModel;
use think\bit\common\GetModel;
use think\bit\common\OriginListsModel;
use think\bit\lifecycle\AddAfterHooks;
use think\bit\lifecycle\DeleteAfterHooks;
use think\bit\lifecycle\DeleteBeforeHooks;
use think\bit\lifecycle\EditAfterHooks;
use think\bit\lifecycle\EditBeforeHooks;

class ResourceController extends BaseController
    implements AddAfterHooks, EditBeforeHooks, EditAfterHooks, DeleteBeforeHooks, DeleteAfterHooks
{
    use OriginListsModel, GetModel, AddModel, DeleteModel, EditModel;

    protected $model = 'resource';
    protected $origin_lists_orders = ['sort'];
    /**
     * @var string
     */
    private $key;

    /**
     * @param int $id
     * @return bool
     */
    public function addAfterHooks($id): bool
    {
        $this->clearRedis();
        return true;
    }

    /**
     * @inheritDoc
     */
    public function editBeforeHooks(): bool
    {
        try {
            if (!$this->edit_switch) {
                $data = Db::name($this->model)
                    ->where('id', '=', $this->post['id'])
                    ->find();
                $this->key = $data['key'];
            }
            return true;
        } catch (Exception $e) {
            $this->edit_before_result = [
                'error' => 1,
                'msg' => $e->getMessage()
            ];
            return false;
        }
    }

    /**
     * @return bool
     */
    public function editAfterHooks(): bool
    {
        try {
            if (!$this->edit_switch && $this->post['key'] != $this->key) {
                Db::name($this->model)
                    ->where('parent', '=', $this->key)
                    ->update([
                        'parent' => $this->post['key']
                    ]);
            }
            $this->clearRedis();
            return true;
        } catch (Exception $e) {
            $this->edit_after_result = [
                'error' => 1,
                'msg' => $e->getMessage()
            ];
            return false;
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function deleteBeforeHooks(): bool
    {
        $data = Db::name($this->model)
            ->whereIn('id', $this->post['id'])
            ->find();

        $result = Db::name($this->model)
            ->where('parent', '=', $data['key'])
            ->count();

        if (!empty($result)) {
            $this->delete_before_result = [
                'error' => 1,
                'msg' => 'error:has_child'
            ];
        }

        return empty($result);
    }

    /**
     * @return bool
     */
    public function deleteAfterHooks(): bool
    {
        $this->clearRedis();
        return true;
    }

    /**
     * 排序接口
     * @return array
     */
    public function sort(): array
    {
        if (empty($this->post['data'])) {
            return [
                'error' => 1,
                'msg' => 'error'
            ];
        }

        return Db::transaction(function () {
            foreach ($this->post['data'] as $value) {
                Db::name($this->model)->update($value);
            }
            $this->clearRedis();
            return true;
        }) ? [
            'error' => 0,
            'msg' => 'success'
        ] : [
            'error' => 1,
            'msg' => 'error'
        ];
    }

    /**
     * 清除缓存
     */
    private function clearRedis(): void
    {
        ResourceRedis::create()->clear();
        RoleRedis::create()->clear();
    }

    /**
     * 验证访问控制键是否存在
     * @return array
     */
    public function validedKey(): array
    {
        if (empty($this->post['key'])) {
            return [
                'error' => 1,
                'msg' => 'error:require_key'
            ];
        }

        $result = Db::name($this->model)
            ->where('key', '=', $this->post['key'])
            ->count();

        return [
            'error' => 0,
            'data' => !empty($result)
        ];
    }
}