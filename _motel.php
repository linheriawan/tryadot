<?php
putenv("EXPORTER=zipkin");
define("EXPORTER",getenv("EXPORTER"));

use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;

use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SemConv\ResourceAttributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;

use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
    
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\Stream\StreamTransportFactory;

use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;

use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;

require 'vendor/autoload.php';
use OpenTelemetry\SDK\Common\Dev\Compatibility\Util;
Util::setErrorLevel(Util::E_NONE);
class Motel{
    private $resource, $tracer, $logger, $meter;
    public static function create($NS,$name,$env='development',$version='0.0.0'){
        return new self($NS, $name, $env, $version);
    }
    public function __construct($NS,$name,$env='development',$version='0.0.0'){
        
        $this->resource = ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAMESPACE => $NS,
            ResourceAttributes::SERVICE_NAME => $name,
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT => $env,
            ResourceAttributes::SERVICE_VERSION => $version
        ])));
        
        // $this->logger = $this->setupLoger($resource);
        // $this->meter = $this->setupMeter($resource);
        Sdk::builder()
            // ->setTracerProvider($this->tracerPvd)
            // ->setMeterProvider($this->meter)
            // ->setLoggerProvider($this->logger)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();
        return $this;
    }
    public function Tracer($name,$attr=[],$version="1.0",$schema="https://opentelemetry.io/schemas/1.24.0"){
        switch(EXPORTER){
            case "adot":
                $transport = (new GrpcTransportFactory())
                    ->create('http://127.0.0.1:4317' . OtlpUtil::method(Signals::TRACE));
                $exporter = new SpanExporter($transport);
            break;
            case "zipkin":
                $transport = \OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory::discover()
                    ->create('http://localhost:9411/api/v2/spans', 'application/json');
                $exporter = new OpenTelemetry\Contrib\Zipkin\Exporter($name,$transport);
            break;
            default:
            break;
        }
        $spanProcessor=new SimpleSpanProcessor( $exporter );
        switch(EXPORTER){
            case "adot":
                $idGenerator = new IdGenerator();
                $detector = new OpenTelemetry\Aws\Ec2\Detector(new GuzzleHttp\Client(), new GuzzleHttp\Psr7\HttpFactory());
                $tracerProvider = new TracerProvider($spanProcessor, null, $detector->getResource(), null, $idGenerator);
                $propagator = new Propagator();
                $awssdkinstr = new OpenTelemetry\Aws\AwsSdkInstrumentationAwsSdkInstrumentation();
                $awssdkinstr->setPropagator($propagator);
                $awssdkinstr->setTracerProvider($tracerProvider);
            break;
            default:
                $sampler=new ParentBased(new AlwaysOnSampler());
                $tracerProvider=TracerProvider::builder()
                    ->addSpanProcessor( $spanProcessor )
                    ->setResource($this->resource)
                    ->setSampler($sampler)
                    ->build();
                Sdk::builder()->setTracerProvider($tracerProvider);
            break;
        }
        $this->tracer=$tracerProvider->getTracer( $name, $version, $schema, $attr );
        return $this;
    }
    private function setupLoger($resource){
        $logExporter = new LogsExporter(
            (new StreamTransportFactory())->create('php://stdout', 'application/json')
        );
        return LoggerProvider::builder()
            ->setResource($resource)
            ->addLogRecordProcessor(
                new SimpleLogRecordProcessor($logExporter)
            )
            ->build();        
    }
    private function setupMeter($resource){
        $reader = new ExportingReader(
            new MetricExporter(
                (new StreamTransportFactory())->create('php://stdout', 'application/json')
            )
        );
        
        return MeterProvider::builder()
            ->setResource($resource)
            ->addReader($reader)
            ->build();
    }

    public static function getParent($parent){
        [$trace,$span]=$parent;
        $pContext = TraceContextPropagator::getInstance()->extract(['traceparent' => [ "X" => "00-$trace-$span-01"] ]);
        return $pContext;
    }
    public function Span($name, string $kind=null, array $parent=[]){
        switch($kind){
            case "INTERNAL": $kind=SpanKind::KIND_INTERNAL; break;
            case "CLIENT": $kind=SpanKind::KIND_CLIENT; break;
            case "SERVER": $kind=SpanKind::KIND_SERVER; break;
            case "PRODUCER": $kind=SpanKind::KIND_PRODUCER; break;
            case "CONSUMER": $kind=SpanKind::KIND_CONSUMER; break;
        }
        return new SpanT($this->tracer, $name, $kind, $parent);
    }
}
class SpanT{
    private $span,$scope;
    public function __construct($tracer, $name, $kind, $parent){
        $B=$tracer->spanBuilder($name);
        if($kind!=null){ $B=$B->setSpanKind($kind); }
        if(!empty($parent)){ $B=$B->setParent(Motel::getParent($parent)); }
        $this->span=$B->startSpan();
        return $this; 
    }
    
    public function scope(){ $this->scope=$this->span->activate(); return $this;}
    public function endSpan(){
        if($this->scope!=null)$this->scope->detach();
        $this->span->end(); 
    }
    public function setAttr($attr=[]){ 
        if(!empty($attr)) {
            foreach($attr as $k=>$v){ 
                $this->span=$this->span->setAttribute($k, $v); 
            } 
        }
        return $this; 
    }
    public function setEvents($events){ 
        if(!empty($events)) {
            foreach($events as $k=>$v){ 
                if(!empty($v)){
                    $this->span=$this->span->addEvent($k, Attributes::create($v));    
                }else{ $this->span=$this->span->addEvent($k); }
            }
        }
        return $this;
    }
    public function setStatus($msg,$level="Error"){
        switch($level){
            case "Unset": $level=\OpenTelemetry\API\Trace\StatusCode::STATUS_UNSET; break;
            case "Ok": $level=\OpenTelemetry\API\Trace\StatusCode::STATUS_OK; break;
            default: $level=\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR; break;
        }
        $this->span->setStatus($level, $msg);
    }
    public function setExc($t, $array){
        $this->span->recordException($t, $array);
    }
    
    public function currCtxID(){
        $c=$this->span->getContext();
        return ["traceid"=>$c->getTraceId(),"spanid"=>$c->getSpanId()];
    }
}