<?php

namespace LaravelPDFParser;

use Illuminate\Http\UploadedFile;
use LaravelPDFParser\Exceptions\EncryptedPDFNeedsPasswordException;
use LaravelPDFParser\Exceptions\IncorrectPDFPasswordException;
use Smalot\PdfParser\Parser as BaseParser;
use Storage;

class Parser extends BaseParser
{

    protected $pdf;

    protected $password;

    protected $has_error = false;

    public function parseFile($filename, $password = '')
    {
        $this->pdf = $filename;
        $this->password = $password;

        try {
            $pdf = parent::parseFile($this->pdf);
        } catch (\Exception $e) {
            //If this is a secure pdf we try unsecure it and try again
            if ($e->getMessage() === 'Secured pdf file are currently not supported.') {
                // Run the command
                // Call this function again and hopefully return $pdf
                $pdf = parent::parseFile($this->gsRewrite());
            } else {
                throw $e;
            }
        }

        return $pdf;
    }

    /**
     * Rewriting for a pdf if it is encrypted to remove encryption
     *
     * @throws IncorrectPDFPasswordException
     */
    public function gsRewrite()
    {
        // Fullpath of filename for gs output
        $unsecured = str_replace(basename($this->pdf), 'u-'.basename($this->pdf), $this->pdf);

        $cmd = 'gs -q -dNOPAUSE -dBATCH -dNumRenderingThreads=2 -dNOGC -sDEVICE=pdfwrite'
            ." -sPDFPassword={$this->password} -sOutputFile=$unsecured -c .setpdfwrite -f {$this->pdf}";

        $resp = shell_exec(escapeshellcmd($cmd));

        if (strpos($resp, 'Error: /invalidfileaccess in pdf_process_Encrypt') !== false) {
            unlink($unsecured);
            $this->has_error = true;
            $message = empty($this->password) ? 'Password Required To Unencrypt PDF' : 'Incorrect Password '.$this->password;
            throw new IncorrectPDFPasswordException($message);
        }
        // If we have a password, save what was done, if not leave it secured
        if ($this->password !== null && $this->password !== '') {
            rename($unsecured, $this->pdf);
        }

        return $this->pdf;
    }

    public function hasPassword($file)
    {
        try {
            $this->parseFile($file);
        } catch (IncorrectPDFPasswordException $e) {
            return true;
        }

        return false;
    }
    
    public function removePassword($file, $password)
    {
        $this->pdf = $file;
        $this->password = $password;
        try {
            $this->gsRewrite();
        } catch (IncorrectPDFPasswordException $e) {
            return false;
        }
        
        return true;
    }
}