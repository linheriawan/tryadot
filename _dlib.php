<?php
class loadLib{
    private $_Ext, $_NSseparator, $_NameSpace, $_Path;
    public function __construct($includePath){
        $this->_Ext = '.php';
        $this->_NSseparator = '\\';
        $this->_Path = $includePath;
        $this->loadnonclass($includePath);
    }
    private function loadnonclass($dir){
        foreach(scandir($dir) as $r){
            if(in_array($r,['.DS_Store',]) ){ unlink("$dir/$r"); }
            else if(!in_array($r,['..', '.','index.html']) ){
                 if( is_dir("$dir/$r") ){ $this->loadnonclass("$dir/$r"); }    
                 else{  $fn=explode(".",$r);
                    if(count($fn)==2 && $fn[1]=="php"){
                        $fc=file_get_contents("$dir/$r");
                        $p=strpos($fc,"class $fn[0]")==false;
                        if (strpos($fc,"class $fn[0]") ==false
                        && strpos($fc,"interface $fn[0]") ==false
                        && strpos($fc,"trait $fn[0]") ==false ){
                            require "$dir/$r"; 
                        }
                    }
                }
            }
        }
    }
    static function from($includePath){ return new self($includePath);}
    public function as($ns=NULL){
        $this->_NameSpace = $ns==NULL ? "" : $ns;
        $this->register();
    }
    private function register(){ spl_autoload_register(array($this, 'require_class')); }
    private function unregister() { spl_autoload_unregister(array($this, 'require_class')); }
    private function require_class($className){
        $NSwithSeparator=$this->_NameSpace.$this->_NSseparator;
        $ClassNS=substr( $className, 0, strlen($NSwithSeparator));
        if( $this->_NameSpace === null || $ClassNS === $NSwithSeparator ) {
            $className=str_replace($this->_NameSpace,"",$className);
            $namespace=explode($this->_NSseparator, $className);
            $file=array_pop($namespace).$this->_Ext;
            $_NSpath=implode(DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR;            
            $filePath = $this->_Path.$_NSpath.$file;
            $load=file_exists($filePath) ? $filePath : $this->_Path.$file;
            require $load; 
        }
    }
}
function vdum(...$x){
    foreach ($x as $i) {
      ob_start(); var_dump($i); $d=ob_get_contents(); ob_end_clean();
      $em= preg_replace_callback('/(\]=>\n\s*string.*?\s*")(.+?)("\n\s*\[|"\n\s+})/s',
        function ($m) {
          if(strpos($m[2],"=>")){
            $mx= preg_replace('/=>\n\s+/'," => ",$m[2]);
            $r="$m[1]$mx$m[3]";
            return preg_replace('/ (".*?")/', " <span style='color:blue'>$1</span>", $r);
          } else{ return "$m[1]<span style='color:blue'>$m[2]</span>$m[3]"; }
        }, $d);
      $em= preg_replace('/=>\n\s+/'," => ",$em);
      $em= preg_replace('/=>\n\t*/'," => ",$em);
      $em= preg_replace('/\] => \"\"\n/'," => ",$em);
      $em= preg_replace('/(\{\n\s+\})/'," {}\n",$em);
      $em= preg_replace('/(\[".*?":*.*\])/', "<b style='color:green'>".'$1'."</b>",$em);
      echo "<pre>$em</pre>";
    }
}
class Console{
    private $CLR=[
        'reset'     => [0,0],
        'red'       => [91, 101],
        'green'     => [92, 101],
        'orange'    => [93, 103],
        'blue'      => [94, 104],
        'magenta'   => [95, 105],
        'cyan'      => [96, 106],
        'white'     => [97, 107],
    ];
    private $bgcolor,$color,$bold;
    function __construct(){  $this->reset(); }
    function reset(){ 
        $this->bgcolor=$this->CLR['reset'][1];
        $this->color=$this->CLR['reset'][0];
        $this->bold=false;
    }
    function bold($b){$this->bold=$b;return $this;}
    function color($c){$this->color=$this->CLR[$c][0];return $this;}
    function setbg($c){$this->bgcolor=$this->CLR[$c][1];return $this;}
    
