var fs = require('fs');
var c = function(val) {
    console.log(val);
}
var start = process.hrtime();
c(start);
//异步
fs.readFile('input.txt', function(e, data) {
    if (e) {
        return c(e);
    }
    c('异步' + data.toString());
})

c(process.hrtime(start));
//同步
var data = fs.readFileSync('input.txt');
c('同步' + data.toString());
c(process.hrtime(start));
