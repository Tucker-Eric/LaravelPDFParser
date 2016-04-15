<?php

namespace LaravelPDFParser;

use Illuminate\Support\ServiceProvider;

class PDFParserServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('pdfparser', function () {
            return new Parser;
        });
    }
}