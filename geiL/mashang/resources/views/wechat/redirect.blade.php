<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no">
	<title>给料</title>
	<style>
		#url:hover {text-decoration: none;}
		* {box-sizing: border-box;}
		input,button,select,textarea{outline:none;-webkit-appearance:none;}
		input[type=radio] {-webkit-appearance:radio;}
		#url{
			display: block;
			font-size: 15px;
			text-align: center;
			border: 1px solid #ababab;
			padding: 10px 20px;
			width: 250px;
			border-radius: 10px;
			text-decoration: none;
			color: #fe4426;
			position: absolute;
			bottom: 80px;
			right: 50%;
			margin-right: -125px;
		}
		.zfb{
			position: absolute;
			top: 0px;
			right: 15px;
		}
		.zfb img{
			width: 183px;
			height: 59px;
		}
	</style>
</head>
<body>
<div id="url" onclick="clickBtn()">支付成功后，点击看内容</div>
<div class="zfb">
	<img src="https://yxapi.qiudashi.com/image/zfb.png" alt="">
</div>
</body>

<script>
    // 原生仿Ajax
    var xhr = new XMLHttpRequest();
    var Ajax={
      get: function(url, fn) {
        // XMLHttpRequest对象用于在后台与服务器交换数据   
        // var xhr = new XMLHttpRequest();            
        xhr.open('GET', url, true);
        xhr.onreadystatechange = function() {
          // readyState == 4说明请求已完成
          if (xhr.readyState == 4 && xhr.status == 200 || xhr.status == 304) { 
            // 从服务器获得数据 
            fn.call(this, xhr.responseText);  
          }
        };
        xhr.send();
      },
      // datat应为'a=a1&b=b1'这种字符串格式，在jq里如果data为对象会自动将对象转成这种字符串格式
      post: function (url, data, fn) {
        xhr.open("POST", url, true);
        // 添加http头，发送信息至服务器时内容编码类型
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");  
        xhr.onreadystatechange = function() {
          if (xhr.readyState == 4 && (xhr.status == 200 || xhr.status == 304)) {
            fn.call(this, xhr.responseText);
          }
        };
        xhr.send(data);
      }
	}
	
	// 加载后执行
    window.onload = function(){
        function getQueryString(name) {
            var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i");
            var reg_rewrite = new RegExp("(^|/)" + name + "/([^/]*)(/|$)", "i");
            var r = window.location.search.substr(1).match(reg);
            var q = window.location.pathname.substr(1).match(reg_rewrite);
            if(r != null){
                return unescape(r[2]);
            }else if(q != null){
                return unescape(q[2]);
            }else{
                return null;
            }
		}
        console.log(getQueryString("sid"));
		
//        var urlOrigin = window.location.origin;
        var urlOrigin = "https://yxm.qiudashi.com";
		var uid = getQueryString("uid");
		var token = getQueryString("token");
		var sids = getQueryString("sids");
		var suid = getQueryString("suid");
		var totals = getQueryString("totals");
		var sid = getQueryString("sid");
		var page = getQueryString("page") || 1;
		console.log('uid',uid)

		var btn = document.getElementById("url");
		var Url
		clickBtn = function(e){
			// Ajax.get('https://yxapitest.qiudashi.com/pub/user/'+uid+'/followcheck?starid=1006', 
			Ajax.get('https://yxapi.qiudashi.com/pub/order/sourceOrderStatus?uid='+uid+'&sids='+sids+'&token='+token+'&target=wx_gl_h5', 
			// 'vcbc_id='+ vcbc_id + '&user_id=' + uid + '&token=' + token + '&order_type=' + order_type + '&payment_method=2&trade_type=5&order_param='+ rid + '&pay_return_url=' + pay_return_url, 
				function(response) {
					var data = eval("("+response+")")
					console.log('data',data)

      		  	  	if(data.status_code === 200){
						var orderedSids = data.data.sids
						if(orderedSids.length > 0){
							Url = urlOrigin + '/orderlists?suid=' + suid + '&sids=' + JSON.stringify(orderedSids);
						}else{
							//未支付，跳回支付页
							if(page == 1){
								Url = urlOrigin + '/pay/payment?scene=s.' + sid + '&uid=' + suid;
							}else if(page == 2){
								Url = urlOrigin + '/recommendlist?uid=' + suid;
							}
						}
						window.location.href = Url;
      		  	  	}else{

      		  	}
      		})
		}
		// a.setAttribute("href", Url);
    }
</script>
</html>
