function say(name) {
    console.log(name);
}

function execute(fun,val) {
    fun(val);
}
execute(say,'czh');

execute(function(h){
    console.log('h' + h);
},'haha');
