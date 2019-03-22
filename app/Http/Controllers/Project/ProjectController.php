<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project\Projects;
use App\Models\Project\ProjectPlan;
use App\Models\Project\ProjectSchedule;
use App\Models\Role;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\OperationLog;
use Illuminate\Support\Facades\DB;
use App\Models\ProjectEarlyWarning;
use Illuminate\Support\Facades\Storage;
use App\Models\Dict;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\ImageManagerStatic as Image;

class ProjectController extends Controller
{
    public $seeIds;
    public $office;

    public function __construct()
    {
        $this->getSeeIds();
    }

    public function getSeeIds()
    {
        if (Auth::check()) {
            $roleId = Auth::user()->group_id;
            $this->office = Auth::user()->office;
            $userId = Auth::id();
            $dataType = Role::where('id', $roleId)->first()->data_type;

            if ($dataType === 0) {
                $userIds = User::all()->toArray();
                $this->seeIds = array_column($userIds, 'id');
            }
            if ($dataType === 1) {
                $departmentIds = DB::table('iba_role_department')->where('role_id', $roleId)->get()->toArray();
                $departmentIds = array_column($departmentIds, 'department_id');
                $userIds = User::whereIn('department_id', $departmentIds)->get()->toArray();
                $this->seeIds = array_column($userIds, 'id');
            }
            if ($dataType === 2) {
                $this->seeIds = [$userId];
            }
        }
    }

    /**
     * 获取一级项目信息
     *
     * @return JsonResponse
     */
    public function getProjects()
    {
        $query = new Projects();

        if ($this->office === 1) {
            $query = $query->where('is_audit', '!=', 4);
        }
        if ($this->office === 2) {
            $query = $query->where('is_audit', 1);
        }

        $projects = $query->whereIn('user_id', $this->seeIds)->get()->toArray();

        return response()->json(['result' => $projects], 200);
    }

    public function getAuditedProjects()
    {
        $projects = Projects::where('is_audit', 1)->whereIn('user_id', $this->seeIds)->get()->toArray();

        return response()->json(['result' => $projects], 200);
    }

    /**
     * 创建项目信息
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function add(Request $request)
    {
        $data = $request->input();
        $data['plan_start_at'] = date('Y-m', strtotime($data['plan_start_at']));
        $data['plan_end_at'] = date('Y-m', strtotime($data['plan_end_at']));
        $data['positions'] = self::buildPositions($data['positions']);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['is_audit'] = 4;
        $data['user_id'] = Auth::id();

        $planData = $data['projectPlan'];

        unset($data['projectPlan']);

        $id = DB::table('iba_project_projects')->insertGetId($data);
        $this->insertPlan($id, $planData);

        $result = $id ? true : false;

        if ($result) {
            $log = new OperationLog();
            $log->eventLog($request, '创建项目信息');
        }

        return response()->json(['result' => $result], 200);
    }

    /**
     * 添加项目计划
     *
     * @param $projectId
     * @param $planData
     */
    public function insertPlan($projectId, $planData)
    {
        foreach ($planData as $k => $v) {
            $v['project_id'] = $projectId;
            $v['parent_id'] = 0;
            $v['created_at'] = date('Y-m-d H:i:s');
            $monthArr = $v['month'];
            unset($v['month']);

            $parentId = DB::table('iba_project_plan')->insertGetId($v);

            foreach ($monthArr as $k => $month) {
                $month['project_id'] = $projectId;
                $month['parent_id'] = $parentId;
                $month['created_at'] = date('Y-m-d H:i:s');

                ProjectPlan::insert($month);
            }
        }
    }

    /**
     * 获取一段时间内的所有月份
     *
     * @param $startDate
     * @param $endDate
     * @return array
     */
    public function getMonthList($startDate, $endDate)
    {
        $yearStart = date('Y', $startDate);
        $monthStart = date('m', $startDate);

        $yearEnd = date('Y', $endDate);
        $monthEnd = date('m', $endDate);

        if ($yearStart == $yearEnd) {
            $monthInterval = $monthEnd - $monthStart;
        } elseif ($yearStart < $yearEnd) {
            $yearInterval = $yearEnd - $yearStart - 1;
            $monthInterval = (12 - $monthStart + $monthEnd) + 12 * $yearInterval;
        }
        //循环输出月份
        $data = [];
        for ($i = 0; $i <= $monthInterval; $i++) {
            $tmpTime = mktime(0, 0, 0, $monthStart + $i, 1, $yearStart);
            $data[$i]['year'] = date('Y', $tmpTime);
            $data[$i]['month'] = date('m', $tmpTime);
        }
        unset($tmpTime);

        $data = collect($data)->groupBy('year')->toArray();

        return $data;
    }

