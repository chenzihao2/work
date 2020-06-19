<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use Dingo\Api\Exception\StoreResourceFailedException;
use Dingo\Api\Routing\Helpers;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class RegisterController extends Controller
{
    //测试注册添加接口
    use RegistersUsers;
    use Helpers;
    
    /**
     * 假设是项目中的一个API
     *
     * @SWG\Post(path="/api/register",
     *   tags={"流程测试接口"},
     *   summary="api流程测试",
     *   description="验证测试流程",
     *   operationId="api/v1/register",
     *   produces={"application/json"},
     *   @SWG\Parameter(
     *     in="formData",
     *     name="name",
     *     type="string",
     *     description="用户姓名",
     *     required=true,
     *   ),
     *   @SWG\Parameter(
     *     in="formData",
     *     name="email",
     *     type="string",
     *     description="用户邮箱",
     *     required=true,
     *   ),
     *   @SWG\Parameter(
     *     in="formData",
     *     name="password",
     *     type="string",
     *     description="用户密码",
     *     required=true,
     *   ),
     *   @SWG\Response(response="default", description="操作成功")
     * )
     */

    public function register(Request $request){
	    $validator = $this->validator($request->all());
        if($validator->fails()){
            throw new StoreResourceFailedException("Validation Error", $validator->errors());
        }
        $user = $this->create($request->all());
        if($user){
            $token = JWTAuth::fromUser($user);

            return $this->response->array([
                "token" => $token,
                "message" => "User created",
                "status_code" => 201
            ]);
        }else{
            return $this->response->error("User Not Found...", 404);
        }
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|unique:users',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|min:6',
        ]);
    }

    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
    }
}
