<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<script src="https://yxapi.qiudashi.com/js/lib/zepto.min.js"></script>
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <title>测试</title>
</head>
<body>
    <button onclick="test()">测试</button>
</body>
<script>

    function test(){
        $.ajax({
            type: 'GET',
            url: "https://yxapi.qiudashi.com/pub/user/wechat",
            success:function(data){
                alert(data)
            }
        });
    }

</script>
</html>