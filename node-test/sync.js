var fs = require('fs');
var c = function(val) {
    console.log(val);
}
var start = process.hrtime();
c(start);
//同步
var data = fs.readFileSync('input.txt');
for(var i=0; i< 1000000 ; i++) {
}
c('同步');
c(process.hrtime(start));
