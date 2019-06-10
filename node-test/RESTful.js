var exp = require('express');
var app = exp();
var fs = require('fs');
app.get('/listUsers', function(req,res) {
    fs.readFile(__dirname + '/users.json',function(err,data){
        console.log(JSON.parse(data))
        
        res.end(data);
    })
})
app.get('/:id',function(req, res) {
    fs.readFile(__dirname + '/users.json', function(err, data) {
        data = JSON.parse(data);
        var user = data['user' + req.params.id]
        console.log(user);
        res.end(JSON.stringify(user));
    })
})
app.listen(8888);
