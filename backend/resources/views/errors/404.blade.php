<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">`
    <head>
        <meta charset="utf-8">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="robots" content="noindex, follow">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
        <title>{{  __('Not Found') }}</title>
        <style>.notfound .notfound-404 h1,.notfound h2{text-transform:uppercase;font-family:Roboto,sans-serif}*{-webkit-box-sizing:border-box;box-sizing:border-box}body{padding:0;margin:0}#notfound{position:relative;height:100vh;background:#f6f6f6}#notfound .notfound{position:absolute;left:50%;top:50%;-webkit-transform:translate(-50%,-50%);-ms-transform:translate(-50%,-50%);transform:translate(-50%,-50%)}.notfound{max-width:767px;width:100%;line-height:1.4;padding:110px 40px;text-align:center;background:#fff;-webkit-box-shadow:0 15px 15px -10px rgba(0,0,0,.1);box-shadow:0 15px 15px -10px rgba(0,0,0,.1)}.notfound .notfound-404{position:relative;height:180px}.notfound .notfound-404 h1{position:absolute;left:50%;top:50%;-webkit-transform:translate(-50%,-50%);-ms-transform:translate(-50%,-50%);transform:translate(-50%,-50%);font-size:165px;font-weight:700;margin:0;color:#262626}.notfound .notfound-404 h1>span{color:#00b7ff}.notfound h2{font-size:22px;font-weight:400;color:#151515;margin-top:0;margin-bottom:25px}.notfound .notfound-search{position:relative;max-width:320px;width:100%;margin:auto}.notfound .notfound-search>input{font-family:Roboto,sans-serif;width:100%;height:50px;padding:3px 65px 3px 30px;color:#151515;font-size:16px;background:0 0;border:2px solid #c5c5c5;border-radius:40px;-webkit-transition:.2s;transition:.2s}.notfound .notfound-search>button:hover>span:after,.notfound .notfound-search>input:focus{border-color:#00b7ff}.notfound .notfound-search>button{position:absolute;right:15px;top:5px;width:40px;height:40px;text-align:center;border:none;background:0 0;padding:0;cursor:pointer}.notfound .notfound-search>button>span{width:15px;height:15px;position:absolute;left:50%;top:50%;-webkit-transform:translate(-50%,-50%) rotate(-45deg);-ms-transform:translate(-50%,-50%) rotate(-45deg);transform:translate(-50%,-50%) rotate(-45deg);margin-left:-3px}.notfound .notfound-search>button>span:after{position:absolute;content:'';width:10px;height:10px;left:0;top:0;border-radius:50%;border:4px solid #c5c5c5;-webkit-transition:.2s;transition:.2s}.notfound-search>button>span:before{position:absolute;content:'';width:4px;height:10px;left:7px;top:17px;border-radius:2px;background:#c5c5c5;-webkit-transition:.2s;transition:.2s}.notfound .notfound-search>button:hover>span:before{background-color:#00b7ff}@media only screen and (max-width:767px){.notfound h2{font-size:18px}}@media only screen and (max-width:480px){.notfound .notfound-404 h1{font-size:141px}}</style>
    </head>

    <body>

        <div id="notfound">
            <div class="notfound">
                <div class="notfound-404">
                    <h1>4<span>0</span>4</h1>
                </div>
                <h2>the page you requested could not found</h2>
            </div>
        </div>

    </body>
</html>