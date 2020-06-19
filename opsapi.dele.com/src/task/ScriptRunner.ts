import { Application, Task } from 'jweb'
import exec from '../lib/util/exec'

@Task
export default class ScriptRunner {

  constructor () {
  }

  public async process(application: Application, args: any) {
    const tid = args.tid
    if (!tid) {
      return
    }
    // 根据tid获取当前执行脚本
    // 判断当前脚本是脚本文件还是shell命令
    // 先上传脚本文件
    // 执行脚本
    // 获取返回码和输出
    // 返回码 != 1，邮件通知脚本处理人
    try {
    } catch (e) {
      console.log(e)
    }
  }
}
