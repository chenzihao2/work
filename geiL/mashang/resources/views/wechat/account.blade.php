<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, shrink-to-fit=no">
	<title>完善信息</title>
	<script src="https://yxapi.qiudashi.com/js/lib/flexible.js"></script>
	<link rel="stylesheet" type="text/css" href="https://yxapi.qiudashi.com/css/userinfo.css">
	<link rel="stylesheet" href="https://yxapi.qiudashi.com/css/lib/weui.min.css">
	<script type="text/javascript" src="https://yxapi.qiudashi.com/js/lib/weui.min.js"></script>
	<script type="text/javascript">
		var _uid = "<?php echo $uid?>",
				_token = "<?php echo $token; ?>";
	</script>
</head>
<body>
<div class="container">
	<div class="content">
		<h1>提现资料填写</h1>
		<p class="info">为了您的资金安全，我们将通过多种支付渠道为您提现（资料只填写一次）</p>
		<form action="" method="post" id="userInfoPush">
			<div class="input-box">
				<label for="name">姓&nbsp;&nbsp;&nbsp;&nbsp;名：</label>
				<input type="text" name="name" id="name" />
			</div>
			<div class="input-box">
				<label for="name">身份证号：</label>
				<input type="text" name="idcard" id="idcard" placeholder="填写18位身份证号"/>
			</div>
			<div class="input-box">
				<label for="name">开户银行：</label>
				<textarea type="text" name="bank" id="bank" placeholder="&nbsp;&nbsp;&nbsp;填写到具体支行"/></textarea>
			</div>
			<div class="input-box">
				<label for="name">银行卡号：</label>
				<input type="text" name="bank_number" id="bank_number" />
			</div>
			<div class="input-box">
				<label for="name">支付宝号：</label>
				<input type="text" name="alipay_number" id="alipay_number" />
			</div>
		</form>
	</div>
	<button class="drawmoney-btn J-userinfo">确认信息</button>
</div>
    <div class="message-box-wrapper J-pop">
        <div class="message-box">
            <div class="message-box-header">提示</div>
            <h3>为了确保支付宝渠道提现成功，请确认姓名、身份证、支付宝为同一人所有。</h3>
            <div class="message-box-content">
             <p>姓名：<span id="J-name"></span></p >
    <p>身份证号：<span id="J-idcard"></span></p >
    <p>支付宝号：<span id="J-alipay"></span></p >
            </div>
            <div class="message-box-btns">
                <div class="message-box-confirm J-confirm">确认并提交</div>
                <div class="message-box-cancel J-cancel">修改信息</div>
            </div>
        </div>
    </div>

<script type='text/javascript' src='https://yxapi.qiudashi.com/js/lib/zepto.min.js' charset='utf-8'></script>
<script src="https://yxapi.qiudashi.com/js/dist/common.d7c4d7e8.js"></script>
<script src="https://yxapi.qiudashi.com/js/dist/userinfo.24510f9d.js"></script>
</body>
</html>


