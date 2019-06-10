var mysql = require('mysql');
var conn= mysql.createConnection({
    host: 'localhost',
    user: 'root',
    password: 'root',
    database: 'employees'
});
conn.connect();
var s_sql = 'select * from employees order by emp_no desc limit 10';
conn.query(s_sql, function(e, result) {
    console.log(result);
})
conn.end();
