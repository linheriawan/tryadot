<?php
require_once __DIR__ .'/_motel.php';
class Fakelib {
    private $tracer;
    function __construct(){
        $M = Motel::create("com.test.adot","My App Name");
        $this->tracer=$M->Tracer("http service",["library"=>"FakeLib"]);
        return $this;
    }
    function rewrite(array &$data){
        $id=$data['num'];
        $y=$this->tracer->Span("fake_lib for $id", "INTERNAL");
        if (array_key_exists('text',$data)){
            $data['text']=$this->reverse($data['text']);
            $y->setEvents(['reversing word'=>$data]);
        }
        $y->endSpan();
    }
    function reverse(string $text){
        return implode('',array_reverse(str_split($text)));
    }
    
}