var events = require('events');
var eventE = new events.EventEmitter();
//eventE.emit('error');
//console.log(events);
//console.log(eventEmitter);
//监听器(function)
var listener1 = function listener1() {
    console.log('listener1');
}

var listener2 = function listener2() {
    console.log('listener2');
}
eventE.once('connection', listener1);//注册监听器listener1到事件connection上
eventE.addListener('connection', listener2);//同上
eventE.on('connection', listener1);//同上
eventE.addListener('connection', listener2);
//eventE.removeListener('connection',listener1);移除监听器
//eventE.removeListener('connection',listener1);
eventE.addListener('connection',function() {
    console.log('callback');
})
console.log(eventE.listeners('connection'));//获取监听器数组
eventE.removeAllListeners();//移除所有的监听器，,如果参数输入事件名则移除事件上所有的监听器
console.log(eventE.listenerCount('connection'));//获取监听器总数
eventE.emit('connection');//触发事件
