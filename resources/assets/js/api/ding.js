import request from '../utils/request'

/**
 * 用户信息
 * @returns {*}
 */
export function getUserId(form) {
  return request({
    url: '/api/ding/userId',
    method: 'post',
    data: {...form}
  });
}

/**
 * 推送消息
 * @returns {*}
 */
export function userNotify(form) {
  return request({
    url: '/api/ding/userNotify',
    method: 'get',
    data: {...form}
  });
}

/**
 * 获取项目进度填报已审核的项目列表（附加权限控制）
 * @returns {*}
 */
export function getAuditedProjects() {
  return request({
    url: '/api/ding/getAuditedProjects',
    method: 'get'
  });
}
/**
 * 投资项目进度填报
 * @returns {*}
 */
export function projectProgress(form) {
  return request({
    url: '/api/ding/projectProgress',
    method: 'post',
    data: {...form}
  });
}
/**
 * 获取所有部门
 * @returns {*}
 */
export function getAllProjects(form) {
  return request({
    url: '/api/ding/getAllProjects',
    method: 'post',
    data: {...form}
  });
}
/**
 * 获取进度
 * @returns {*}
 */
export function projectProgressList(form) {
  return request({
    url: '/api/ding/projectProgressList',
    method: 'post',
    data: {...form}
  });
}
/**
 * 获取所有预警信息
 * @returns {*}
 */
export function getAllWarning(form) {
  return request({
    url: '/api/ding/getAllWarning',
    method: 'post',
    data: {...form}
  });
}


