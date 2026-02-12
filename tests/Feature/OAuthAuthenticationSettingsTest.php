<?php

namespace App\Http\Controllers {
    function get_socialite_provider(string $provider): \Tests\Feature\FakeOauthProvider
    {
        return new \Tests\Feature\FakeOauthProvider;
    }
}

namespace Tests\Feature {

    use App\Actions\Fortify\ResetUserPassword;
    use App\Models\InstanceSettings;
    use App\Models\User;
    use Illuminate\Foundation\Testing\RefreshDatabase;
    use Illuminate\Support\Facades\Hash;

    uses(RefreshDatabase::class);

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

    class FakeSocialiteUser
    {
        public string $name = 'OAuth User';

        public string $email = 'oauth-user@example.com';
    }

    function createSettings(array $overrides = []): void
    {
        InstanceSettings::query()->create(array_merge([
            'id' => 0,
            'is_registration_enabled' => false,
            'is_oauth_registration_enabled' => false,
            'is_oauth_password_login_disabled' => false,
        ], $overrides));
    }

    it('allows oauth self registration when oauth registration setting is enabled', function () {
        createSettings(['is_oauth_registration_enabled' => true]);

        $response = $this->get('/auth/github/callback');

        $response->assertRedirect('/');
        expect(User::query()->where('email', 'oauth-user@example.com')->first())
            ->not->toBeNull()
            ->is_oauth_user->toBeTrue();
    });

    it('blocks oauth self registration when all registration is disabled', function () {
        createSettings();

        $response = $this->get('/auth/github/callback');

        $response->assertRedirect(route('login'));
        expect(User::query()->where('email', 'oauth-user@example.com')->exists())->toBeFalse();
    });

    it('blocks password login for oauth users when oauth password login is disabled', function () {
        createSettings(['is_oauth_password_login_disabled' => true]);

        User::query()->create([
            'name' => 'OAuth User',
            'email' => 'oauth-user@example.com',
            'password' => Hash::make('secret-password'),
            'is_oauth_user' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'oauth-user@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    });

    it('does not allow oauth-only users to reset passwords', function () {
        createSettings(['is_oauth_password_login_disabled' => true]);

        $user = User::query()->create([
            'name' => 'OAuth User',
            'email' => 'oauth-user@example.com',
            'password' => Hash::make('secret-password'),
            'is_oauth_user' => true,
        ]);

        app(ResetUserPassword::class)->reset($user, [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);
}
