<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no">
    <title>给料</title>
    <style>
        body{margin:0;}
        a:hover {text-decoration: none;}
        * {box-sizing: border-box;}
        input,button,select,textarea{outline:none;-webkit-appearance:none;}
        input[type=radio] {-webkit-appearance:radio;}
        h1{
            font-size: 15px;
            text-align: left;
            margin-top: 50px;
            color: #020202;
        }
        p{
            font-size: 15px;
            text-align: center;
            margin-top:20px;
        }
        .box-wrap{
            width: 90%;
            margin: 0 auto;
        }
        .zfb{
            position: absolute;
            bottom: 0px;
            right: 0px;
        }
        .code img{
            width: 209px;
            height: 209px;
            display: block;
            margin: 0 auto;
        }
        .logo-wrap {
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            align-items: center;
            height: 50px;
            padding-top: 5px;
            font-size: 12px;
            color: #909090;
            background-color: #f8f8f8;

        }
        .logo {
            width: 25px;
            height: 29px;
        }
        .bg-gap img {
            display: block;
            width: 100%;
        }
    </style>
</head>
<body>
<div class="logo-root"><div class="logo-wrap"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAA6CAYAAADybArcAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA3ZpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMTMyIDc5LjE1OTI4NCwgMjAxNi8wNC8xOS0xMzoxMzo0MCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDo3ODIzNzA3Zi1jOTJkLTVkNDgtYjc2OS1jNGEzOTRjYzQzY2UiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6RjIxNEYxQzFCMDgxMTFFNzlFNzlEODM0MjlBQzc5NjciIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6RjIxNEYxQzBCMDgxMTFFNzlFNzlEODM0MjlBQzc5NjciIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENDIDIwMTUgKFdpbmRvd3MpIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6NDkyZTZjYjYtNzhjMC1iYjQ5LThkZmQtYWRmYzI0NDkzYWY1IiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjc4MjM3MDdmLWM5MmQtNWQ0OC1iNzY5LWM0YTM5NGNjNDNjZSIv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" class="logo"><span>资源变现工具</span></div><div class="bg-gap"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAu4AAAAZCAIAAADhdzwVAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA3ZpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMTMyIDc5LjE1OTI4NCwgMjAxNi8wNC8xOS0xMzoxMzo0MCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDo3ODIzNzA3Zi1jOTJkLTVkNDgtYjc2OS1jNGEzOTRjYzQzY2UiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6NUJGRjBDQjFCMDgyMTFFN0FFN0NGMDAxMzBCMEQyNTEiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6NUJGRjBDQjBCMDgyMTFFN0FFN0NGMDAxMzBCMEQyNTEiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENDIDIwMTUgKFdpbmRvd3MpIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6NDkyZTZjYjYtNzhjMC1iYjQ5LThkZmQtYWRmYzI0NDkzYWY1IiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjc4MjM3MDdmLWM5MmQtNWQ0OC1iNzY5LWM0YTM5NGNjNDNjZSIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/Pkl+3RgAAAHTSURBVHja7NjbcoIwFEBRFP7/j5U6OtOHEA4RkFvWenAi0E6LAba5PR6Pvu+fz2f/1gAAHNjt7X6/fwbda/Ta2rbtZ3ef46wBAHtVSyI5psv+TLJR3wAAu1dLVlf+2/UNALBjtcxPGX0DAOxeLSunjL4BALasli1SprxvJA4ASJZzpMzyxPlsNHsAYLNndLZaDvindsc8fXHiJLljwgHAkmfu/5P3yMlyppSZkTjDuFE5AJA8QIfhcoH/rrvGJzT2eSSVk4wB4GK9MlxcuUavXDxl5lVONnEayzkAHDtWmtwqS82npTMnxion2zdCB4DtY6WpYHFFyqw/meJ5E1SO1gEgfriMDZAyO4TzWOgM40brANRWKo1lFSlz9tkcHDMMHbkDcLpMaaypSJmar4rJqR9UjuIBWP3LZ9ArSBmWXmDlxRO8AtQcKNlXpAynKZ44cXQPcLo6EShIGdFT2j0lAQSw5I40WSogZfhh90zWjwCC2qJElyBlqKJ+gtYpHABrXbyzByBlcA9ddFuMQyc7HjsAznjtjF1H8QFCBCkD14mhuHu+2jX5ljpT46u35bsAKQPTX3N/Ydg32eIp37jK3sMWWPlnER85Y2/hRoUBe/kTYAA0GggTSaM4nQAAAABJRU5ErkJggg=="></div></div>
<div class="box-wrap">

    <h1>关注【给料消息助手】订阅更新消息</h1>
    <p>
        由于种种原因，新给料的消息推送将会在备用号
        【给料消息助手】重新开启，烦请您长按扫描二维码，
        关注公众号接受消息推送。
    </p>
    <div class="code">
        <img src="https://yxapi.qiudashi.com/image/subscribe_code.png" alt="">
    </div>
</div>

</body>
</html>
