<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

class SwaggerController extends Controller
{
    //swagger 接口文档
    /**
     * 返回JSON格式的Swagger定义
     *
     * 码上资源
     * @SWG\Swagger(
     *   @SWG\Info(
     *     title="码上资源",
     *     version="1.0.0"
     *   )
     * )
     */
    public function getJSON()
    {
        // 你可以将API的`Swagger Annotation`写在实现API的代码旁，从而方便维护，
        // `swagger-php`会扫描你定义的目录，自动合并所有定义。这里我们直接用`Controller/`
        // 文件夹。
        $swagger = \Swagger\scan(app_path('Http/Controllers/'));

        return response()->json($swagger, 200);
    }

}
