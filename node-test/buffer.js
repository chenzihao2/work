//创建缓冲区
//Buffer.from(str,encoding);str需要编码的字符串。encoding编码方式 默认utf8
var buf = Buffer.from('czh');
//console.log(buf.toString());

var buf1 = Buffer.alloc(10);
//console.log(buf1);
var buf2 = Buffer.alloc(10,1);
//console.log(buf2);
const buf6 = Buffer.from('test');
buf6.write('abce',2,1);
//写入缓冲区，如果空间不够只能写入一部分
console.log(buf6.toString('utf8'));
//读取缓存区的数据,使用指定的编码
const json = JSON.stringify(buf6);
console.log(json);
var buf16 = Buffer.concat([buf1,buf6]);
//console.log(buf16.toString());
console.log(JSON.stringify(buf16));
console.log(buf16.length);
