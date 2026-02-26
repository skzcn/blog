<?php
declare(strict_types=1);

namespace app\service;

use app\model\User;

/**
 * 用户管理服务类
 */
class UserService
{
    /**
     * 获取用户列表
     */
    public function getList(array $where = [], int $page = 1, int $limit = 20)
    {
        $query = User::where($where);
        $count = $query->count();
        $list = $query->page($page, $limit)->order('id', 'desc')->select();
        
        return [
            'count' => $count,
            'list'  => $list
        ];
    }

    /**
     * 保存/更新用户
     */
    public function save(array $data)
    {
        if (isset($data['id']) && $data['id'] > 0) {
            $user = User::find($data['id']);
            if (!$user) throw new \Exception('用户不存在');
            
            // 如果密码为空，则不修改密码
            if (empty($data['password'])) {
                unset($data['password']);
            }
            $user->save($data);
        } else {
            if (User::where('username', $data['username'])->find()) {
                throw new \Exception('用户名已存在');
            }
            User::create($data);
        }
        return true;
    }

    /**
     * 删除用户
     */
    public function delete(int $id)
    {
        return User::destroy($id);
    }
}