    function bg($i){ return "\e[$this->bgcolor."."m$i\033[0m";}
    function text($i){ 
        $i=gettype($i)=="string" ? $i : json_encode($i);
        if($this->bold){
            return "\e[1;$this->color"."m$i\033[0m";
        }else{
            return "\e[0;$this->color"."m$i\033[0m";
        }
    }
}
class Fett{
    private $URL,$HEADER,$OPTION;
    function __construct($url){
        $this->URL=$url;
        $this->HEADER=[
            "User-Agent"=>"Fett/1.0",
            "Cache-Control"=>"no-cache",
            "Content-Type"=>"application/json",
            "Accept"=>"application/json",
        ];
        $this->OPTION=["timeout"=>30,"checkssl"=>0];
    }
    static function to($url) { 
      $url=explode("?",$url);
      $q=ISSET($url[1])? "?".str_replace(" ","+",$url[1]): ""; 
      return new self("$url[0]$q"); 
    }
    private function getHeaderValue($p){ return $this->HEADER[$p]; }
    private function getHeader(){
        $t=[];
        foreach($this->HEADER as $k=>$v){ array_push($t,"$k: $v"); }
        return $t;
    }
    private function jsonify($j){ return !empty(json_decode($j)) ? json_decode($j) : $j; }
    public function header($header){
        $this->HEADER=array_merge($this->HEADER,$header); return $this;
    }
    public function options($opts){
        $this->OPTION=array_merge($this->OPTION,$opts); return $this;
    }
    public function get(){
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->URL);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->OPTION["timeout"]);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeader());
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->OPTION["checkssl"]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->OPTION["checkssl"]);
            $ex = curl_exec($ch);
            // var_dump($ex );
            if ($ex === false) { throw new Exception(curl_error($ch), curl_errno($ch)); }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($this->getHeaderValue("Accept")=="application/json"){$ex=$this->jsonify($ex);}
            return (object)["status"=>$httpCode,"body"=>$ex];
        } catch(Exception $e) {
            trigger_error( sprintf( 'Curl failed with error #%d: %s', $e->getCode(), $e->getMessage()), E_USER_ERROR);
        } finally {
            if (is_resource($ch)) { curl_close($ch); }
        }
    }
    public function post($data){
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->URL);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            if(gettype($data)=="array" && $this->getHeaderValue("Content-Type")=="application/json"){
                $data=json_encode($data);
            }else{
                $data=http_build_query($data);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->OPTION["timeout"]);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeader());
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->OPTION["checkssl"]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->OPTION["checkssl"]);
            $ex = curl_exec($ch);
            if ($ex === false) { throw new Exception(curl_error($ch), curl_errno($ch)); }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($this->getHeaderValue("Accept")=="application/json"){$ex=$this->jsonify($ex);}
            return (object)["status"=>$httpCode,"body"=>$ex];
        } catch(Exception $e) {
            trigger_error( sprintf( 'Curl failed with error #%d: %s', $e->getCode(), $e->getMessage()), E_USER_ERROR);
        } finally {
            if (is_resource($ch)) { curl_close($ch); }
        }
    }
    public function put($data){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->URL);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->OPTION["timeout"]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeader());
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->OPTION["checkssl"]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->OPTION["checkssl"]);
        $ex = curl_exec($ch);
        if ($ex === false) { throw new Exception(curl_error($ch), curl_errno($ch)); }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($this->getHeaderValue("Accept")=="application/json"){$ex=$this->jsonify($ex);}
        return (object)["status"=>$httpCode,"body"=>$ex];
    }
    public function patch($data){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->URL);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->OPTION["timeout"]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeader());
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->OPTION["checkssl"]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->OPTION["checkssl"]);
        $ex = curl_exec($ch);
        if ($ex === false) { throw new Exception(curl_error($ch), curl_errno($ch)); }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($this->getHeaderValue("Accept")=="application/json"){$ex=$this->jsonify($ex);}
        return (object)["status"=>$httpCode,"body"=>$ex];
    }
    public function DELETE(){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->URL);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->OPTION["timeout"]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeader());
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        // curl_setopt($ch, CURLOPT_HEADER, TRUE);
        // curl_setopt($ch, CURLOPT_NOBODY, TRUE); // remove body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->OPTION["checkssl"]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->OPTION["checkssl"]);
        $ex = curl_exec($ch);
        if ($ex === false) { throw new Exception(curl_error($ch), curl_errno($ch)); }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($this->getHeaderValue("Accept")=="application/json"){$ex=$this->jsonify($ex);}
        return (object)["status"=>$httpCode,"body"=>$ex];
    }
}
class Rest{
  public $Attr=[];
  public function __construct(){
		$sv["method"]=$_SERVER["REQUEST_METHOD"];
		$sv["query"]=array_key_exists("QUERY_STRING",$_SERVER) ? $_SERVER["QUERY_STRING"] :[];
		$sv["issamesite"]=ISSET($_SERVER["HTTP_SEC_FETCH_SITE"])?$_SERVER["HTTP_SEC_FETCH_SITE"]: "none";
		$sv["extnl"]=ISSET($_SERVER["HTTP_SEC_FETCH_MODE"])?$_SERVER["HTTP_SEC_FETCH_MODE"]: "cors";
		$sv["req_cont_type"]=ISSET($_SERVER["CONTENT_TYPE"])?$_SERVER["CONTENT_TYPE"]:"";
		if(in_array('application/json',explode(';',$sv["req_cont_type"]))){
			$rd=file_get_contents('php://input');
			$_POST=empty($_POST)?json_decode($rd):$_POST;
			if($_POST==NULL && !empty($rd)){ parse_str(urldecode($rd), $_POST); }
		}
		$sv["post"]=$_POST;
		$sv["get"]=$_GET;
        $sv["header"]=getallheaders();
		$sv["uri"]=[];
		$sc=[];
		if(ISSET($_SERVER["PATH_INFO"])){  $sc=explode("/",$_SERVER["PATH_INFO"]);  }
		for($x=array_search("index.php",$sc)+1;$x<count($sc);$x++){ array_push($sv["uri"],$sc[$x]); }
		$this->Attr=$sv;
  }
  function proc(){ return implode("/",$this->Attr["uri"]).":".$this->Attr["method"]; }
  function get($v=""){
    if($v==""){ return $this->Attr["get"]==NULL?[]:$this->Attr["get"]; }
    else{ $x=(array)$this->Attr["get"];
        return array_key_exists($v,$x)?$x[$v]:false;
    }
  }
  function post($v=""){
    if($v==""){ return $this->Attr["post"]==NULL?[]:$this->Attr["post"]; }
    else{ $x=(array)$this->Attr["post"];
        return array_key_exists($v,$x)?$x[$v]:false;
    }
  }
  function header($v=""){
    if($v==""){ return $this->Attr["header"]==NULL?[]:$this->Attr["header"]; }
    else{ $x=(array)$this->Attr["header"];
        return array_key_exists($v,$x)?$x[$v]:false;
    }
  }
  function fromPost(&$data, $full=true){
    foreach($data as $k=>$r){
      $val= ($this->post($r)==false) ? null : $this->post($r);
      if(is_int($k)){ unset($data[$k]); $k=$r;}
			if($full && $val==null){  $this->json(["'$k' is required"],400); }
			if($full==false && $val==null){  unset($data[$k]); }
			else{ $a=&$data[$k]; $a=$val; }
    }
  }
  function fromGet(&$data, $full=true){
    foreach($data as $k=>$r){
			$val=($this->get($r)==false) ? null : $this->get($r);
      if(is_int($k)){ unset($data[$k]); $k=$r;}
      else if($full && $val==null){ $this->json("'$k' is required",400); }
			if($full==false && $val==null){  unset($data[$k]); }
			else{ $a=&$data[$k]; $a=$val; }
    }
  }
  public function json($data,$code=200){
      if (ob_get_contents()) {ob_end_clean(); } http_response_code($code);
      ob_start();
			if(gettype($data)=="array"){
				$data=json_encode($data);
				header('Content-Type: application/json; charset=utf-8');
			}
			header('Content-Length: '.strlen($data));
			echo $data;
      ob_end_flush(); //die();
  }
  public function text($data,$code=200){
      if (ob_get_contents()) {ob_end_clean(); } http_response_code($code);
      ob_start();
      if(gettype($data)=="array"){ $data=json_encode($data); }
      header('Content-Type: text/plain; charset=utf-8');
      header('Content-Length: '.strlen($data));
      echo $data;
      ob_end_flush(); //die();
  }
}

