var c = function(val) {
    console.log(val);
}
c(__filename); //输出当前文件的绝对路径
c(__dirname); //输出当前文件的目录
var t = setTimeout(function() {
    c('timeout');
}, 1000);
clearTimeout(t);

var tt = setInterval(function() {
    c('interval');
},1000)

setTimeout(function() {
    clearInterval(tt);
},1000);

console.log('1%s2',__filename);
process.on('exit', function(code) {
    console.log('退出',code);
})

