var fs = require('fs');
var c = function(val) {
    console.log(val);
}
var start = process.hrtime();
c(start);
//异步
fs.readFile('input.txt', function(e, data) {
    for(var i = 0; i < 1000000; i++) {
        //c(i);
    }
    c('异步');
})

c(process.hrtime(start));
c(process.hrtime());