// define('AMQP_DEBUG', true);
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Message\AMQPMessage;
class Rabbit_subs{
    private $connection, $channel, $EXNAME, $MODE,$ROUTING_KEY;
    function __construct($host, $port, $user, $pass){
        $this->connection = new AMQPSSLConnection($host, $port, $user, $pass, '/', ['verify_peer' => false]);
        $this->channel = $this->connection->channel();
        $this->MODE='basic';
    }
    static function subs($host, $port, $user, $pass){return new self($host, $port, $user, $pass);}
    function rem_exchange($EXNAME){$this->channel->exchange_delete($EXNAME);}
    function exchange($EXNAME, ){ $this->EXNAME=$EXNAME;  return $this;  }
    function key($keys,$m='basic'){
        $this->MODE=$m; 
        $this->ROUTING_KEY=$keys; 
        return $this; 
    }
    function start($CALLBACK){
        // try{
            switch($this->MODE){
                case 'topic': 
                    $this->channel->exchange_declare($this->EXNAME, 'topic', false, false, false);
                    if($this->ROUTING_KEY==NULL){$this->ROUTING_KEY=["info.*"];}
                break;
                case 'routing':
                    $this->channel->exchange_declare($this->EXNAME, 'direct', false, false, false);
                    if($this->ROUTING_KEY==NULL){$this->ROUTING_KEY=["info"];}
                break;
                default: // fanout
                    $this->channel->queue_declare($this->EXNAME, 'fanout', false, false, false);
                break;
            }

            list($queue_name, ,) = $this->channel->queue_declare("", false, false, true, false);

            if(in_array($this->MODE,['topic', 'routing'])){
                foreach ($this->ROUTING_KEY as $K) {
                    $this->channel->queue_bind($queue_name, $this->EXNAME, $K);
                }
            }else{
                $this->channel->queue_bind($queue_name, $this->EXNAME);
            }
            
            $this->channel->basic_consume($queue_name, '', false, true, false, false, $CALLBACK);
            while ($this->channel->is_open()) { $this->channel->wait(); }
            $this->channel->close();
            $this->connection->close();
        // }catch(Exception $e){ 
        //     $em=$e->getMessage();
        //     echo "Error $em"; 
        //     if(strpos("PRECONDITION_FAILED - inequivalent arg 'type' for exchange",$em)==0){
        //         $this->rem_exchange($this->EXNAME);
        //         $this->exchange($this->EXNAME)->start($CALLBACK);
        //     }
        // }
    }
}
class Rabbit_pubs{
    private $connection, $channel, $exchange, $mode, $routekey;

