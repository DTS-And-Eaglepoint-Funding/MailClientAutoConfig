<?php


class ExceptionHandling
{
    private $status_code;
    private $exception_message;
    private $respose_type;

    /**
     * ExceptionHandling constructor.
     * @param int $status_code
     * @param string $exception_message
     * @param string $respose_type
     */
    public function __construct(
        int $status_code = 400,
        string $exception_message = 'Bad Request',
        string $respose_type = 'xml'
    ) {
        $this->status_code = $status_code;
        $this->exception_message = $exception_message;
        $this->respose_type = $respose_type;
    }

    /**
     * @param int $status
     * @return ExceptionHandling
     */
    public function set_status(int $status)
    {
        $this->status_code = $status;
        return $this;
    }

    /**
     * @param string $message
     * @return ExceptionHandling
     */
    public function set_exception(string $message)
    {
        $this->exception_message = $message;
        return $this;
    }

    /**
     * @param string $response_type
     * @return ExceptionHandling
     * @throws Exception
     */
    public function set_respose_type(string $response_type)
    {
        $possible_response_types = array('xml', 'text', 'html');
        if (!in_array($response_type, $possible_response_types)) {
            throw new Exception("Selected response_type not available '$response_type'");
        }
        $this->respose_type = $response_type;
        return $this;
    }

    /**
     * @return string
     */
    public function get_response()
    {
        switch ($this->respose_type) {
            case 'html':
                return $this->response_html();
            case 'xml':
                return $this->response_xml();
            case 'text':
            default:
                return $this->response_text();
        }
    }

    /**
     * @return string
     */
    private function response_xml()
    {
        header("Content-Type: application/xml; charset=UTF-8");
        http_response_code($this->status_code);
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument("1.0", "utf-8");
        $writer->setIndent(4);
        $writer->writeElement("Exception", $this->exception_message);
        return $writer->outputMemory(true);
    }

    /**
     * @return string
     */
    private function response_text()
    {
        header("Content-Type: text/plain; charset=UTF-8");
        http_response_code($this->status_code);
        return $this->exception_message;
    }

    /**
     * @return string
     */
    private function response_html()
    {
        header("Content-Type: text/html; charset=UTF-8");
        http_response_code($this->status_code);
        return "<html lang=\"en\"><head><title>{$this->status_code}</title></head><body>{$this->exception_message}</body></html>";
    }
}
