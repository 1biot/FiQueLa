<?php

namespace FQL\Stream\Writers;

use OpenSpout\Writer\XLSX\Writer;

class XlsxWriter extends AbstractSpreadsheetWriter
{
    protected function createWriter(): Writer
    {
        return new Writer();
    }
}
