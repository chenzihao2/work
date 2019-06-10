
exports.world = function() {
    console.log('hello world');
}

function hel() {
    var name;
    this.setName = function(n) {
        name = n;
    }
    this.sayName = function() {
        console.log('hello' + name);
    }
}

module.exports = hel;
