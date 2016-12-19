<?php

namespace LaravelPDFParser;

use LaravelPDFParser\Exceptions\EncryptedPDFNeedsPasswordException;
use LaravelPDFParser\Exceptions\IncorrectPDFPasswordException;
use Smalot\PdfParser\Parser as BaseParser;

class Parser
{

    /**
     * @var string
     */
    protected $pdf;

    /**
     * @var BaseParser
     */
    protected $parser;

    /**
     * @var string
     */
    protected $password = '';

    /**
     * @var bool
     */
    protected $has_error = false;

    /**
     * @var bool
     */
    protected $has_password;

    /**
     * @var string
     */
    protected $invalid_error;

    public function __construct($filename = null, $password = null)
    {
        $this->parser = new BaseParser;
        $this->setPdf($filename)->setPassword($password);
    }

    public function isValid($filename = null)
    {
        try {
            $this->parseFile($filename);
        } catch (\Exception $e) {
            if (preg_match('/Empty PDF data|Invalid PDF data|Invalid type/', $e->getMessage())) {
                $this->setInvalidError('It looks like that PDF is corrupt. Please open it and check before trying again');

                return false;
            }

        }

        return true;
    }

    private function setInvalidError($message)
    {
        $this->invalid_error = $message;
    }

    /**
     * This will free memory so we don't have the file in memory twice
     * @return $this
     */
    protected function resetParser()
    {
        unset($this->parser);
        $this->parser = new BaseParser;

        return $this;
    }

    public function setPdf($pdf = null)
    {
        if ($pdf !== null) {
            $this->pdf = $pdf;
        }

        return $this;
    }

    public function setPassword($password = null)
    {
        if ($password !== null) {
            $this->password = $password;
        }

        return $this;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function parseFile($filename = null, $password = null)
    {
        $this->setPdf($filename)->setPassword($password);

        return $this->parser->parseFile($this->pdf);
    }

    /**
     * Remove password returns true if removed or false if incorrect password
     * @param null $file
     * @param null $password
     * @return bool
     * @throws \Exception
     */
    public function removePassword($file = null, $password = null)
    {
        $this->setPdf($file)->setPassword($password);

        try {
            $this->gsRewrite();
        } catch (IncorrectPDFPasswordException $e) {
            return false;
        } catch (\Exception $e) {
            $securedMessages = [
                'Object list not found. Possible secured file.',
                'Secured pdf file are currently not supported.'
            ];
            if (!in_array($e->getMessage(), $securedMessages, true)) {
                throw $e;
            }
        }

        return true;
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
            .' -sPDFPassword='.escapeshellarg($this->password)
            .' -sOutputFile='.escapeshellarg($unsecured)
            .' -c .setpdfwrite -f '.escapeshellarg($this->pdf);

        $resp = shell_exec($cmd);

        if (strpos($resp, 'Error: /invalidfileaccess in pdf_process_Encrypt') !== false) {
            unlink($unsecured);
            $this->has_error = true;
            $message = empty($this->password) ? 'Password Required To Unencrypt PDF' : 'Incorrect Password '.$this->password;
            throw new IncorrectPDFPasswordException($message);
        }

        // If there is a password entered we want to replace the secure PDF
        if (strlen($this->password) > 0) {
            rename($unsecured, $this->pdf);
        }

        return $unsecured;
    }

    public function hasPassword($file = null)
    {
        $this->setPdf($file);
        if (is_bool($this->has_password)) {
            return $this->has_password;
        }

        try {
            $this->parseFile();
        } catch (\Exception $e) {
            //If this is a secure pdf we try unsecure it and try again
            if ($e->getMessage() === 'Secured pdf file are currently not supported.') {
                $this->resetParser();

                return $this->has_password = true;
            }

            throw $e;
        }

        return $this->has_password = false;
    }

}
