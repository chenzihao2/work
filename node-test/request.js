var http = require('http');
var url = require('url');
var util = require('util');
http.createServer(function (req, res) {
    res.writeHead(200, {'Content-Type' : 'text/plain; charset=utf-8'});
    var params = url.parse(req.url, true).query;
    var name = util.inspect(params.name);
    res.write(name);
    //res.write("网站名：" + params.name);
    //var params = url.parse(req.url, true).query;
    //res.write(util.inspect(req.url));
    res.end();
}).listen(8888);
var qs = require('querystring');
http.createServer(function(req,res) {
    var post = '';
    req.on('data', function(chunk) {
        post += chunk;
    });
    req.on('end', function() {
        post = qs.parse(post);
        res.writeHead(200,{
            'Content-Type' : 'text/plain'
        })
        res.end(util.inspect(post));
    });
}).listen(9999);
