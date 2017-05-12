<?php

namespace LaravelPDFParser;

use Illuminate\Support\ServiceProvider;

class PDFParserServiceProvider extends ServiceProvider
{
    protected $defer = true;

    public function register()
    {
        $this->app->bind('pdfparser', function () {
            return new Parser;
        });
    }

    public function provides(): array
    {
        return ['pdfparser'];
    }
}
