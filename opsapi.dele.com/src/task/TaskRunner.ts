import { Application, Task } from 'jweb'
import exec from '../lib/util/exec'

@Task
export default class TaskRunner {

  constructor () {
  }

  public async process(application: Application, args: any) {
    // TODO 获取待执行的任务，并启动ScriptRunner
    console.log('task runner', args)
  }
}
