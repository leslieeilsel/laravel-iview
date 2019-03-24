<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Departments;
use App\Models\Role;
use App\Models\OperationLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Project\Projects;
use App\Models\Dict;

class RegistController extends Controller
{
    /**
     * 获取用户列表
     *
     * @return JsonResponse
     */
    public function getUsers()
    {
        $data = DB::table('users')->select('id','name', 'username', 'email', 'created_at', 'department_id', 'last_login', 'group_id', 'office')->get()->toArray();

        foreach ($data as $k => $row) {
            if (!isset($row['department_id'])) {
                $data[$k]['department'] = '无';
            } else {
                $department = Departments::where('id', $row['department_id'])->first()->title;
                $data[$k]['department'] = $department;
            }
            unset($data[$k]['department_id']);

            if (!isset($row['group_id'])) {
                $data[$k]['group'] = '无';
            } else {
                $group = Role::where('id', $row['group_id'])->first()->name;
                $data[$k]['group'] = $group;
            }
            unset($data[$k]['group_id']);

            if (!isset($row['office'])) {
                $data[$k]['office'] = '无';
            } else {
                $group = Dict::getOptionsArrByName('职位');
                $data[$k]['office'] = $group[$row['office']];
            }
        }

        return response()->json(['result' => $data], 200);
    }

    /**
     * 创建用户
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function registUser(Request $request)
    {
        $data = $request->input();
        unset($data['pwdCheck'], $data['department_title']);
        $data['password'] = bcrypt($data['password']);
        $data['created_at'] = date('Y-m-d H:i:s');
        $result = DB::table('users')->insert($data);

        if ($result) {
            $log = new OperationLog();
            $log->eventLog($request, '创建用户');
        }

        return $result ? response()->json(['result' => true], 200) : response()->json(['result' => false], 200);
    }

    /**
     * 修改密码
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $oldPassword = $request->get('oldPassword');

        $user = Auth::user();
        if (Hash::check($oldPassword, $user->password)) {
            $newPassword = $request->get('newPassword');
            $result = DB::table('users')->where('id', $user->id)->update(['password' => bcrypt($newPassword)]);
            $result = $result ? true : false;
        } else {
            $result = false;
        }

        if ($result) {
            $log = new OperationLog();
            $log->eventLog($request, '修改密码');
        }

        return response()->json(['result' => $result], 200);
    }

    /**
     * 获取数据字典数据
     *
     * @param Request $request
     * @return array
     */
    public function getUserDictData(Request $request)
    {
        $nameArr = $request->input('dictName');
        $result = Projects::getDictDataByName($nameArr);

        return response()->json(['result' => $result], 200);
    }
    /**
     * 删除用户
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteUserData(Request $request)
    {
        $ids = $request->input('id');
        if ($ids) {
            $result = DB::table('users')->whereIn('id', explode(',',$ids))->delete();
            $result = $result ? true : false;
        } else {
            $result = false;
        }

        if ($result) {
            $log = new OperationLog();
            $log->eventLog($request, '删除用户');
        }

        return response()->json(['result' => $result], 200);
    }

    /**
     * 修改用户
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function editRegistUser(Request $request)
    {
        $params = $request->input();
        unset($params['department_title']);
        $params['created_at'] = date('Y-m-d H:i:s');
        $result = DB::table('users')->where('id',$params['id'])->update($params);

        if ($result) {
            $log = new OperationLog();
            $log->eventLog($request, '修改用户');
        }

        return $result ? response()->json(['result' => true], 200) : response()->json(['result' => false], 200);
    }
    /**
     * 获取单挑用户
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUser(Request $request)
    {
        $params = $request->input();
        $result = DB::table('users')->where('id',$params['id'])->first();
        return response()->json(['result' => $result], 200);
    }
}
