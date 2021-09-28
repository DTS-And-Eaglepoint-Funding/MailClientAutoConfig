<?php

interface UsernameResolver
{
    /**
     * @param stdClass $request
     *
     * @return string|null
     */
    public function find_username(stdClass $request);
}
