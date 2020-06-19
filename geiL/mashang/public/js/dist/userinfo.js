webpackJsonp([2],[
/* 0 */
/***/ (function(module, exports, __webpack_require__) {

	$(function() {
		var CONF = __webpack_require__(1);

		// 提交信息
		function userInfo(balance) {
			var loading = weui.loading("提交信息");

			$.ajax({
				url: "https://yxapi.qiudashi.com/pub/user/account",
				dataType: "json",
				type: "POST",
				data: {
					token: _token,
					uid: _uid,
					name: $("#name").val(),
					idcard: $('#idcard').val(),
					bank: $('#bank').val(),
					bank_number: $('#bank_number').val(),
					alipay_number: $('#alipay_number').val(),
				},
				success: function(data) {
					loading.hide();
					if (data.status_code == 200) {
						window.location.href = location.href;
					} else {
						weui.alert(data.error_message);
					}
				},
				error: function(err) {
					loading.hide();
					weui.alert(JSON.stringify(err));
				}
			});
		}
		
		$(".J-userinfo").on("click", function(e) {
			userInfo();
		});
	});

/***/ })
]);