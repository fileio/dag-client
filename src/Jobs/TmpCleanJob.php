<?php

namespace Gio\IijDagClient\Jobs;

use Gio\IijDagClient\DagAdapter;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TmpCleanJob extends Job implements SelfHandling, ShouldQueue
{
    const DELETE_TIME_BEFORE = 120; // sec

    use InteractsWithQueue, SerializesModels;

    public function __construct()
    {
    }

    /**
     * @param DagAdapter $adapter
     */
    public function handle(DagAdapter $adapter)
    {
        $adapter->deleteTmp(FileCreateJob::DELETE_TIME_BEFORE);
    }
}
