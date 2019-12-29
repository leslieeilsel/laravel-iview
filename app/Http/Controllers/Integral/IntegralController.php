<?php

namespace App\Http\Controllers\Integral;


use App\Http\Controllers\Controller;
use App\Models\Role;
use App\User;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Dict;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\Departments;
use App\Models\Menu;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class IntegralController extends Controller
{
    public $user;
    public $office;
    public $group_id;
    public $department_id;
    public $projectsCache;
    public $projectPlanCache;
    public $departmentCache;

    public function __construct()
    {
        if (Auth::check()) {
            if (!Cache::has('departmentsCache')) {
                Cache::put('departmentsCache', collect(Departments::all()->toArray()), 10080);
            }
            $this->departmentCache = Cache::get('departmentsCache');

            $this->user = Auth::user();
            $this->office = $this->user->office;
            $this->department_id = $this->user->department_id;
            $this->group_id = $this->user->group_id;
        }
    }

    public function getSeeIds()
    {
        $seeIds = [];

        $roleId = $this->group_id;
        $userId = $this->user->id;
        $users = User::all()->toArray();
        // 数据权限类型
        $dataType = Role::where('id', $roleId)->first()->data_type;

        if ($dataType === 0) {
            $seeIds = array_column($users, 'id');
        }
        if ($dataType === 1) {
            $departmentIds = DB::table('iba_role_department')->where('role_id', $roleId)->get()->toArray();
            $departmentIds = array_column($departmentIds, 'department_id');
            $userIds = collect($users)->whereIn('department_id', $departmentIds)->all();
            $seeIds = array_column($userIds, 'id');
        }
        if ($dataType === 2) {
            $seeIds = [$userId];
        }

        return $seeIds;
    }
    //获取价值积分列表
    public function valueIntegralList(Request $request)
    {  
        $params =  $request->input();

        $data = DB::table('import_value_integral');
        $count = DB::table('import_value_integral');
        if(isset($params['date_time'])){
            $params['date_time'] = date('Y-m-d', strtotime($params['date_time']));
            $data = $data->where('date_time',$params['date_time']);
            $count = $count->where('date_time',$params['date_time']);
        }
        if (isset($params['pageNumber']) && isset($params['pageSize'])) {
            $data = $data
                ->limit($params['pageSize'])
                ->offset(($params['pageNumber'] - 1) * $params['pageSize']);
        }
        $data=$data->orderBy('id','desc')->orderBy('date_time','desc')->get()->toArray();
        $count = $count->count();

        return response()->json(['result' => $data, 'total' => $count], 200);
    }
    //获取发展积分列表
    public function devIntegralList(Request $request)
    {  
        $params =  $request->input();

        $data = DB::table('import_development_integral');
        $count = DB::table('import_development_integral');
        if(isset($params['date_time'])){
            $params['date_time'] = date('Y-m-d', strtotime($params['date_time']));
            $data = $data->where('date_time',$params['date_time']);
            $count = $count->where('date_time',$params['date_time']);
        }
        if (isset($params['pageNumber']) && isset($params['pageSize'])) {
            $data = $data
                ->limit($params['pageSize'])
                ->offset(($params['pageNumber'] - 1) * $params['pageSize']);
        }
        $data=$data->orderBy('id','desc')->orderBy('date_time','desc')->get()->toArray();
        $count = $count->count();

        return response()->json(['result' => $data, 'total' => $count], 200);
    }
    //获取销售数据
    public function salesData(Request $request)
    {   
        $params =  $request->input();

        $data = DB::table('integral');
        $data=$data->where('id',$params['id'])->get()->toArray();

        return response()->json(['result' => $data], 200);
    }
    //销售数据列表
    public function salesDataList(Request $request)
    {   
        $params =  $request->input();

        $data = DB::table('integral');
        $count = DB::table('integral');
        $user=$this->user;
        $group_id=$user->group_id;
        if($group_id===7){
            $data->where('applicant',$user->id);
        }
        if(isset($params['date_time'])){
            $params['date_time'] = date('Y-m-d', strtotime($params['date_time']));
            $data = $data->where('date_time','=',$params['date_time']);
            $count = $count->where('date_time','=',$params['date_time']);
        }
        if (isset($params['pageNumber']) && isset($params['pageSize'])) {
            $data = $data
                ->limit($params['pageSize'])
                ->offset(($params['pageNumber'] - 1) * $params['pageSize']);
        }
        $data=$data->orderBy('id','desc')->get()->toArray();
        $count = $count->count();
        $project_type_v = Dict::getOptionsArrByName('产品类型价值');
        $project_type_d = Dict::getOptionsArrByName('产品类型发展');
        $business_type = Dict::getOptionsArrByName('业务类型');
        $is_new_user = Dict::getOptionsArrByName('是否新用户');
        $terminal_type = Dict::getOptionsArrByName('终端类型');
        $set_meal = Dict::getOptionsArrByName('套餐');
        $set_meal_0 = Dict::getOptionsArrByName('融合套餐');
        $set_meal_1 = Dict::getOptionsArrByName('单卡套餐');
        $set_meal_2 = Dict::getOptionsArrByName('智慧企业套餐');
        $set_up_meal = Dict::getOptionsArrByName('升级套餐');
        $set_up_meal_0 = Dict::getOptionsArrByName('智慧家庭升级包');
        $set_up_meal_1 = Dict::getOptionsArrByName('5G升级包');
        $set_up_meal_2 = Dict::getOptionsArrByName('加第二路宽带');
        $set_up_meal_3 = Dict::getOptionsArrByName('加卡');
        foreach ($data as $k => $row) {
            $data[$k]['is_new_user'] = $is_new_user[$row['is_new_user']];
            if($row['is_new_user']===0){
                if($row['project_type']>3){
                    $data[$k]['project_type']=$project_type_v[$row['project_type']];
                }
            }else{
                if($row['project_type']<4){
                    $data[$k]['project_type']=$project_type_d[$row['project_type']];
                }
            }
            if(isset($row['business_type'])){
                $data[$k]['business_type'] = $business_type[$row['business_type']];
            }
            if(isset($row['terminal_type'])){
                $data[$k]['terminal_type'] = $terminal_type[$row['terminal_type']];
            }
            $set_meal_arr=json_decode($row['set_meal'],true);
            $set_meal_info='';
            if(isset($set_meal_arr['meal']['meal_type'])&&isset($set_meal_arr['meal']['meal'])){
                if($set_meal_arr['meal']['meal_type']===0){
                    $meal_type=$set_meal_0[$set_meal_arr['meal']['meal']];
                }elseif($set_meal_arr['meal']['meal_type']===1){
                    $meal_type=$set_meal_1[$set_meal_arr['meal']['meal']];
                }elseif($set_meal_arr['meal']['meal_type']===2){
                    $meal_type=$set_meal_2[$set_meal_arr['meal']['meal']];
                }
                $set_meal_info='套餐：'.$meal_type;
            }
            if(isset($set_meal_arr['up_meal'])){
                foreach($set_meal_arr['up_meal'] as $v){
                    if($v['meal']){
                        if($v['meal_type']===0){
                            $up_meal_type=$set_up_meal_0[$v['meal']];
                        }elseif($v['meal_type']===1){
                            $up_meal_type=$set_up_meal_1[$v['meal']];
                        }elseif($v['meal_type']===2){
                            $up_meal_type=$set_up_meal_2[$v['meal']];
                        }elseif($v['meal_type']===3){
                            $up_meal_type=$set_up_meal_3[$v['meal']];
                        }
                        $set_meal_info=$set_meal_info.'、'.$up_meal_type;
                    }
                }
            }
            $data[$k]['set_meal'] = $set_meal_info;
            $applicant = DB::table('users')->where('id',$row['applicant'])->value('name');
            $data[$k]['applicant'] = $applicant;
        }

        return response()->json(['result' => $data, 'total' => $count], 200);
    }
    //销售数据填报
    public function salesDataAdd(Request $request)
    {
        $params =  $request->input();
        $params['set_meal']=json_encode($params['meal_info']);
        unset($params['meal'],$params['meal_info'],$params['meal_type'],$params['integral']);
        $users=$this->user->id;
        $area=DB::table('iba_system_department')->where('id',$this->department_id)->value('title');
        $params['area'] = $area;
        $params['applicant'] = $users;
        $params['date_time'] = date('Y-m-d');
        $params['created_at']=date('Y-m-d H:i:s');
        $id = DB::table('integral')->insertGetId($params);

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //销售数据修改
    public function salesDataEdit(Request $request)
    {
        $params =  $request->input();
        $id=$params['id'];
        $params['set_meal']=json_encode($params['meal_info']);
        unset($params['meal'],$params['meal_info'],$params['meal_type'],$params['id']);
        $params['date_time'] = date('Y-m-d', strtotime($params['date_time']));
        $params['updated_at']=date('Y-m-d H:i:s');
        $id = DB::table('integral')->where('id',$id)->update($params);

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //销售数据删除
    public function salesDataDel(Request $request)
    {
        $params =  $request->input();
        $id = DB::table('integral')->where('id',$params['id'])->delete();

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    
    //活动计划列表
    public function activityPlan(Request $request)
    {   
        $params =  $request->input();

        $data = DB::table('activity_plan');
        $count = DB::table('activity_plan');
        if(isset($params['plan_start_time'])){
            $params['plan_start_time'] = date('Y-m-d', strtotime($params['plan_start_time']));
            $data = $data->where('plan_start_time','>',$params['plan_start_time']);
            $count = $count->where('plan_start_time','>',$params['plan_start_time']);
        }
        if(isset($params['plan_end_time'])){
            $params['plan_end_time'] = date('Y-m-d', strtotime($params['plan_end_time']));
            $data = $data->where('plan_end_time','<',$params['plan_end_time']);
            $count = $count->where('plan_end_time','<',$params['plan_end_time']);
        }
        if(isset($params['area'])){
            $data = $data->where('area','=',json_encode($params['area']));
            $count = $count->where('area','=',json_encode($params['area']));
        }
        if (isset($params['pageNumber']) && isset($params['pageSize'])) {
            $data = $data
                ->limit($params['pageSize'])
                ->offset(($params['pageNumber'] - 1) * $params['pageSize']);
        }
        $data=$data->orderBy('id','desc')->get()->toArray();
        foreach ($data as $k => $row) {
            $applicant = DB::table('users')->where('id',$row['applicant'])->value('name');
            $data[$k]['applicant'] = $applicant;
            $department = DB::table('iba_system_department')->whereIn('id',json_decode($row['area'],true))->pluck('title')->toArray();
            $data[$k]['area']=implode("/",$department);
            $data[$k]['area_id']=$row['area'];
            if($row['state']==0&&$row['plan_end_time']<date('Y-m-d')){
                $activity = DB::table('activity')->where('activity_plan_id',$row['id'])->value('id');
                if($activity){
                    $id = DB::table('activity_plan')->where('id',$row['id'])->update(['state'=>1]);
                }else{
                    $id = DB::table('activity_plan')->where('id',$row['id'])->update(['state'=>2]);
                }
            }
        }
        $count = $count->count();

        return response()->json(['result' => $data, 'total' => $count], 200);
    }
    //活动计划填报
    public function activityPlanAdd(Request $request)
    {   
        $params =  $request->input();
        $params['plan_start_time'] = date('Y-m-d', strtotime($params['plan_start_time']));
        $params['plan_end_time'] = date('Y-m-d', strtotime($params['plan_end_time']));
        $params['created_at']=date('Y-m-d H:i:s');
        $params['area'] = json_encode($params['area']);
        $users=$this->user->id;
        // $area=DB::table('iba_system_department')->where('id',$this->department_id)->value('title');
        $params['applicant'] = $users;
        $id = DB::table('activity_plan')->insertGetId($params);

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //活动计划修改
    public function activityPlanEdit(Request $request)
    {
        $params =  $request->input();
        $id=$params['id'];
        $params['plan_start_time'] = date('Y-m-d', strtotime($params['plan_start_time']));
        $params['plan_end_time'] = date('Y-m-d', strtotime($params['plan_end_time']));
        $params['updated_at']=date('Y-m-d H:i:s');
        $params['area'] = json_encode($params['area']);
        unset($params['id'],$params['area_id']);
        $id = DB::table('activity_plan')->where('id',$id)->update($params);

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //活动计划删除
    public function activityPlanDel(Request $request)
    {
        $params =  $request->input();
        $id = DB::table('activity_plan')->where('id',$params['id'])->delete();

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //活动执行列表
    public function activityImplement(Request $request)
    {   
        $params =  $request->input();

        $data = DB::table('activity');
        if(isset($params['plan_id'])){
            $data = $data->where('activity_plan_id',$params['plan_id']);
        }
        if (isset($params['pageNumber']) && isset($params['pageSize'])) {
            $data = $data
                ->limit($params['pageSize'])
                ->offset(($params['pageNumber'] - 1) * $params['pageSize']);
        }
        $data=$data->orderBy('id','desc')->get()->toArray();
        foreach ($data as $k => $row) {
            $activity_plan = DB::table('activity_plan')->where('id',$row['activity_plan_id'])->select('title','area','plan_start_time','plan_end_time')->first();
            $data[$k]['plan_start_time']=$activity_plan['plan_start_time'];
            $data[$k]['plan_end_time']=$activity_plan['plan_end_time'];
            $data[$k]['title']=$activity_plan['title'];
            $users=$this->user->name;
            $data[$k]['applicant'] = $users;
            $department = DB::table('iba_system_department')->whereIn('id',json_decode($activity_plan['area'],true))->pluck('title')->toArray();
            $data[$k]['area']=implode("/",$department);
        }
        $count = DB::table('activity')->count();

        return response()->json(['result' => $data, 'total' => $count], 200);
    }
    //活动执行填报
    public function activityImplementAdd(Request $request)
    {   
        $params =  $request->input();
        $params['date_time'] = date('Y-m-d');
        $params['created_at']=date('Y-m-d H:i:s');
        $users=$this->user->id;
        $params['applicant'] = $users;
        unset($params['area'],$params['title'],$params['plan_start_time'],$params['plan_end_time'],$params['name']);
        $id = DB::table('activity')->insertGetId($params);

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //活动执行修改
    public function activityImplementEdit(Request $request)
    {   
        $params =  $request->input();
        $id=$params['id'];
        $params['updated_at']=date('Y-m-d H:i:s');
        unset($params['id'],$params['plan_start_time'],$params['plan_end_time'],$params['area']);
        $id = DB::table('activity')->where('id',$id)->update($params);

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //活动执行删除
    public function activityImplementDel(Request $request)
    {   
        $params =  $request->input();
        $id = DB::table('activity')->where('id',$params['id'])->delete();

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //片区积分目标列表
    public function areaMeritsAimList(Request $request)
    {   
        $params =  $request->input();

        $data = DB::table('area_target');
        $count = DB::table('area_target');
        if(isset($params['target_start_time'])){
            $params['target_start_time'] = date('Y-m-d', strtotime($params['target_start_time']));
            $data = $data->where('target_start_time','>',$params['target_start_time']);
            $count = $count->where('target_start_time','>',$params['target_start_time']);
        }
        if(isset($params['target_end_time'])){
            $params['target_end_time'] = date('Y-m-d', strtotime($params['target_end_time']));
            $data = $data->where('target_end_time','<',$params['target_end_time']);
            $count = $count->where('target_end_time','<',$params['target_end_time']);
        }
        if(isset($params['duty_department'])){
            if(count($params['duty_department'])>0){
                $data = $data->where('duty_department','=',json_encode($params['duty_department']));
                $count = $count->where('duty_department','=',json_encode($params['duty_department']));
            }
        }
        if (isset($params['pageNumber']) && isset($params['pageSize'])) {
            $data = $data
                ->limit($params['pageSize'])
                ->offset(($params['pageNumber'] - 1) * $params['pageSize']);
        }
        $data=$data->orderBy('id','desc')->get()->toArray();
        $count = $count->count();
        // $product_type = Dict::getOptionsArrByName('产品类型');
        $business_type = Dict::getOptionsArrByName('业务类型');
        foreach ($data as $k => $row) {
            $business_target_info='';
            if(isset($row['business_target'])&&$row['business_target']){
                $business_target=json_decode($row['business_target'],true);
                foreach($business_target as $bk=>$bv){
                    $business_target_info.='、业务类型：'.$business_type[$bv['business_type']].',目标：'.$bv['target'];
                }
                $business_target_info=mb_substr($business_target_info,1);
            }else{
                $business_target=[];
            }
            // $data[$k]['product_type'] = $product_type[$row['product_type']];
            // $data[$k]['business_type'] = $business_type[$row['business_type']];
            // $data[$k]['business_type_id'] = $row['business_type'];
            
            $data[$k]['business_target'] = $business_target_info;
            $data[$k]['business_target_id'] = $row['business_target'];
            $data[$k]['target_start_time'] = date('Y-m-d',strtotime($row['target_start_time']));
            $data[$k]['target_end_time'] = date('Y-m-d',strtotime($row['target_end_time']));
            $department = DB::table('iba_system_department')->whereIn('id',json_decode($row['duty_department'],true))->pluck('title')->toArray();
            $data[$k]['duty_department']=implode("/",$department);
            $data[$k]['duty_department_id']=$row['duty_department'];
        }
        return response()->json(['result' => $data, 'total' => $count], 200);
    }
    //片区积分目标填报
    public function areaMeritsAimAdd(Request $request)
    {   
        $params =  $request->input();
        $params['business_target']=json_encode($params['business_target']);
        $params['duty_department']=json_encode($params['duty_department']);
        $params['target_start_time'] = date('Y-m-d', strtotime($params['target_start_time']));
        $params['target_end_time'] = date('Y-m-d', strtotime($params['target_end_time']));
        $params['created_at']=date('Y-m-d H:i:s');
        $id = DB::table('area_target')->insertGetId($params);

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //片区积分目标修改
    public function areaMeritsAimEdit(Request $request)
    {
        $params =  $request->input();
        $id=$params['id'];
        $params['business_target']=json_encode($params['business_target']);
        $params['duty_department']=json_encode($params['duty_department']);
        $params['target_start_time'] = date('Y-m-d', strtotime($params['target_start_time']));
        $params['target_end_time'] = date('Y-m-d', strtotime($params['target_end_time']));
        $params['updated_at']=date('Y-m-d H:i:s');
        unset($params['id'],$params['duty_department_id']);
        $id = DB::table('area_target')->where('id',$id)->update($params);

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //片区积分目标删除
    public function areaMeritsAimDel(Request $request)
    {
        $params =  $request->input();
        $id = DB::table('area_target')->where('id',$params['id'])->delete();

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    
    /**
     * 获取数据字典数据多个
     *
     * @param Request $request
     * @return array
     */
    public function dictData(Request $request)
    {
        $nameArr = $request->input('dictName');
        $result = Dict::getOptionsByNameArr($nameArr);
        return response()->json(['result' => $result], 200);
    }
    /**
     * 获取服务数据字典数据
     *
     * @param Request $request
     * @return array
     */
    public function dictDataSupervise(Request $request)
    {
        $result = DB::table('supervise_service_dict_data')->select('service_grade','title','id')->get()->toArray();
        return response()->json(['result' => $result], 200);
    }
    /**
     * 获取项目库表单中的数据字典数据多个
     *
     * @param Request $request
     * @return array
     */
    public function areaShop(Request $request){
        $params = $request->all();
        $result=DB::table('iba_system_department')->where('parent_id',$params['id'])->select('id','title')->get()->toArray();
        return response()->json(['result' => $result], 200);
    }
    /**
     * 上传
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadPic(Request $request)
    {
        $params = $request->all();
        $suffix = $params['pic']->getClientOriginalExtension();
        $path = Storage::putFileAs(
            'public/value/activity/' . $params['title'],
            $request->file('pic'),
            rand(1000000, time()) . '.' . $suffix
        );
        $path = 'storage/' . substr($path, 7);

        return response()->json(['result' => $path], 200);
    }
    //导入发展积分
    public function importIntegral(Request $request){
        $params = $request->all();
        $suffix = $params['import_file']->getClientOriginalExtension();
        $path = Storage::putFileAs(
            'public/value/sale-data',
            $request->file('import_file'),
            rand(1000000, time()) . '.' . $suffix
        );
        $path = 'storage/' . substr($path, 7);
        
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(TRUE);
        $spreadsheet = IOFactory::load($path);// 载入excel表格
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow(); // 总行数
        $highestColumn = $worksheet->getHighestColumn(); // 总列数
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        $data = [];
        $ids='id: ';
        for ($row = 3; $row <= $highestRow; $row++) { // 从第几行开始读取
            $data[]=$row;
            $row_data = [];
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $row_data[] = $worksheet->getCellByColumnAndRow($column, $row)->getValue();
                // if($column==12){
                //     $value = intval(($value - 25569) * 3600 * 24);     //转换成1970年以来的秒数 
                //     $row_data[] = gmdate('Y-m-d',$value);  
                // }else{
                //     $row_data[] = $value;
                // }
            }
            $id = DB::table('import_development_integral')
                ->insertGetId([
                    'province'=>$row_data[0],'local_wifi'=>$row_data[1],
                    'three_wifi'=>$row_data[2],'obu'=>$row_data[3],
                    'area'=>$row_data[4],'six_wifi'=>$row_data[5],
                    'u_single_move'=>$row_data[7],'u_single_wifi'=>$row_data[8],
                    'u_fuse'=>$row_data[9],'u_gover_products'=>$row_data[10],
                    'date_time'=>$row_data[11]
                    ]);
            if($row==3 || $row==$highestRow){
                $ids.=$id.'-';
            }
            $data[] = $row_data; //获取
        }
        $users=Auth::user();
        
        $date=$worksheet->getCellByColumnAndRow(12, 3)->getValue();
        // $date = intval(($date - 25569) * 3600 * 24);     //转换成1970年以来的秒数 
        // $time = gmdate('Y-m-d',$date);  
        DB::table('import_log')
                ->insertGetId([
                    'title'=>'导入发展积分','table_name'=>'import_development_integral',
                    'desc'=>substr($ids,0,strlen($ids)-1),'user_id'=>$users['id'],
                    'date_time'=>$date,'created_at'=>date('Y-m-d H:i:s')
                    ]);
        return response()->json(['result' => true], 200);
    }
    //导入价值积分
    public function importValueIntegral(Request $request){
        $params = $request->all();
        $suffix = $params['import_file']->getClientOriginalExtension();
        $path = Storage::putFileAs(
            'public/value/value',
            $request->file('import_file'),
            rand(1000000, time()) . '.' . $suffix
        );
        $path = 'storage/' . substr($path, 7);
        
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(TRUE);
        $spreadsheet = IOFactory::load($path);// 载入excel表格
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow(); // 总行数
        $highestColumn = $worksheet->getHighestColumn(); // 总列数
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        $data = [];
        $ids='id: ';
        for ($row = 3; $row <= $highestRow; $row++) { // 从第几行开始读取
            $data[]=$row;
            $row_data = [];
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $value=$worksheet->getCellByColumnAndRow($column, $row)->getValue();
                if($column==11){
                    $value = intval(($value - 25569) * 3600 * 24);     //转换成1970年以来的秒数 
                    $row_data[] = gmdate('Y-m-d',$value);  
                }else{
                    $row_data[] = $value;
                }
            }
            $id = DB::table('import_value_integral')
                ->insertGetId([
                    'province'=>$row_data[0],'local_wifi'=>$row_data[1],
                    'three_wifi'=>$row_data[2],'obu'=>$row_data[3],
                    'area'=>$row_data[4],'six_wifi'=>$row_data[5],
                    'stock_v_up'=>$row_data[7],'stock_contract'=>$row_data[8],
                    'stock_tenure'=>$row_data[9],'date_time'=>$row_data[10]
                    ]);
            if($row==3 || $row==$highestRow){
                $ids.=$id.'-';
            }
            $data[] = $row_data; //获取
        }
        $users=Auth::user();
        $date=$worksheet->getCellByColumnAndRow(11, 3)->getValue();
        $date = intval(($date - 25569) * 3600 * 24);     //转换成1970年以来的秒数 
        $time = gmdate('Y-m-d',$date);  
        DB::table('import_log')
                ->insertGetId([
                    'title'=>'导入价值积分','table_name'=>'import_value_integral',
                    'desc'=>substr($ids,0,strlen($ids)-1),'user_id'=>$users['id'],
                    'date_time'=>$time,'created_at'=>date('Y-m-d H:i:s')
                    ]);
        return response()->json(['result' => true], 200);
    }
    //首页获取组织架构
    public function departmentList(Request $request){
        $params = $request->all();
        if(isset($params['is_integral'])){
            if($params['is_integral']==true){
                $applicants = DB::table('integral')->where('date_time',date('Y-m-d'))->pluck('applicant')->toArray();
                $applicants=array_unique($applicants);
            }else{
                $applicants=[];  
            }
        }else{
            $applicants=[];  
        }
        return response()->json(['result' => $this->getDepartmentList($applicants)], 200);
    }
    
    public function getDepartmentList($applicants)
    {
        $departments = Departments::where('id','>',1)->whereNotIn('id',$applicants)->where('status',1)->get()->toArray();
        $data = [];
        $d=[];
        foreach ($departments as $k => $v) {
            if ($v['parent_id'] === 1) {
                // $v['children'] = $this->getChild($v['id'], $departments);
                // $v['key'] = $v['id'];
                // $v['expand'] = true;
                // $data[] = $v;
                $d['value'] = $v['id'];
                $d['label'] = $v['title'];
                $d['children'] = $this->getChild($v['id'], $departments);
                $data[] = $d;
                
            }
        }

        return $data; 
    }
    public function getChild($pid, $departments)
    {
        $tree = [];
        $t=[];
        foreach ($departments as $k => $v) {
            if ($v['parent_id'] === $pid) {
                $t['value']=$v['id'];
                $t['label']=$v['title'];
                // 匹配子记录
                $v['children'] = $this->getChild($v['id'], $departments); // 递归获取子记录
                if ($v['children'] == null) {
                    unset($v['children']);                          // 如果子元素为空则unset()
                }else{
                    $t['children']=$v['children'];
                }
                // $v['key'] = $v['id'];
                // $v['expand'] = true;
                // $tree[] = $v;
                $tree[]=$t;
            }
        }

        return $tree;
    }
    //获取组织架构   片区
    public function areaDepartmentList(Request $request){
        return response()->json(['result' => $this->getAreaDepartmentList()], 200);
    }
    
    public function getAreaDepartmentList()
    {
        $departments = Departments::where('id','>',1)->where('id','<',367)->get()->toArray();
        $data = [];
        $d=[];
        foreach ($departments as $k => $v) {
            if ($v['parent_id'] === 1) {
                $d['value'] = $v['id'];
                $d['label'] = $v['title'];
                $d['children'] = $this->getAreaChild($v['id'], $departments);
                $data[] = $d;
                
            }
        }

        return $data; 
    }
    public function getAreaChild($pid, $departments)
    {
        $tree = [];
        $t=[];
        foreach ($departments as $k => $v) {
            if ($v['parent_id'] === $pid) {
                $t['value']=$v['id'];
                $t['label']=$v['title'];
                // 匹配子记录
                $v['children'] = $this->getAreaChild($v['id'], $departments); // 递归获取子记录
                if ($v['children'] == null) {
                    unset($v['children']);                          // 如果子元素为空则unset()
                }else{
                    $t['children']=$v['children'];
                }
                $tree[]=$t;
            }
        }

        return $tree;
    }
    //查询组织机构的详情
    public function departmentInfo(Request $request)
    {   
        $params =  $request->input();
        $result = DB::table('department_info')->where('department_id',$params['id'])->first();
        $result['url']=DB::table('video_url')->where('department_name',$result['channel_name'])->value('url');
        return response()->json(['result' => $result], 200);
    } 
    //巡店列表
    public function videoPatrolList(Request $request)
    {   
        $params =  $request->input();
        $data = DB::table('video_patrol');
        $count = DB::table('video_patrol');
        if(isset($params['date_time'])){
            $params['date_time'] = date('Y-m-d', strtotime($params['date_time']));
            $data=$data->where('date_time',$params['date_time']);
            $count=$count->where('date_time',$params['date_time']);
        }
        // if(isset($params['department_name'])){
        //     // $params['department_name'] = ;
        //     // $data=$data->where('department_id',$params['department_name'][4]);
        //     // $count=$count->where('department_id',$params['department_name'][4]);
        // }
        $result = $data->orderBy('id','desc')->get()->toArray();
        foreach($result as $k=>$v){
            $depart=DB::table('department_info')->where('id',$v['department_info_id'])->first();
            $result[$k]['title']=$depart['channel_name'];
            $result[$k]['name']=$depart['applicant'];
            $result[$k]['area']=$depart['five_name'];
            $result[$k]['mobile']=$depart['mobile'];
            $result[$k]['addr']=$depart['channel_name'];
            $result[$k]['shop_state']=$v['shop_state']==0?'未营业':'正在营业';
        }

        $count = $count->count();
        return response()->json(['result' => $result,'total' => $count], 200);
    }
    //巡店的填报
    public function videoPatrolAdd(Request $request)
    {   
        $params =  $request->input();

        if(!isset($params['shop_state'])){
            $params['shop_state']=0;
        }
        if(!isset($params['desc'])){
            $params['desc']='';
        }
        $params['applicant']=$params['create_by'];
        $params['department_info_id']=$params['id'];
        $params['date_time']=date('Y-m-d');
        unset($params['title'],$params['sort'],$params['status'],$params['parent_id'],$params['description'],
        $params['create_by'],$params['update_by'],$params['created_at'],$params['updated_at'],
        $params['key'],$params['expand'],$params['nodeKey'],$params['selected'],$params['name'],
        $params['mobile'],$params['area'],$params['addr'],$params['id'],$params['state']);
        $c=$params;
        $id = DB::table('video_patrol')->insertGetId($params);

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //巡店的填报修改
    public function videoPatrolEdit(Request $request)
    {   
        $params =  $request->input();
        if(!isset($params['shop_state'])){
            $params['shop_state']=0;
        }
        if(!isset($params['desc'])){
            $params['desc']='';
        }
        $params['updated_at']=date('Y-m-d H:i:s');
        $id=$params['id'];
        unset($params['title'],$params['name'],$params['mobile'],$params['area'],$params['addr'],$params['id']);
        $id = DB::table('video_patrol')->where('id',$id)->update($params);

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //巡店的填报删除
    public function videoPatrolDel(Request $request)
    {   
        $params =  $request->input();
        $id = DB::table('video_patrol')->where('id',$params['id'])->delete();

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    /**
     * 上传视频
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadVideo(Request $request)
    {
        $params = $request->all();
        $suffix = $params['video']->getClientOriginalExtension();
        $path = Storage::putFileAs(
            'public/value/supervise/'.date('Y-m-d'),
            $request->file('video'),
            rand(1000000, time()) . '.' . $suffix   
        );
        $path = 'storage/' . substr($path, 7);

        return response()->json(['result' => $path], 200);
    }
    //服务列表
    public function superviseServiceList(Request $request)
    {   
        $params =  $request->input();
        $user=$this->user;
        $group_id=$user->group_id;
        $result = DB::table('supervise_service');
        $count = DB::table('supervise_service');
        if($group_id===6){
            $result->where('user_id',$user->id);
        }
        if(isset($params['date_time'])){
            $params['date_time'] = date('Y-m-d', strtotime($params['date_time']));
            $result=$result->where('date_time',$params['date_time']);
            $count=$count->where('date_time',$params['date_time']);
        }
        $result=$result->orderBy('id','desc')->get()->toArray();
        foreach($result as $k=>$v){
            $result[$k]['service_grade_id']=$v['service_grade'];
            if($v['user_id']){
                $users=DB::table('users')->where('id',$v['user_id'])->first();
                $result[$k]['ename']=$users['name'];
                $result[$k]['job_num']=$users['username'];
            }else{
                $result[$k]['ename']='';
                $result[$k]['job_num']='';                
            }
            if($v['department_id']){
                $result[$k]['area']=DB::table('iba_system_department')->where('id',$v['department_id'])->value('title');
            }else{
                $result[$k]['area']='';
            }
            $grade=0;
            if($v['service_grade']){
                $service_grade=json_decode($v['service_grade'],true);
                foreach($service_grade as $v){
                    $grade = $grade+$v['service_grade'];
                }
            }
            $result[$k]['service_grade']=$grade;
        }
        $count=$count->count();
        return response()->json(['result' => $result], 200);
    }
    //服务填报
    public function superviseServiceAdd(Request $request)
    {   
        $params =  $request->input();
        
        $params['user_id'] = $this->user->id;
        $params['department_id'] = $this->user->department_id;
        $params['date_time']=date('Y-m-d');
        $params['created_at']=date('Y-m-d H:i:s');
        $id = DB::table('supervise_service')->insertGetId($params);

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //服务修改 打分
    public function superviseServiceEdit(Request $request)
    {   
        $params =  $request->input();
        $id=$params['id'];
        unset($params['area'],$params['date_time'],$params['ename'],$params['job_num'],$params['phone'],$params['position'],$params['id']);
        $params['service_grade']=json_encode($params['service_grade']);
        $params['updated_at']=date('Y-m-d H:i:s');
        $id = DB::table('supervise_service')->where('id',$id)->update($params);

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    //服务删除
    public function superviseServiceDel(Request $request)
    {   
        $params =  $request->input();
        $id = DB::table('supervise_service')->where('id',$params['id'])->delete();

        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    // 价值积分导入日志
    public function valueIntegralLogs(Request $request){
        $params =  $request->input();
        
        $result = DB::table('import_log')->where('table_name','import_value_integral')->get()->toArray();

        return response()->json(['result' => $result], 200);
    }
    public function valueIntegralLogsDel(Request $request){
        $params =  $request->input();
        $data = DB::table('import_log')->where('id',$params['id'])->first();
        $desc=explode(': ',$data['desc']);
        $desc=explode('-',$desc[1]);
        $id = DB::table('import_log')->where('id',$params['id'])->delete();
        $v_id = DB::table('import_value_integral')->where('id','>=',$desc[0])->where('id','<=',$desc[1])->delete();
        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
    // 发展积分导入日志
    public function devIntegralLogs(Request $request){
        $params =  $request->input();
        
        $result = DB::table('import_log')->where('table_name','import_development_integral')->get()->toArray();

        return response()->json(['result' => $result], 200);
    }
    public function devIntegralLogsDel(Request $request){
        $params =  $request->input();
        $data = DB::table('import_log')->where('id',$params['id'])->first();
        $desc=explode(': ',$data['desc']);
        $desc=explode('-',$desc[1]);
        $id = DB::table('import_log')->where('id',$params['id'])->delete();
        $d_id = DB::table('import_development_integral')->where('id','>=',$desc[0])->where('id','<=',$desc[1])->delete();
        $result = $id ? true : false;

        return response()->json(['result' => $result], 200);
    }
}
                     