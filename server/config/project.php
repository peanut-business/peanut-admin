<?php

return [
    'version' => env('project.version', '1.0.0'),
    'website' => [
        'name' => env('project.web_name', 'Peanut Admin'),
        'url'  => env('project.web_url', ''),
    ],
    'based' => 'Vue 3.x、Arco Design Vue、ThinkPHP 8、MySQL',
    'channel' => [
        'website' => env('project.channel_website', ''),
        'gitee'   => env('project.channel_gitee', ''),
    ],
    // 工作台使用 Peanut 自有本地资源，不引用参考项目素材。
    'default_image' => [
        'admin_avatar'     => 'favicon.ico',
        'menu_admin'       => 'favicon.ico',
        'menu_role'        => 'favicon.ico',
        'menu_dept'        => 'favicon.ico',
        'menu_dict'        => 'favicon.ico',
        'menu_generator'   => 'favicon.ico',
        'menu_file'        => 'favicon.ico',
        'menu_auth'        => 'favicon.ico',
        'menu_web'         => 'favicon.ico',
        'project_docs'     => 'favicon.ico',
        'technical_support' => 'favicon.ico',
        'user_avatar'       => 'favicon.ico',
    ],
];
