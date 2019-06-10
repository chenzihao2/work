var util = require('util');//常用函数模块
var c = function (val) {
    console.log(val);
}
function Base() {
    this.name = 'base';
    this.base = '1991';
    this.sayHello = function() {
        console.log('Hello' + this.name);
    };
}
Base.prototype.showName = function() {
    console.log(this.name);
}
function Sub() {
    this.name = 'sub';
}

util.inherits(Sub,Base);//Sub 继承 Base
var sub = new Sub();
var base = new Base();
//console.info(base);
//console.log(sub);
//sub.sayHello(); 没有继承这个方法 仅继承原型中的方法
//sub.showName();
//c(util.inspect(base,true,null,true));//对象转字符串
c(util.isArray([]));
var er = new Error();
c(util.isError(er));
