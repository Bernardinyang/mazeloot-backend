<?php

namespace App\Session;

use Illuminate\Session\DatabaseSessionHandler as BaseDatabaseSessionHandler;

class DatabaseSessionHandler extends BaseDatabaseSessionHandler
{
    /**
     * Add the user information to the session payload.
     *
     * @param  array  $payload
     * @return $this
     */
    protected function addUserInformation(&$payload)
    {
        if ($this->container->bound(\Illuminate\Contracts\Auth\Guard::class)) {
            $payload['user_uuid'] = $this->userId();
        }

        return $this;
    }
}
