<?php
namespace phphound\output;

use phphound\AnalysisResult;

class JsonOutput extends AbstractOutput
{
    /**
     * @inheritdoc
     */
    public function result(AnalysisResult $result) : void
    {
        $this->cli->out(json_encode($result->toArray()));
    }
}
