import * as child_process from 'child_process'

export default async function exec (cmd: string) {
  return new Promise((resolve, reject) => {
    child_process.exec(cmd, function (err, out, stderr) {
      if (err) {
        reject({code: err.code, message: JSON.stringify(stderr)})
        return
      }
      resolve(out)
    })
  })
}
