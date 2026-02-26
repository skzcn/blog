<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\model\AuthGroup;
use app\model\AuthRule;
use think\App;

/**
 * 权限分发管理 (RBAC)
 */
class Auth extends AdminBase
{
    /**
     * 角色组列表
     */
    public function groups()
    {
        if ($this->request->isAjax()) {
            $list = AuthGroup::select();
            return json(['code' => 0, 'data' => $list]);
        }
        return view();
    }

    /**
     * 角色组表单
     */
    public function groupForm()
    {
        $id = $this->request->param('id', 0);
        $info = null;
        if ($id > 0) {
            $info = AuthGroup::find($id);
        }
        $rules = AuthRule::where('status', 1)->select();
        return view('group_form', ['info' => $info, 'rules' => $rules]);
    }

    /**
     * 保存角色组
     */
    public function groupSave()
    {
        $data = $this->request->post();
        if (isset($data['rules']) && is_array($data['rules'])) {
            $data['rules'] = implode(',', $data['rules']);
        }
        
        if (isset($data['id']) && $data['id'] > 0) {
            AuthGroup::update($data);
        } else {
            AuthGroup::create($data);
        }
        return json(['code' => 1, 'msg' => '保存成功']);
    }

    /**
     * 权限规则列表
     */
    public function rules()
    {
        if ($this->request->isAjax()) {
            $list = AuthRule::select();
            return json(['code' => 0, 'data' => $list]);
        }
        return view();
    }
}
