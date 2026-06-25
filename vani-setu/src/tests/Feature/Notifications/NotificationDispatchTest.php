<?php

namespace Tests\Feature\Notifications;

use App\Mail\GenericNotificationMail;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    public function test_authenticated_user_can_send_email_notification(): void
    {
        config()->set('services.delivery_channels.send_email', true);
        config()->set('logging.default', 'null');

        $user = User::factory()->make(['id' => 1]);
        Sanctum::actingAs($user);
        Mail::fake();

        $response = $this->postJson('/api/notifications/email', [
            'type' => 'G',
            'to' => ['member@example.test'],
            'subject' => 'Committee update',
            'title' => 'Committee update',
            'content' => 'The sitting has been rescheduled.',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Mail sent successfully.')
            ->assertJsonPath('data.mailer', 'smtp');

        Mail::assertSent(GenericNotificationMail::class, function (GenericNotificationMail $mail) {
            return $mail->subjectLine === 'Committee update';
        });
    }

    public function test_authenticated_user_can_send_sms_notification(): void
    {
        config()->set('services.delivery_channels.send_sms', true);
        config()->set('logging.default', 'null');
        config()->set('services.sms_gateway.api_url', 'https://smsgw.sms.gov.in/failsafe/MLink?');
        config()->set('services.sms_gateway.username', 'test-user');
        config()->set('services.sms_gateway.pin', 'test-pin');
        config()->set('services.sms_gateway.signature', 'RSS Admin');
        config()->set('services.sms_gateway.dlt_entity_id', '1001748780434324174');

        $user = User::factory()->make(['id' => 1]);
        Sanctum::actingAs($user);

        Http::fake([
            'https://smsgw.sms.gov.in/*' => Http::response('OK:1001', 200),
        ]);

        $response = $this->postJson('/api/notifications/sms', [
            'recipient' => '9876543210',
            'message' => 'OTP 123456',
            'template_id' => '17071707170717',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'SMS sent successfully.')
            ->assertJsonPath('data.successful', true);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://smsgw.sms.gov.in/failsafe/MLink')
                && $request['username'] === 'test-user'
                && $request['pin'] === 'test-pin'
                && $request['mnumber'] === '9876543210'
                && $request['message'] === 'OTP 123456'
                && $request['signature'] === 'RSS Admin'
                && $request['dlt_entity_id'] === '1001748780434324174'
                && $request['dlt_template_id'] === '17071707170717';
        });
    }

    public function test_authenticated_user_can_dispatch_whatsapp_notification_through_engine(): void
    {
        config()->set('services.delivery_channels.send_whatsapp', true);
        config()->set('logging.default', 'null');
        config()->set('services.whatsapp_gateway.api_url', 'https://graph.example.test/messages');
        config()->set('services.whatsapp_gateway.token', 'test-token');

        $user = User::factory()->make(['id' => 1]);
        Sanctum::actingAs($user);

        Http::fake([
            'https://graph.example.test/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200),
        ]);

        $response = $this->postJson('/api/notifications', [
            'channel' => 'whatsapp',
            'producer' => 'sabha',
            'recipient' => '919876543210',
            'body' => 'Committee update',
            'template_id' => 'committee_update',
            'data' => ['sitting' => 'RS-42'],
        ]);

        $response->assertAccepted()
            ->assertJsonPath('message', 'Notification dispatched.')
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.channel', 'whatsapp');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.example.test/messages'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['to'] === '919876543210'
                && $request['template_id'] === 'committee_update';
        });
    }

}
