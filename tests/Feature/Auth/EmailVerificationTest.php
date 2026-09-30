<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());
    }

    public function test_registering_sends_the_verification_notification()
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Nueva Firmante',
            'email' => 'nueva@ravsign.test',
            'password' => 'a-long-enough-password-1',
            'password_confirmation' => 'a-long-enough-password-1',
        ]);

        $user = User::where('email', 'nueva@ravsign.test')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_an_unverified_user_is_redirected_away_from_documents()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/documents')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_a_verified_user_can_reach_documents()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/documents')->assertOk();
    }

    public function test_email_can_be_verified()
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect(route('documents.index', absolute: false).'?verified=1');

        Event::assertDispatched(Verified::class);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_is_not_verified_with_an_invalid_hash()
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('not-the-right-email'),
        ]);

        $this->actingAs($user)->get($url);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_the_verification_link_can_be_resent()
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertRedirect();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }
}
