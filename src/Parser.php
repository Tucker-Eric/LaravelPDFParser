<?php

namespace LaravelPDFParser;

use Smalot\PdfParser\Parser as BaseParser;
use Storage;

class Parser extends BaseParser
{
    public function parseFile($filename, $firstTry = true)
    {
        try {
            $pdf = parent::parseFile($filename);
        } catch (\Exception $e) {
            //If this is a secure pdf we try unsecure it and try again
            if ($firstTry && $e->getMessage() === 'Secured pdf file are currently not supported.') {
                // Relative filepath for Storage Facade
                $file = $this->storeFileLocally($filename);
                // Fullpath for gs command
                $fullPath = $this->getLocalFilePath($file);
                // Fullpath of filename for gs output
                $unsecured = str_replace(basename($fullPath), 'u-'.basename($fullPath), $fullPath);
                // Run the command
                shell_exec("gs -q -dNOPAUSE -dBATCH -dNumRenderingThreads=2 -dNOGC -sDEVICE=pdfwrite -sOutputFile=$unsecured -c .setpdfwrite -f $fullPath");
                // Call this function again and hopefully return $pdf
                $pdf = $this->parseFile($unsecured, false);
                // Clean up after ourselves when we are done
                Storage::disk('local')->deleteDirectory(dirname($file));
            } else {

                if (! $firstTry) {
                    // Clean up after ourselves when we are done
                    Storage::disk('local')->deleteDirectory(dirname($filename));
                }

                throw new \Exception('File Not Recognized After Trying To Remove Security');
            }
        }

        return $pdf;
    }

    public function storeFileLocally($filename)
    {
        $file = 'tmp/'.md5(microtime()).'/'.basename($filename);
        $fs = Storage::disk('local')->getDriver();
        $stream = $fs->readStream(str_replace(config('filesystems.disks.local.root'), '', $filename));
        Storage::disk('local')->put($file, $stream);
        fclose($stream);

        return $file;
    }

    public function getLocalFilePath($file = '')
    {
        return config('filesystems.disks.local.root').'/'.$file;
    }
}