    public function addProjectPlan(Request $request)
    {
        $data = $request->input();
        $result = ProjectPlan::insert($data);

        // if ($result) {
        //     $log = new OperationLog();
        //     $log->eventLog($request, '创建项目计划');
        // }

        return response()->json(['result' => $result], 200);
    }

    /**
     * 修改项目信息
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function edit(Request $request)
    {
        $data = $request->input();
        $data['plan_start_at'] = date('Y-m', strtotime($data['plan_start_at']));
        $data['plan_end_at'] = date('Y-m', strtotime($data['plan_end_at']));
        if ($this->office === 0) {
            if ($data['is_audit'] === 2 || $data['is_audit'] === 3) {
                $data['is_audit'] = 4;
            }
        }
        $id = $data['id'];
        $data['reason'] = '';
        $projectPlan = $data['projectPlan'];
        unset($data['id'], $data['projectPlan'], $data['positions'], $data['center_point']);
        $result = Projects::where('id', $id)->update($data);
        $deleteRes = ProjectPlan::where('project_id', $id)->delete();
        $this->insertPlan($id, $projectPlan);

        $result = ($result >= 0 && $deleteRes >= 0) ? true : false;

        return response()->json(['result' => $result], 200);
    }

    /**
     * 获取所有项目信息
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllProjects(Request $request)
    {
        $params = $request->input('searchForm');
        $query = new Projects;
        if (isset($params['title'])) {
            $query = $query->where('title', $params['title']);
        }
        if (isset($params['subject'])) {
            $query = $query->where('subject', $params['subject']);
        }
        if (isset($params['unit'])) {
            $query = $query->where('unit', $params['unit']);
        }
        if (isset($params['num'])) {
            $query = $query->where('num', $params['num']);
        }
        if (isset($params['type'])) {
            $query = $query->where('type', $params['type']);
        }
        if (isset($params['build_type'])) {
            $query = $query->where('build_type', $params['build_type']);
        }
        if (isset($params['money_from'])) {
            $query = $query->where('money_from', $params['money_from']);
        }
        if (isset($params['is_gc'])) {
            $query = $query->where('is_gc', $params['is_gc']);
        }
        if (isset($params['nep_type'])) {
            $query = $query->where('nep_type', $params['nep_type']);
        }
        if (isset($params['status'])) {
            $query = $query->where('status', $params['status']);
        }
        if ($this->office === 1) {
            $query = $query->where('is_audit', '!=', 4);
        }
        if ($this->office === 2) {
            $query = $query->where('is_audit', 1);
        }
        $projects = $query->whereIn('user_id', $this->seeIds)->get()->toArray();
        foreach ($projects as $k => $row) {
            $projects[$k]['amount'] = number_format($row['amount'], 2);
            $projects[$k]['land_amount'] = isset($row['land_amount']) ? number_format($row['land_amount'], 2) : '';
            $projects[$k]['type'] = Dict::getOptionsArrByName('工程类项目分类')[$row['type']];
            $projects[$k]['is_gc'] = Dict::getOptionsArrByName('是否为国民经济计划')[$row['is_gc']];
            $projects[$k]['status'] = Dict::getOptionsArrByName('项目状态')[$row['status']];
            $projects[$k]['money_from'] = Dict::getOptionsArrByName('资金来源')[$row['money_from']];
            $projects[$k]['build_type'] = Dict::getOptionsArrByName('建设性质')[$row['build_type']];
            $projects[$k]['nep_type'] = isset($row['nep_type']) ? Dict::getOptionsArrByName('国民经济计划分类')[$row['nep_type']] : '';
            $projects[$k]['projectPlan'] = $this->getPlanData($row['id'], 'preview');
        }

        return response()->json(['result' => $projects], 200);
    }

    public function getEditFormData(Request $request)
    {
        $id = $request->input('id');

        $projects = Projects::where('id', $id)->first()->toArray();

        $projects['plan_start_at'] = date('Y-m', strtotime($projects['plan_start_at']));
        $projects['plan_end_at'] = date('Y-m', strtotime($projects['plan_end_at']));
        $projects['amount'] = (float)$projects['amount'];
        $projects['land_amount'] = $projects['land_amount'] ? (float)$projects['land_amount'] : null;

        $projects['projectPlan'] = $this->getPlanData($id, 'edit');

        return response()->json(['result' => $projects], 200);
    }

    /**
     * 获取计划数据
     *
     * @param integer $project_id
     * @param string  $status
     * @return array
     */
    public function getPlanData($project_id, $status)
    {
        $projectPlans = ProjectPlan::where('project_id', $project_id)->where('parent_id', 0)->get()->toArray();
        $data = [];
        foreach ($projectPlans as $k => $row) {
            $data[$k]['date'] = $row['date'];
            $data[$k]['amount'] = $status === 'preview' ? number_format($row['amount'], 2) : (float)$row['amount'];
            $data[$k]['image_progress'] = $row['image_progress'];
            $monthPlan = ProjectPlan::where('parent_id', $row['id'])->get()->toArray();
            foreach ($monthPlan as $key => $v) {
                $data[$k]['month'][$key]['date'] = $v['date'];
                $data[$k]['month'][$key]['amount'] = $status === 'preview' ? number_format($v['amount'], 2) : (float)$v['amount'];
                $data[$k]['month'][$key]['image_progress'] = $v['image_progress'];
            }
        }

        return $data;
    }

