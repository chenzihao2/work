var c_p = require('child_process');
for(var i=0; i<3; i++) {
    var workP = c_p.exec('node support.js ' + i, function(e,stdout, stderr) {
        if (e) {
            console.error(e);
        }
        console.log(stdout);
        console.log(stderr);
    })
    workP.on('exit', function(code) {
        console.log('子进程退出' + code)
    })
}

