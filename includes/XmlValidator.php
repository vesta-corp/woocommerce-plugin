<?php
/**
 * Guarantee Module risk information validator functions.
 *
 * @author Chetu Team.
 */
class XmlValidator
{

    /**
     * @var string
     */
    public $feedSchema = __DIR__ . DIRECTORY_SEPARATOR . 'StandardRiskInfo2.0.xsd';

    /**
     * @var int
     */
    public $feedErrors = 0;

    /**
     * Formatted libxml Error details
     *
     * @var array
     */
    public $errorDetails;

    /**
     * CLass instance
     *
     * @var mixed
     */
    public static $instance;

    /**
     * Validation Class constructor Instantiating DOMDocument
     *
     * @param \DOMDocument $handler
     */
    public function __construct()
    {
        $this->handler = new \XMLReader();
        
    }
    public static function getXmlValidatorInstance() {
        if ( is_null( self::$instance ) ) {
                self::$instance = new self;
        }

        return self::$instance;
    }
    /**
     * @param \libXMLError object $error
     *
     * @return string
     */
    private function libxmlDisplayError($error = null)
    {
        $errorString = "Error $error->code in $error->file (Line:{$error->line}):";
        $errorString .= trim($error->message);
        return $errorString;
    }

    /**
     * Add XML errors into array object
     *
     * @return array
     */
    private function libxmlDisplayErrors()
    {
        $errors = libxml_get_errors();
        $result = [];
        foreach ($errors as $error) {
            $result[] = $this->libxmlDisplayError($error);
        }
        libxml_clear_errors();

        return $result;
    }

    /**
     * Validate Incoming Feeds against Listing Schema
     *
     * @param resource $feeds
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function validateFeeds($feeds = null)
    {
        if (!class_exists('XMLReader')) {
            die('XMLReader class not found!');

            return false;
        }
        if (!file_exists($this->feedSchema)) {
            die('Schema is Missing, Please add schema to feedSchema property');
            return false;
        }
        $this->handler->open($feeds);
        $this->handler->setSchema($this->feedSchema);
        libxml_use_internal_errors(true);
        while ($this->handler->read()) {
            if (!$this->handler->isValid()) {
                $this->errorDetails[] = $this->libxmlDisplayErrors();
                $this->feedErrors = 1;
            } else {
                return true;
            }
        }
    }

    /**
     * prepare Error in formatted way
     *
     * @return array
     */
    private function prepareErrors()
    {
        $data = [];
        $errors = array_filter($this->errorDetails);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                if (is_array($error)) {
                    foreach ($error as $value) {
                        $data[] = $value;
                    }
                } else {
                    $data[] = $error;
                }
            }
        }
        return $data;
    }

    /**
     * Display XSD validation errors
     *
     * @return array
     */
    public function displayErrors()
    {
        return $this->prepareErrors();
    }
}
