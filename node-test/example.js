'use strict';
var http = require('http');
//端口号
var port = process.env.PORT || 1337;
/**
 * 回调函数的使用
 */
//回调函数
var fs = require("fs");

//回调函数
fs.readFile('./input.txt', function (err, data) {
    if (err) return console.error(err);
    console.log(data.toString());
});
/************end**************/


/**
 * 事件驱动程序
 */
//引入events模块
var events = require('events');
//创建eventEmitter对象
var eventEmitter = new events.EventEmitter();

//创建时间处理程序
var connectHandler = function conneted() {
    console.log("connect success.");

    //触发 data_received 事件
    eventEmitter.emit('data_received');
}

//绑定connection 事件处理程序
eventEmitter.on('connection', connectHandler);

//使用匿名函数绑定data_received事件
eventEmitter.on('data_received', function () {
    console.log('received data1 success.');
});

eventEmitter.on('data_received', function () {
    console.log('received data2 success.');
});

//触发connection事件
setTimeout(function () {
    eventEmitter.emit('connection');
}, 1000);

console.log('program run over.');
/************end**************/

/************Buffer对象************/
var buf = Buffer.alloc(26);
for (var i = 0; i < 26; i++) {
    buf[i] = i + 97;
}

console.log(buf.toString('ascii'));// 输出: abcdefghijklmnopqrstuvwxyz
console.log(buf.toString('ascii', 0, 5)); // 输出: abcde
console.log(buf.toString('utf8', 0, 5));    // 输出: abcde
console.log(buf.toString(undefined, 0, 5)); // 使用 'utf8' 编码, 并输出: abcde

//buffer转Json
var buffer2json = Buffer.from('aabbc!');
var _json = buffer2json.toJSON(buffer2json);
console.log("The Buffer2Json is " + _json);

//缓冲区合并
var buffer1 = Buffer.from('Me');
var buffer2 = Buffer.from(' QinShiHuang');
var buffer3 = Buffer.from(' DaQian');
var buffer4 = Buffer.concat([buffer1, buffer2, buffer3]);
console.log("The concat result is " + buffer4.toString());

//缓冲区比较
//返回一个数字，表示 buf 在 otherBuffer 之前，之后或相同。
var cBuffer1 = Buffer.from('ABC');
var cBuffer2 = Buffer.from('ABCD');
console.log("The compare result is ")
var compareResult = cBuffer1.compare(cBuffer2);
if (compareResult < 0) {
    console.log(cBuffer1 + ' at before of ' + cBuffer2);
} else if (compareResult == 0) {
    console.log(cBuffer1 + ' at after of ' + cBuffer2);
} else {
    console.log(cBuffer1 + ' is equal of ' + cBuffer2);
}

//拷贝缓冲区
var copyBuffer1 = Buffer.from('I Hate You!');
var copyBuffer2 = Buffer.from('Love');
//将 buf2 插入到 buf1 指定位置上
copyBuffer2.copy(copyBuffer1, 2);
console.log("The copy content is " + copyBuffer1.toString());

//缓冲区裁剪
var sliceBuffer1 = Buffer.from('you are');
var sliceBuffer2 = sliceBuffer1.slice(0, 3);
console.log("sliceBuffer2 content : " + sliceBuffer2.toString());
/************end**************/


/*******************Stream流start**********************/
var streamData = '';
//创建可读流
var readerStream = fs.createReadStream('./input.txt');
//设置编码为utf-8
readerStream.setEncoding('UTF8');
//处理流事件-->data,end,and error
readerStream.on('data', function (chunk) {
    streamData += chunk;
});
readerStream.on('end', function () {
    console.log(streamData);
});
readerStream.on('error', function (err) {
    console.log(err.stack);
});
console.log('stream run over!');


//写入流
var writeData = 'this is a very good thing,dont worry';
// 创建一个可以写入的流，写入到文件 output.txt 中
var writeStream = fs.createWriteStream('./output.txt');
// 使用 utf8 编码写入数据
writeStream.write(writeData, 'UTF8');
// 标记文件末尾
writeStream.end();
// 处理流事件 --> finish, and error
writeStream.on('finish', function () {
    console.log('Write success.');
});
writeStream.on('error', function (err) {
    console.log(err.stack);
});

