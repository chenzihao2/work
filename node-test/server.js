var http = require('http');
var url = require('url');
var rf = require('./readFile');

//http.createServer(function (request, response) {
//    response.writeHead(200, {'Content-Type': 'text/plain'});
//    response.end('iiidasfasdfello World\n');
//}).listen(8888);
var c = function(val) {
    console.log(val);
}


function start(route) {
    function onRequest(request, response) {
        if (request.url !== '/favicon.ico') {
            var path = url.parse(request.url).pathname;
            var pathname = url.parse(request.url).query;
            c("Request for" + path + " received");
            route(request.url);
            var filename = path.substr(1);
            var bo = 1;
            //var bo = rf.rf(filename, response);
            //response.writeHead(200,{
            //    "Content-type" : "text/plain"
            //});
            //response.write('hello');
            if (bo) {
                response.end();
            }
        }
    }

    http.createServer(onRequest).listen(8888);
    c("Server has started");
}

exports.start = start;