    public function getAllWarning()
    {
        $data = [];
        $projects = Projects::whereIn('user_id', $this->seeIds)->get()->toArray();
        $projectIds = array_column($projects, 'id');
        $projectSchedules = ProjectSchedule::whereIn('project_id', $projectIds)->get()->toArray();
        $scheduleIds = array_column($projectSchedules, 'id');
        $result = ProjectEarlyWarning::whereIn('schedule_id', $scheduleIds)->get()->toArray();
        foreach ($result as $k => $row) {
            $data[$k]['key'] = $row['id'];
            $res = ProjectSchedule::where('id', $row['schedule_id'])->first();
            foreach ($projects as $kk => $v) {
                if ($v['id'] === (int)$res->project_id) {
                    $data[$k]['title'] = $v['title'];
                }
            }
            $data[$k]['project_id'] = $res->project_id;
            $data[$k]['problem'] = $res->problem;
            $data[$k]['tags'] = $row['warning_type'];
            $data[$k]['schedule_at'] = $row['schedule_at'];
        }

        return response()->json(['result' => $data], 200);
    }

    /**
     * 构建坐标集
     *
     * @param array $positions
     * @return string
     */
    public static function buildPositions($positions)
    {
        $result = [];
        if ($positions) {
            foreach ($positions as $key => $value) {
                if ($value['status'] === 1) {
                    $result[] = $value['value'];
                }
            }
        }

        return implode(';', $result);
    }

