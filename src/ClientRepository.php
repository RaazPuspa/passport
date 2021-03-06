<?php

namespace RaazPuspa\Passport;

class ClientRepository
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var PersonalAccessClient
     */
    protected $personalAccessClient;

    /**
     * ClientRepository constructor.
     * @return void
     */
    public function __construct()
    {
        $this->client               = Passport::client();
        $this->personalAccessClient = Passport::personalAccessClient();
    }

    /**
     * @param string $connection
     */
    public function updateConnection(string $connection)
    {
        $this->client->setConnection($connection);
        $this->personalAccessClient->setConnection($connection);
    }

    /**
     * Get a client by the given ID.
     *
     * @param  int $id
     * @return \RaazPuspa\Passport\Client|null
     */
    public function find($id)
    {
        return $this->client->where('id', $id)->first();
    }

    /**
     * Get an active client by the given ID.
     *
     * @param  int $id
     * @return \RaazPuspa\Passport\Client|null
     */
    public function findActive($id)
    {
        $client = $this->find($id);

        return $client && !$client->revoked ? $client : null;
    }

    /**
     * Get a client instance for the given ID and user ID.
     *
     * @param  int   $clientId
     * @param  mixed $userId
     * @return \RaazPuspa\Passport\Client|null
     */
    public function findForUser($clientId, $userId)
    {
        return $this->client
            ->where('id', $clientId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Get the client instances for the given user ID.
     *
     * @param  mixed $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function forUser($userId)
    {
        return $this->client
            ->where('user_id', $userId)
            ->orderBy('name', 'asc')->get();
    }

    /**
     * Get the active client instances for the given user ID.
     *
     * @param  mixed $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function activeForUser($userId)
    {
        return $this->forUser($userId)->reject(function ($client) {
            return $client->revoked;
        })->values();
    }

    /**
     * Get the personal access token client for the application.
     *
     * @return \RaazPuspa\Passport\Client
     */
    public function personalAccessClient()
    {
        if (Passport::$personalAccessClientId) {
            return $this->find(Passport::$personalAccessClientId);
        }

        return $this->personalAccessClient->orderBy('id', 'desc')->first()->client;
    }

    /**
     * Store a new client.
     *
     * @param  int    $userId
     * @param  string $name
     * @param  string $redirect
     * @param  bool   $personalAccess
     * @param  bool   $password
     * @return \RaazPuspa\Passport\Client
     */
    public function create($userId, $name, $redirect, $personalAccess = false, $password = false)
    {
        $client = $this->client->forceFill([
            'user_id'                => $userId,
            'name'                   => $name,
            'secret'                 => str_random(40),
            'redirect'               => $redirect,
            'personal_access_client' => $personalAccess,
            'password_client'        => $password,
            'revoked'                => false,
        ]);

        $client->save();

        return $client;
    }

    /**
     * Store a new personal access token client.
     *
     * @param  int    $userId
     * @param  string $name
     * @param  string $redirect
     * @return \RaazPuspa\Passport\Client
     */
    public function createPersonalAccessClient($userId, $name, $redirect)
    {
        return tap($this->create($userId, $name, $redirect, true), function ($client) {
            $accessClient            = $this->personalAccessClient;
            $accessClient->client_id = $client->id;
            $accessClient->save();
        });
    }

    /**
     * Store a new password grant client.
     *
     * @param  int    $userId
     * @param  string $name
     * @param  string $redirect
     * @return \RaazPuspa\Passport\Client
     */
    public function createPasswordGrantClient($userId, $name, $redirect)
    {
        return $this->create($userId, $name, $redirect, false, true);
    }

    /**
     * Update the given client.
     *
     * @param  Client $client
     * @param  string $name
     * @param  string $redirect
     * @return \RaazPuspa\Passport\Client
     */
    public function update(Client $client, $name, $redirect)
    {
        $client->forceFill([
            'name'     => $name,
            'redirect' => $redirect,
        ])->save();

        return $client;
    }

    /**
     * Regenerate the client secret.
     *
     * @param  \RaazPuspa\Passport\Client $client
     * @return \RaazPuspa\Passport\Client
     */
    public function regenerateSecret(Client $client)
    {
        $client->forceFill([
            'secret' => str_random(40),
        ])->save();

        return $client;
    }

    /**
     * Determine if the given client is revoked.
     *
     * @param  int $id
     * @return bool
     */
    public function revoked($id)
    {
        $client = $this->find($id);

        return is_null($client) || $client->revoked;
    }

    /**
     * Delete the given client.
     *
     * @param  \RaazPuspa\Passport\Client $client
     * @return void
     */
    public function delete(Client $client)
    {
        $client->tokens()->update([ 'revoked' => true ]);

        $client->forceFill([ 'revoked' => true ])->save();
    }
}
