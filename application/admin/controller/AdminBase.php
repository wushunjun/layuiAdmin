<?php
namespace app\admin\controller;

use think\Controller;

class AdminBase extends Controller
{
    public function _initialize()
    {
        //是否登录判断
        if (!isset($_SESSION['admin']) || empty($_SESSION['admin'])) {
            $this->redirect('Public/login');
        }
        //判断权限
        $this->checkAuthority();
        //模板赋值
        $this->assign('admin_id', $_SESSION['admin']['id']);
        $this->assign('p', $this->request->param('p', 1, 'intval'));
    }

    /**
     * 判断权限
     * @return bool
     */
    public function checkAuthority()
    {
        //如果是超级管理员，则可以执行所有操作
        if ($_SESSION['admin']['id'] == 1) {
            return true;
        }
        //排除一些不必要的权限检查
        foreach (config('IGNORE_PRIV_LIST') as $key => $val) {
            if ($this->request->controller() == $val['module_name']) {
                if (count($val['action_list']) == 0) return true;

                foreach ($val['action_list'] as $action_item) {
                    if ($this->request->action() == $action_item) return true;
                }
            }
        }
        $node_mod = M('node');
        $node_id = $node_mod->where(array('module' => $this->request->controller(), 'action' => $this->request->action()))->getField('id');

        $access_mod = D('access');
        $rel = $access_mod->where(array('node_id' => $node_id, 'role_id' => $_SESSION['admin']['role_id']))->count();
        if ($rel == 0) {
            $this->error('没有权限');
        }
    }
}