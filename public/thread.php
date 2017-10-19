<?php
class Total extends \Thread {
    public $name = '';
    private $total = 0;
    private $startNum = 0;
    private $endNum = 0;

    public function __construct($name, $startNum, $endNum){
        $this->name = $name;
        $this->startNum = $startNum;
        $this->endNum = $endNum;
    }
    public function run(){
        for($ix = $this->startNum; $ix < $this->endNum; ++$ix) {
            $this->total += $ix;
        }
        echo "Thread {$this->name} total: {$this->total} \r\n";
    }
    public function getTotal() {
        return $this->total;
    }
}

$num = 1000000000;
$threadNum = 100;
$setp = $num / $threadNum;
$startNum = 0;

$startTime = microtime(true);
for($ix = 0; $ix < $threadNum; ++$ix) {
    $endNum = $startNum + $setp;
    $thread = new Total($ix, $startNum, $endNum);
    $thread->start();
    $startNum = $endNum;
    $threads[] = $thread;
}

$total = 0;
foreach($threads as $thread) {
    $thread->join();
    $total += $thread->getTotal();
}

$endTime = microtime(true);
$time = $endTime - $startTime;

echo "total : {$total} time : {$time} \r\n";