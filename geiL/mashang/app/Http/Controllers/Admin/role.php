<?php

$GLOBALS['power'] = [
    'admin' => [
        // 管理员管理
        'index_auth' => '1',
        'edit_auth' => '1',
        'create_auth' => '1',
        'delete_auth' => '1',

        // 用户管理
        'index_client' => '1',
        'create_client' => '1',
        'edit_client' => '1',
        'delete_client' => '1',
        'able_client' => '1', // 暂定/允许一个用户

        // 资源管理
        'index_source' => '1',
        'create_source' => '1',
        'edit_source' => '1',
        'delete_source' => '1',
        'details_source' => '1',
        'able_source' => '1', // 禁止一个料


        'update_withdraw' => '1'    // 料状态更新
    ],
];


class role{
    public function power($role, $power){
        if (array_key_exists($role, $GLOBALS['power'])){
            if (array_key_exists($power, $GLOBALS['power'][$role])) {
                return $GLOBALS['power'][$role][$power];
            }
        }
        return 0;
    }
}
