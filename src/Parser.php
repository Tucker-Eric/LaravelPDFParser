<?php

namespace LaravelPDFParser;

use Illuminate\Http\UploadedFile;
use LaravelPDFParser\Exceptions\EncryptedPDFNeedsPasswordException;
use LaravelPDFParser\Exceptions\IncorrectPDFPasswordException;
use Smalot\PdfParser\Parser as BaseParser;
use Storage;

class Parser extends BaseParser
{
    public function parseFile($filename, $password = '')
    {
        try {
            $pdf = parent::parseFile($filename);
        } catch (\Exception $e) {
            //If this is a secure pdf we try unsecure it and try again
            if ($e->getMessage() === 'Secured pdf file are currently not supported.') {
                // Relative filepath for Storage Facade
                $file = $this->storeFileLocally($filename);
                // Fullpath for gs command
                $fullPath = $this->getLocalFilePath($file);
                // Fullpath of filename for gs output
                $unsecured = str_replace(basename($fullPath), 'u-'.basename($fullPath), $fullPath);
                // Run the command
                // This will return false if we can't unsecure the file
                $this->gsRewrite($fullPath, $unsecured, $password);
                // Call this function again and hopefully return $pdf
                $pdf = parent::parseFile($unsecured);
                // Clean up after ourselves when we are done
                Storage::disk('local')->deleteDirectory(dirname($file));
            } else {
                // Clean up after ourselves when we are done
                Storage::disk('local')->deleteDirectory(dirname($filename));
                throw $e;
            }
        }

        return $pdf;
    }

    /**
     * Store this file locally so we can do ghostscript stuff to it
     * @param $filename
     * @return string
     */
    public function storeFileLocally($filename)
    {
        $file = 'tmp/'.md5(microtime()).'/'.basename($filename);
        $stream = fopen($filename, 'r');
        Storage::disk('local')->put($file, $stream);
        fclose($stream);

        return $file;
    }

    /**
     * Returns fully qualified directory root of our local storage
     *
     * @param string $file
     * @return string
     */
    public function getLocalFilePath($file = '')
    {
        return config('filesystems.disks.local.root').'/'.$file;
    }

    /**
     * Rewriting for a pdf if it is encrypted to remove encryption
     *
     * @param $original
     * @param $new
     * @param string $password
     * @throws EncryptedPDFNeedsPasswordException
     * @throws IncorrectPDFPasswordException
     */
    public function gsRewrite($original, $new, $password = '')
    {
        $cmd = 'gs -q -dNOPAUSE -dBATCH -dNumRenderingThreads=2 -dNOGC -sDEVICE=pdfwrite'
            ." -sPDFPassword=$password -sOutputFile=$new -c .setpdfwrite -f $original";
        $resp = shell_exec(escapeshellcmd($cmd));

        if (strpos($resp, 'Error: /invalidfileaccess in pdf_process_Encrypt') !== false) {
            $message = $password === '' ? 'Password Required To Unencrypt PDF' : 'Incorrect Password '.$password;
            throw new IncorrectPDFPasswordException($message);
        }
    }


    public function removePassword($file, $password)
    {
        // Create a new file
        $newFile = $this->getLocalFilePath($this->storeFileLocally($file));
        // Overwrite our original with password removed
        $this->gsRewrite($newFile, $file, $password);
        unlink($newFile);
        rmdir(dirname($newFile));

        return $file;
    }
}