<?php

class Configuration
{
    private $items = [];

    /**
     * Setup new Configuration for Autoconfig
     *
     * @param $id
     *
     * @return DomainConfiguration
     */
    public function add( $id )
    {
        $result = new DomainConfiguration();
        $result->set_id( $id );
        array_push( $this->items, $result );
        return $result;
    }

    /**
     * @param string $domain
     *
     * @return DomainConfiguration
     * @throws Exception
     */
    public function get_domain_config( string $domain )
    {
        foreach ( $this->items as $domain_config ) {
            /** @var $domain_config DomainConfiguration */
            if ( in_array( $domain, $domain_config->get_domains() ) ) {
                return $domain_config;
            }
        }

        throw new Exception( sprintf( Exceptions::DOMAIN_NOT_CONFIGURED, $domain ) );
    }
}
