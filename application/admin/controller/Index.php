<?php
namespace app\admin\controller;
use think\Controller;
use service\NodeService;
use service\ToolsService;

class Index extends controller{
    public function index(){
        $this->request->controller();
        $nodes = NodeService::get();
        $all = ToolsService::arr2tree($nodes, 'node', 'pnode', 'children');dump($all);
    }
    public function _test(){

    }
}