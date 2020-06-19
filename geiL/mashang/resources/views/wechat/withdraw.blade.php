<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, shrink-to-fit=no">
	<title>我的钱包</title>
	<script src="https://yxapi.qiudashi.com/js/lib/flexible.js"></script>
	<link rel="stylesheet" type="text/css" href="https://yxapitest.qiudashi.com/css/drawmoney.css?v=1.2">
	<link rel="stylesheet" href="https://yxapi.qiudashi.com/css/lib/weui.min.css">
	<script type="text/javascript" src="https://yxapi.qiudashi.com/js/lib/weui.min.js"></script>
	<script type="text/javascript">
		var _uid = "<?php echo $uid?>",
			_token = "<?php echo $token; ?>";
	</script>
</head>
<body>
	   <div class="notice_box">
                        <p>给料是内容生成工具，严禁上传违法信息，内容仅代表发布者个人意见！</p>
                </div>
	<div class="container">
		<div class="content">
			<h1>余额</h1>
			<img src="https://yxapi.qiudashi.com/css/img/icon_money.png" />
			<p class="balance"><i>0.00</i>元</p>
			<!-- <p class="extra-info">应微信要求，每次最多提现2000元，多谢您的谅解！</p> -->
			<p class="info">提现金额将在<em>两个工作日</em>内直接打入您的账户,<em>满10元</em>可以提现</p>
			<div class="rate-info">服务费减免说明</div>
		</div>
		<button class="drawmoney-btn">立即提现</button>
	</div>

	<script type='text/javascript' src='https://yxapi.qiudashi.com/js/lib/zepto.min.js' charset='utf-8'></script>
	<script src="https://yxapitest.qiudashi.com/js/dist/common.28ea00b4.js?3"></script>
	<script src="https://yxapi.qiudashi.com/js/dist/drawmoney.cc1f0b54.js?5"></script>
</body>
</html>
