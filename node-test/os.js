var c = function(val) {
    console.log(val);
}
var os = require('os');
c(os.tmpdir());
c(os.hostname());
c(os.type());
c(os.release());
c(os.totalmem());
c(os.freemem());
