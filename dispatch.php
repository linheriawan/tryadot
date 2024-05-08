<?php // define('AMQP_DEBUG', true);
putenv("RABBIT_HOST=localhost");
putenv("RABBIT_PORT=5672");
putenv("RABBIT_USER=guest");
putenv("RABBIT_PASS=guest");

putenv("RABBIT_EXC=hellow");
putenv("RABBIT_KEYS=callsecond master_data.*");

$BASE_URL="https://localhost/tryadot/index.php";
require_once __DIR__."/_dlib.php";
require('_motel.php');
$W=new Console();

$EX=getenv("RABBIT_EXC");
$keys=explode(" ", getenv("RABBIT_KEYS"));
if(count($argv)>1){ $EX=$argv[1]; }
$errcount=0;
begin:
echo ($errcount>0?"re-":"")."Listeing Rabbit exchange '".$W->color("red")->text($EX)."'\n";
echo "with keys '".$W->color("magenta")->text(json_encode($keys))."'\n\n";

$T = Motel::create("CMPA","My Dispatch");
$TRC=$T->Tracer("dispatch",["dun"=>"dun"]);

try{
$CALLBACK=function ($M) use ($BASE_URL, $W, $TRC){  
  switch($M->getRoutingKey()){
    case "callsecond":
      $raw=$M->getBody();
      echo "received data is: ".$W->color("blue")->text($raw)."\n";
      $data=json_decode($raw);
      $parentCTXID=[$data->traceid,$data->spanid];
      $x=$TRC->Span('calling second process', "CONSUMER", $parentCTXID);
      
        $d=$x->currCtxID();
        $fattd=(object) FETT::to( "$BASE_URL/second?num=$data->num" )
          ->header($d)
          ->get();
        $f=$fattd->body;
        
        $x->setEvents(["fetch"=>[
          "url"=>"$BASE_URL/second?num=$data->num",
          "status"=>$fattd->status,
          "body"=>json_encode($f)
        ]]);
        $color=$f->traceid == $d["traceid"] ? "green": "red";
        $x->setAttr(["color-check"=>$color]);

        echo "current ids is: ".$W->color($color)->text(json_encode($d))."\n";
        echo "geting response($fattd->status) with content: \n".$W->color( $color )->text( json_encode($f) )."\n\n";
      
      $x->endSpan();
    break;
    default:
      echo "receive message with topic: ".$W->color("orange")->text($M->getRoutingKey())."\n";
      echo "the data is: ".$W->color("blue")->text($M->getBody())."\n\n";
    break;
  }
  
};

Rabbit_subs::subs(getenv("RABBIT_HOST"), getenv("RABBIT_PORT"), getenv("RABBIT_USER"), getenv("RABBIT_PASS"))
  ->exchange($EX) // fanout
  ->key($keys,'topic') // topic
  // ->key($keys,'routing') //routing
  ->start($CALLBACK);
}catch(Exception $e){ 
  $errcount=$errcount+1;
  $em=$e->getMessage();
  echo "ERROR:".$W->color("red")->text($em)."\n\n"; 
  if(strpos("PRECONDITION_FAILED - inequivalent arg 'type' for exchange",$em)==0){
    Rabbit_subs::subs(getenv("RABBIT_HOST"), getenv("RABBIT_PORT"), getenv("RABBIT_USER"), getenv("RABBIT_PASS"))
      ->rem_exchange($EX);
  }
  if($errcount<3){ goto begin; }
}
?>
