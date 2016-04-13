<?php

namespace LaravelPDFParser;

use Illuminate\Support\Facades\Facade;

class PDFParser extends Facade
{
    protected  static function getFacadeAccessor()
    {
        return 'pdfparser';
    }
}