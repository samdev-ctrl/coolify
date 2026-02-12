<?php

namespace Tests\Feature;

class FakeOauthProvider
{
    public function redirect(): string
    {
        return '/redirected';
    }

    public function user(): FakeSocialiteUser
    {
        return new FakeSocialiteUser;
    }
}
