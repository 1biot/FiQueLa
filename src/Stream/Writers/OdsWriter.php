<?php

namespace FQL\Stream\Writers;

use OpenSpout\Writer\ODS\Writer;

class OdsWriter extends AbstractSpreadsheetWriter
{
    protected function createWriter(): Writer
    {
        return new Writer();
    }
}
