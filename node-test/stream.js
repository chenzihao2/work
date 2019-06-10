//创建可读流
var fs = require('fs');
var data = '';
var readS = fs.createReadStream('input.txt');
var writeS = fs.createWriteStream('output.txt');
//处理流事件
readS.on('data',function(chunk) {
    data += chunk;
})
readS.on('end',function() {
    console.log(data);
    writeS.write('11111');
    writeS.end('end~');
})
readS.on('error', function(e) {
    console.log(e.stack);
})
setTimeout(function(){
    console.log('谁快？');
},1000);
process.exit();
//writeS.write('11111');
//writeS.end();
//writeS.on('error', function(e) {
//    console.log(e);
//})
readS.pipe(writeS);
