<?php

class OutlookHandler extends RequestHandler
{

    /**
     * @return object
     * @throws Exception
     */
    protected function parse_request(): object
    {
        $postdata = file_get_contents( "php://input" );

        if ( strlen( $postdata ) > 0 ) {
            $xml = simplexml_load_string( $postdata );
            $emailaddress = $xml->Request->EMailAddress;
            if ( is_null( $emailaddress ) ) {
                throw new Exception( Exceptions::NO_MAILADDRESS_PROVIDED );
            }
            return (object) [ 'email' => $emailaddress ];
        }

        throw new Exception( Exceptions::NO_MAILADDRESS_PROVIDED );
    }

    /**
     * @param XMLWriter           $writer
     * @param DomainConfiguration $config
     * @param stdClass            $request
     */
    public function write_xml( XMLWriter $writer, DomainConfiguration $config, stdClass $request )
    {
        $writer->startDocument( "1.0", "utf-8" );
        $writer->setIndent( 4 );
        $writer->startElement( "Autodiscover" );
        $writer->writeAttribute( "xmlns", "http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006" );
        $writer->startElement( "Response" );
        $writer->writeAttribute( "xmlns", "http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a" );

        $writer->startElement( "Account" );
        $writer->writeElement( "AccountType", "email" );
        $writer->writeElement( "Action", "settings" );

        foreach ( $config->get_servers() as $server ) {
            foreach ( $server->endpoints as $endpoint ) {
                if ( $this->write_protocol( $writer, $server, $endpoint, $request ) )
                    break;
            }
        }

        $writer->endElement();

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
    }

    /**
     * @param XMLWriter $writer
     * @param Server    $server
     * @param stdClass  $endpoint
     * @param stdClass  $request
     *
     * @return bool
     */
    protected function write_protocol( XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request )
    {
        switch ( $endpoint->authentication ) {
            case 'password-cleartext':
            case 'SPA':
                break;
            case 'none':
                if ( $server->type !== 'smtp' ) return false;
                break;
            default:
                return false;
        }

        $writer->startElement( 'Protocol' );
        $writer->writeElement( 'Type', strtoupper( $server->type ) );
        $writer->writeElement( 'Server', $server->hostname );
        $writer->writeElement( 'Port', $endpoint->port );
        $writer->writeElement( 'LoginName', $this->get_username( $server, $request ) );
        $writer->writeElement( 'DomainRequired', 'off' );
        $writer->writeElement( 'SPA', $endpoint->authentication === 'SPA' ? 'on' : 'off' );

        switch ( $endpoint->socket_type ) {
            case 'plain':
                $writer->writeElement( "SSL", "off" );
                break;
            case 'SSL':
                $writer->writeElement( "SSL", "on" );
                $writer->writeElement( "Encryption", "SSL" );
                break;
            case 'STARTTLS':
                $writer->writeElement( "SSL", "on" );
                $writer->writeElement( "Encryption", "TLS" );
                break;
        }

        $writer->writeElement( "AuthRequired", $endpoint->authentication !== 'none' ? 'on' : 'off' );

        if ( $server->type == 'smtp' ) {
            $writer->writeElement( 'UsePOPAuth', $server->same_password ? 'on' : 'off' );
            $writer->writeElement( 'SMTPLast', 'off' );
        }

        $writer->endElement();

        return true;
    }

    /**
     * @param string $authentication
     *
     * @return bool|false|string|null
     */
    protected function map_authentication_type( string $authentication )
    {
        switch ( $authentication ) {
            case 'password-cleartext':
                return 'password-cleartext';
            case 'CRAM-MD5':
                return 'password-encrypted';
            case 'none':
                return false;
            default:
                return null;
        }
    }
}