//管道流
//读取一个文件并写入另一个文件
//创建一个可读流
var rStream = fs.createReadStream('./input.txt');
//创建一个可写流
var wStream = fs.createWriteStream('./output1.txt');
//管道读写操作
//读取input.txt的文件内容，并将内容写入output.txt文件中
rStream.pipe(wStream);
console.log("Read and Write Over!");


//链式流
//压缩文件
var zlib = require('zlib');
//压缩input.txt文件为input.txt.gz
fs.createReadStream('./input.txt')
    .pipe(zlib.createGzip())
    .pipe(fs.createWriteStream('./input.txt.gz'));
console.log('zip complete!');
//解压文件
//fs.createReadStream('./input.txt.gz')
//    .pipe(zlib.createGunzip())
//    .pipe(fs.createWriteStream('./zip_input.txt'));
//console.log('zipGun complete!');

/*******************Stream流end**********************/



/*******************模块系统创建对象start**********************/
var Hello = require('./hello');
var hello = new Hello();
hello.setName('Wang Da Niang');
hello.sayHello();
/*******************模块系统创建对象end**********************/

/*******************Node.js函数start**********************/
//一个函数可以作为另一个函数的参数
function say(word) {
    console.log(word);
}
function execute(someFunction, value) {
    someFunction(value);
}
execute(say, 'WoZhenDeAiNi!');

//匿名函数,方法隐藏在参数中
execute(function (word) { console.log('NiMing:' + word); }, 'BuXiangShuoHua')
/*******************Node.js函数end**********************/


/*******************Node.js全局对象start **********************/
//输出全局变量_filename的值，__filename 表示当前正在执行的脚本的文件名。它将输出文件所在位置的绝对路径，且和命令行参数所指定的文件名不一定相同。 如果在模块中，返回的值是模块文件的路径。
console.log(__filename);
// 输出全局变量 __dirname 的值，__dirname 表示当前执行脚本所在的目录。
console.log(__dirname);


//Process属性
//输出到终端
process.stdout.write("Hello World!\n")
//通过参数读取
process.argv.forEach(function (val, index, array) {
    console.log(index + ':' + val);
});
//获取执行路径
console.log(process.exePath);
//平台信息
console.log(process.platform);

//Process方法
//输出当前目录
console.log('current direction is :' + process.cwd());
//输出当前版本
console.log('current version is :' + process.version);
//输出内存使用情况
console.log('current neicun is :'+process.memoryUsage());
/*******************Node.js全局对象end**********************/

/************util.inherits继承方法start**************/
var util = require('util');
function Base() {
    this.name = 'GuoJia';
    this.base = 1995;
    this.sayHello = function () {
        console.log('Hello' + this.name);
    };
}
Base.prototype.showName = function () {
    console.log(this.name);
}
function Sub() {
    this.name = 'Ai';
}
//使Sub继承于Base
util.inherits(Sub, Base);
var objBase = new Base();
objBase.showName();
objBase.sayHello();
console.log(objBase);
var objSub = new Sub();
objBase.showName();
//objSub.sayHello();
console.log(objSub);

/************util.inherits继承方法end**************/


/************util.inspect方法start**************/
//将任意对象转换 为字符串的方法，通常用于调试和错误输出
function Person() {
    this.name = 'LiXiuQi';
    this.toString = function () {
        return this.name;
    };
}
var obj = new Person();
console.log(util.inspect(obj));
console.log(util.inspect(obj, true));
/************util.inspect方法end**************/


/*******************启动服务start**********************/
var url = require("url");
var route = require('./route.js');
function start(route) {
    function onRequest(request, response) {
        var pathname = url.parse(request.url).pathname;
        console.log("Request for " + pathname + " received.");
        //处理url地址参数
        route(pathname);

        // 发送 HTTP 头部 
        // HTTP 状态值: 200 : OK
        // 内容类型: text/plain
        response.writeHead(200, { 'Content-Type': 'text/plain' });

        // 发送响应数据 "Hello World"
        response.end('Hello World\n');
    }
    //创建服务
    http.createServer(onRequest).listen(port);
}
start(route.route);


/*******************启动服务end**********************/

// 终端打印信息
console.info("hi this is the first application!");
