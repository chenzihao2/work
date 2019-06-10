var fs = require('fs');
var rf = function(filename, response) {
    fs.readFile(filename, function(err, data) {
        if (err) {
            console.log(err);
            response.writeHead(404, {'Content-Type': 'text/html'});
        } else {
            response.writeHead(200, {'Content-Type': 'text/html'});
            response.write(data.toString());
            return true;
            //response.write(filename);
        }
    })
}
exports.rf = rf;
