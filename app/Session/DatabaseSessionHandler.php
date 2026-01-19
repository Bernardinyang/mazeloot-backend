<?php

namespace App\Session;

use Illuminate\Session\DatabaseSessionHandler as BaseDatabaseSessionHandler;

class DatabaseSessionHandler extends BaseDatabaseSessionHandler
{
    /**
     * Write a session to the database.
     *
     * @param  string  $sessionId
     * @param  string  $data
     */
    public function write($sessionId, $data): bool
    {
        $payload = $this->getDefaultPayload($data);

        if (! $this->exists) {
            $this->exists = $this->getQuery()->insert([
                'id' => $sessionId,
                'user_uuid' => $this->userId(),
                'payload' => base64_encode($data),
                'last_activity' => $payload['last_activity'],
                'ip_address' => $payload['ip_address'],
                'user_agent' => $payload['user_agent'],
            ]);
        } else {
            $this->getQuery()->where('id', $sessionId)->update([
                'user_uuid' => $this->userId(),
                'payload' => base64_encode($data),
                'last_activity' => $payload['last_activity'],
                'ip_address' => $payload['ip_address'],
                'user_agent' => $payload['user_agent'],
            ]);
        }

        return true;
    }

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
