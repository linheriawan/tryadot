<?php
putenv("RABBIT_HOST=localhost");
putenv("RABBIT_PORT=5672");
putenv("RABBIT_USER=guest");
putenv("RABBIT_PASS=guest");

require_once __DIR__ .'/_motel.php';
require_once __DIR__ ."/_dlib.php";
require_once __DIR__ .'/fakelib.php';

$PUB=Rabbit_pubs::pub(getenv("RABBIT_HOST"), getenv("RABBIT_PORT"), getenv("RABBIT_USER"), getenv("RABBIT_PASS"))
    ->exchange('hellow');

$M = Motel::create("com.test.adot","My App Name");
$TRC=$M->Tracer("http service",["gung"=>"gang"]);
$R=new Rest();
try{
	switch($R->proc()){
		case ":GET":
            $data=[ "num"=>rand(1, 100), "text"=>"helloW" ];
            $x=$TRC->Span('init process',"CLIENT")
                ->scope()
                ->setAttr(["number"=>$data["num"]]);
            try{
                $data=array_merge($data, $x->currCtxID() );
                $PUB->key("callsecond",'topic')->send($data);
                
                (new Fakelib())->rewrite($data);

                // $c=1/0; // move this error before sub process is executed
            }catch(\Throwable $t){
                $x->setStatus($t->getMessage());
                $x->setExc($t, ['exception.myreason' => 'testing handling']);
            }finally{
                $x->endSpan();
                $R->json($data);
            }
		break;
		case "second:GET":
			$num=$R->get("num");
            $h=$R->header();
            $parentCTXID=[$h["traceid"], $h["spanid"]];
            $x=$TRC->Span("get /second?num=$num", "SERVER", $parentCTXID)
                    ->setAttr(["num"=>$num]);
            try{
                $data=$x->currCtxID();
                $data["caller_tid"]=$parentCTXID[0];
                $data["caller_sid"]=$parentCTXID[1];
                $data["num"]=$num/0;
                $R->json($data);
            }catch(\Throwable $e){ 
                $x->setStatus($e->getMessage());
                $x->setExc($e, ['exception.what' => 'my mistake']);
                $R->json($e,400);
            }finally{
                $x->endSpan();
            }
		break;
		default:
			[$m,$t]=explode(":",$R->proc());
            $c=[ "msg"=>"'$m' not found on $t method",
                "OTEL_PHP_AUTOLOAD_ENABLED"=>getenv('OTEL_PHP_AUTOLOAD_ENABLED'),
                "OTEL_TRACES_EXPORTER"=>getenv('OTEL_TRACES_EXPORTER'),
                "OTEL_EXPORTER_OTLP_PROTOCOL"=>getenv('OTEL_EXPORTER_OTLP_PROTOCOL'),
                "OTEL_METRICS_EXPORTER"=>getenv('OTEL_METRICS_EXPORTER'),
                "OTEL_EXPORTER_OTLP_METRICS_PROTOCOL"=>getenv('OTEL_EXPORTER_OTLP_METRICS_PROTOCOL'),
                "OTEL_EXPORTER_OTLP_ENDPOINT"=>getenv('OTEL_EXPORTER_OTLP_ENDPOINT'),
                "OTEL_PHP_TRACES_PROCESSOR"=>getenv('OTEL_PHP_TRACES_PROCESSOR')];
			$R->json( $c, 404);
		break;
	}
	
}catch(Exception $e){ $R->text( $e->getMessage(), 500 ); }
?>
