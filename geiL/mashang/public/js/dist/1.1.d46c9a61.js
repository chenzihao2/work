webpackJsonp([1,2],[,,function(e,n,r){function t(e,n){e.on("click",function(e){e.stopPropagation(),$("body").append($(p())),a=$(".row").eq(1),$(".code-btn").on("click",function(e){s($("#phone"),$(this))}),$(".confirm-btn").on("click",function(e){i(n)}),$(".popup").on("click",function(){o()})})}function o(){d=!1,v=!1,$(".popup").remove(),$(".dialong-register").remove()}function s(e,n){if(!d){d=!0;var r=f.checkPhone(e);r.st===!0?$.ajax({url:l.PHP_PATH+"pub/user/"+_uid+"/smssend",data:{token:_token,telephone:$("#phone").val()},type:"POST",dataType:"json",success:function(e){console.log(e);var t=parseInt(e.status_code,10);200===t?(r.st=!0,r.errMsg="",c(n)):10002===t?(d=!1,r.st=!1,r.errMsg="手机号格式不正确"):10003===t?(d=!1,r.st=!1,r.errMsg="当日只能发送三条短信，已超过限制"):(d=!1,r.st=!1,r.errMsg="服务器错误"),u({st:r.st,errMsg:r.errMsg,errHolder:a.prev(".err-status"),index:0})},error:function(e){console.log(e),d=!1}}):(d=!1,u({st:r.st,errMsg:r.errMsg,errHolder:a.prev(".err-status"),index:0}))}}function i(e){var n=$("#code"),r=$.trim(n.val()),t=$.trim($("#phone").val());if(v&&r.length>0){var o=weui.loading("正在提现");f.checkAuthIsRight(t,r,function(n){return n.st?(console.log("实名成功"),void e()):(o.hide(),void u({st:n.st,errMsg:n.errMsg,errHolder:a.next(".err-status"),index:1}))})}}function c(e){var n=60,r=null;v=!0,e.html("正在发送..."),r=setInterval(function(){return 0==n?(clearInterval(r),e.html("发送验证码"),void(d=!1)):(e.html(n),void n--)},1e3)}function u(e){var n=e.errHolder;return console.log(e),e.st===!0?void n.remove():void(0!=n.length?n.html(e.errMsg):$(".row").eq(e.index).after("<span class='err-status'>"+e.errMsg+"</span>"))}var a,l=r(1),p=r(3),f=r(5),d=!1,v=!1;e.exports=t},function(e,n,r){var t;t=function(e){return r(4)("register",' <div class="popup"></div> <div class="dialong-register"> <h2>实名认证</h2> <p>根据相关规定，涉及到售卖提现，需要实名认证</p> <div class="row"> <label>手机号<input id="phone" name="phone" type="text" /></label> </div>  <div class="row"> <label>验证码<input id="code" name="code" type="text" /></label> <button class="code-btn">发送验证码</button> </div>  <button class="confirm-btn">确定</button> </div>')}.call(n,r,n,e),!(void 0!==t&&(e.exports=t))},function(e,n,r){var t;!function(){function o(e,n){return(/string|function/.test(typeof n)?p:l)(e,n)}function s(e,n){return"string"!=typeof e&&(n=typeof e,"number"===n?e+="":e="function"===n?s(e.call(e)):""),e}function i(e){return g[e]}function c(e){return s(e).replace(/&(?![\w#]+;)|[<>"']/g,i)}function u(e,n){if(h(e))for(var r=0,t=e.length;r<t;r++)n.call(e,e[r],r,e);else for(r in e)n.call(e,e[r],r)}function a(e,n){var r=/(\/)[^\/]+\1\.\.\1/,t=("./"+e).replace(/[^\/]+$/,""),o=t+n;for(o=o.replace(/\/\.\//g,"/");o.match(r);)o=o.replace(r,"/");return o}function l(e,n){var r=o.get(e)||f({filename:e,name:"Render Error",message:"Template not found"});return n?r(n):r}function p(e,n){if("string"==typeof n){var r=n;n=function(){return new v(r)}}var t=d[e]=function(r){try{return new n(r,e)+""}catch(e){return f(e)()}};return t.prototype=n.prototype=$,t.toString=function(){return n+""},t}function f(e){var n="{Template Error}",r=e.stack||"";if(r)r=r.split("\n").slice(0,2).join("\n");else for(var t in e)r+="<"+t+">\n"+e[t]+"\n\n";return function(){return"object"==typeof console&&console.error(n+"\n\n"+r),n}}var d=o.cache={},v=this.String,g={"<":"&#60;",">":"&#62;",'"':"&#34;","'":"&#39;","&":"&#38;"},h=Array.isArray||function(e){return"[object Array]"==={}.toString.call(e)},$=o.utils={$helpers:{},$include:function(e,n,r){return e=a(r,e),l(e,n)},$string:s,$escape:c,$each:u},m=o.helpers=$.$helpers;o.get=function(e){return d[e.replace(/^\.\//,"")]},o.helper=function(e,n){m[e]=n},t=function(){return o}.call(n,r,n,e),!(void 0!==t&&(e.exports=t))}()},function(e,n,r){function t(e){var n=$.trim(e.val()),r=!0,t="";return 0===n.length?(r=!1,t="手机号不能为空"):11!=n.length&&(r=!1,t="请输入有效的手机号码"),{st:r,errMsg:t}}function o(e,n,r){var t=!0,o="";i||(i=!0,$.ajax({url:s.PHP_PATH+"pub/user/"+_uid+"/smsverify",data:{sms:n,telephone:e,token:_token},dataType:"json",type:"POST",success:function(e){console.log(e),i=!1,200==e.status_code||(10004==e.status_code?(t=!1,o="验证码错误"):(t=!1,o=e.message)),r&&r({st:t,errMsg:o})},error:function(e){i=!1}}))}var s=r(1),i=!1;e.exports={checkPhone:t,checkAuthIsRight:o}}]);