    /**
     * 项目信息填报
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function projectProgress(Request $request)
    {
        $data = $request->all();
        $data['month'] = date('Y-m', strtotime($data['month']));
        $year = (int)date('Y', strtotime($data['month']));
        $month = (int)date('m', strtotime($data['month']));
        $plan_id = DB::table('iba_project_plan')->where('project_id', $data['project_id'])->where('date', $year)->value('id');
        $plan_month_id = DB::table('iba_project_plan')->where('project_id', $data['project_id'])->where('parent_id', $plan_id)->where('date', $month)->value('id');

        $data['build_start_at'] = date('Y-m', strtotime($data['build_start_at']));
        $data['build_end_at'] = date('Y-m', strtotime($data['build_end_at']));
        if ($data['plan_build_start_at']) {
            $data['plan_build_start_at'] = date('Y-m', strtotime($data['plan_build_start_at']));
        }
        if ($data['img_progress_pic']) {
            $data['img_progress_pic'] = substr($data['img_progress_pic'], 1);
        }
        $data['is_audit'] = 4;
        $data['plan_id'] = $plan_month_id;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['user_id'] = Auth::id();
        $schedule_id = DB::table('iba_project_schedule')->insertGetId($data);

        $m = intval($month);
        $plans_amount = DB::table('iba_project_plan')->where('project_id', $data['project_id'])->where('parent_id', $plan_id)->where('date', $m)->value('amount');
        $warResult = true;
        if ($plans_amount) {
            $Percentage = ($plans_amount - $data['month_act_complete']) / $plans_amount;
            if ($Percentage <= 0.1) {
                $warData['warning_type'] = 0;
            } elseif ($Percentage > 0.1 && $Percentage <= 0.2) {
                $warData['warning_type'] = 1;
            } elseif ($Percentage > 0.2) {
                $warData['warning_type'] = 2;
            }
            $warData['schedule_id'] = $schedule_id;
            $warData['schedule_at'] = $year . '-' . $month;
            $warResult = ProjectEarlyWarning::insert($warData);
        }
        $result = $schedule_id && $warResult;
        if ($result) {
            $log = new OperationLog();
            $log->eventLog($request, '投资项目进度填报');
        }

        return response()->json(['result' => $result], 200);
    }

    /**
     * 获取项目进度列表
     *
     * @return JsonResponse
     */
    public function projectProgressM($data)
    {
        $query = new ProjectSchedule;
        if (isset($data['project_id'])) {
            $query = $query->where('project_id', $data['project_id']);
        }
        if (isset($data['project_num'])) {
            $query = $query->where('project_num', $data['project_num']);
        }
        if (isset($data['subject'])) {
            $query = $query->where('subject', $data['subject']);
        }
        if (isset($data['start_at']) || isset($data['end_at'])) {
            if (isset($data['start_at']) && isset($data['end_at'])) {
                $data['start_at'] = date('Y-m', strtotime($data['start_at']));
                $data['end_at'] = date('Y-m', strtotime($data['end_at']));
                $query = $query->whereBetween('month', [$data['start_at'], $data['end_at']]);
            } else {
                if (isset($data['start_at'])) {
                    $data['start_at'] = date('Y-m', strtotime($data['start_at']));
                    $query = $query->where('month', $data['start_at']);
                } elseif (isset($data['end_at'])) {
                    $data['end_at'] = date('Y-m', strtotime($data['end_at']));
                    $query = $query->where('month', $data['end_at']);
                }
            }
        }
        if ($this->office === 1) {
            $query = $query->where('is_audit', '!=', 4);
        }
        if ($this->office === 2) {
            $query = $query->where('is_audit', 1);
        }
        $ProjectSchedules = $query->whereIn('user_id', $this->seeIds)->get()->toArray();
        foreach ($ProjectSchedules as $k => $row) {
            $ProjectSchedules[$k]['money_from'] = Projects::where('id', $row['project_id'])->value('money_from');
            $Projects = Projects::where('id', $row['project_id'])->value('title');
            $ProjectSchedules[$k]['project_title'] = $Projects;
        }

        return $ProjectSchedules;
    }

    public function projectProgressList(Request $request)
    {
        $params = $request->all();
        $result = $this->projectProgressM($params);
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
        $suffix = $params['img_pic']->getClientOriginalExtension();
        $path = Storage::putFileAs(
            'public/project/project-schedule/' . $params['month'] . "_" . $params['project_num'],
            $request->file('img_pic'),
            rand(1000000, time()) . '_' . $params['project_num'] . '.' . $suffix
        );
        $path = 'storage/' . substr($path, 7);
        $img = Image::make($path);
        $img_w = $img->width();
        $img_h = $img->height();
        $img = $img->resize($img_w * 0.5, $img_h * 0.5)->save($path);
        $c = $img->response($suffix);

        return response()->json(['result' => $path], 200);
    }

    /**
     * 查询项目计划
     *
     * @return JsonResponse
     */
    public function projectPlanInfo(Request $request)
    {
        $data = $request->input();
        $year = date('Y');
        if ($data['month']) {
            $year = date('Y', strtotime($data['month']));
        }
        $plans = DB::table('iba_project_plan')->where('date', $year)->where('project_id', $data['project_id'])->where('parent_id', 0)->first();

        return response()->json(['result' => $plans], 200);
    }

    /**
     * 查询数据字典
     *
     * @return JsonResponse
     */
    public function getData(Request $request)
    {
        $params = $request->input();
        $data = Dict::getOptionsByName($params['title']);

        return response()->json(['result' => $data], 200);
    }

    /**
     * 获取项目库表单中的数据字典数据
     *
     * @param Request $request
     * @return array
     */
    public function getProjectDictData(Request $request)
    {
        $nameArr = $request->input('dictName');
        $result = Projects::getDictDataByName($nameArr);

        return response()->json(['result' => $result], 200);
    }

    /**
     * 项目季度改变项目名称，填写其他字段
     *
     * @return JsonResponse
     */
    public function projectQuarter(Request $request)
    {
        $params = $request->input();
        if ($params['dictName']['year']) {
            $year = date('Y', strtotime($params['dictName']['year']));
        }
        $quarter = $params['dictName']['quarter'];
        if ($quarter == 0) {
            $date = $year . '-03';
        } elseif ($quarter == 1) {
            $date = $year . '-06';
        } elseif ($quarter == 2) {
            $date = $year . '-09';
        } elseif ($quarter == 3) {
            $date = $year . '-12';
        }
        $project_id = $params['dictName']['project_id'];
        $result = [];
        $result['projects'] = Projects::where('id', $project_id)->first();

        $result['ProjectSchedules'] = ProjectSchedule::where('project_id', $project_id)->where('month', $date)->first();

        return response()->json(['result' => $result], 200);
    }