    function __construct($host, $port, $user, $pass){
        $this->connection = new AMQPSSLConnection($host, $port, $user, $pass,'/', ['verify_peer' => false]);
        $this->channel = $this->connection->channel();
        $this->mode='direct';
    }
    static function pub($host, $port, $user, $pass){ return new self($host, $port, $user, $pass); }
    private function setMessage($hello){
        $msg=$hello=="string" ? $hello : json_encode($hello);
        return new AMQPMessage( $msg );
    }
    private function done($msg=""){
        $this->channel->close();
        $this->connection->close();
        $msg=$msg=="string" ? $msg : json_encode($msg);
        // vdum( "msg: $msg is sent" );
    }
    function exchange($ex){$this->exchange=$ex;  return $this; }
    function key($k,$m='basic'){$this->mode=$m; $this->routekey=$k; return $this; }
    function send($hello){ 
        try{
        $msg = $this->setMessage($hello);
        switch($this->mode){
            case 'topic':
            $this->channel->exchange_declare($this->exchange, 'topic', false, false, false);
            if($this->routekey==NULL){$this->routekey="info.else";}
            $this->channel->basic_publish($msg, $this->exchange, $this->routekey);
            break;
            case 'routing':
            $this->channel->exchange_declare($this->exchange, 'direct', false, false, false);    
            if($this->routekey==NULL){$this->routekey="info";}
            $this->channel->basic_publish($msg, $this->exchange, $this->routekey);
            break;
            default: //fanout
            $this->channel->queue_declare($this->exchange, 'fanout', false, false, false);
            $this->channel->basic_publish($msg, '', $this->exchange);
            break;
        } $this->done($hello);
        } catch(Exception $e){ throw new Exception( "Error(Rabbit): {$e->getMessage()}");}
    }
    
}
?>