    /**
     * 修改项目进度填报
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function editProjectProgress(Request $request)
    {
        $data = $request->all();
        $data['month'] = date('Y-m', strtotime($data['month']));
        $data['build_start_at'] = date('Y-m', strtotime($data['build_start_at']));
        $data['build_end_at'] = date('Y-m', strtotime($data['build_end_at']));
        if ($data['plan_build_start_at']) {
            $data['plan_build_start_at'] = date('Y-m', strtotime($data['plan_build_start_at']));
        }
        if ($this->office === 0) {
            if ($data['is_audit'] === 2 || $data['is_audit'] === 3) {
                $data['is_audit'] = 4;
            }
        }
        $id = $data['id'];
        unset(
            $data['id'], $data['updated_at'], $data['project_id'], $data['subject'], $data['project_num'],
            $data['build_start_at'], $data['build_end_at'], $data['total_investors'], $data['plan_start_at'],
            $data['plan_investors'], $data['plan_img_progress'], $data['month'], $data['project_title']
        );

        $result = ProjectSchedule::where('id', $id)->update($data);

        if ($result) {
            $log = new OperationLog();
            $log->eventLog($request, '修改项目进度信息');
        }

        return response()->json(['result' => $result], 200);
    }

    /**
     * 审核项目进度填报
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function auditProjectProgress(Request $request)
    {
        $data = $request->all();
        $result = ProjectSchedule::where('id', $data['id'])->update(['is_audit' => $data['status'], 'reason' => $data['reason']]);

        $result = $result || $result >= 0;

        return response()->json(['result' => $result], 200);
    }

    public function buildPlanFields(Request $request)
    {
        $date = $request->input('date');

        $start = strtotime($date[0]);
        $end = strtotime($date[1]);
        $dateList = self::getMonthList($start, $end);

        $data = [];
        $i = 0;
        foreach ($dateList as $year => $month) {
            $data[$i] = [
                'date' => $year,
                'amount' => null,
                'image_progress' => '',
            ];
            $monthList = [];
            $ii = 0;
            foreach ($month as $k => $v) {
                $monthList[$ii] = [
                    'date' => (int)$v['month'],
                    'amount' => null,
                    'image_progress' => '',
                ];
                $ii++;
            }
            $data[$i]['month'] = $monthList;
            $i++;
        }

        return response()->json(['result' => $data], 200);
    }

    /**
     * 审核项目库信息
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function auditProject(Request $request)
    {
        $data = $request->input('params');
        $result = Projects::where('id', $data['id'])->update(['is_audit' => $data['status'], 'reason' => $data['reason']]);

        $result = $result || $result >= 0;

        return response()->json(['result' => $result], 200);
    }

    /**
     * 项目调整 ，改变审核状态
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function projectAdjustment(Request $request)
    {
        $result = Projects::where('id', '>', 0)->update(['is_audit' => 3]);

        $result = $result ? true : false;

        return response()->json(['result' => $result], 200);
    }

    public function toAudit(Request $request)
    {
        $id = $request->input('id');

        $result = Projects::where('id', $id)->update(['is_audit' => 0]);

        $result = $result ? true : false;

        return response()->json(['result' => $result], 200);
    }

    public function toAuditSchedule(Request $request)
    {
        $id = $request->input('id');

        $result = ProjectSchedule::where('id', $id)->update(['is_audit' => 0]);

        $result = $result ? true : false;

        return response()->json(['result' => $result], 200);
    }

    /**
     * 填报，当当月实际投资发生改变时，修改累计投资
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function actCompleteMoney(Request $request)
    {
        $params = $request->input();
        if ($params['month']) {
            $year = date('Y', strtotime($params['month']));
        }
        $result = ProjectSchedule::where('project_id', $params['project_id'])->where('month', 'like', $year . '%')->sum('month_act_complete');

        return response()->json(['result' => $result], 200);
    }

    /**
     * 当月项目未填报列表
     *
     * @return JsonResponse
     */
    public function getProjectNoScheduleList()
    {
        $Project_id = ProjectSchedule::where('month', '=', date('Y-m'))->pluck('project_id')->toArray();
        $result = Projects::whereNotIn('id', $Project_id)->get()->toArray();

        return response()->json(['result' => $result], 200);
    }

}
