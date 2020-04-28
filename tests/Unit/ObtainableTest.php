<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Orchestra\Testbench\TestCase;
use WizeWiz\MailjetMailer\Collections\MailjetRequestCollection;
use WizeWiz\MailjetMailer\Events\Webhook\BaseEvent;
use WizeWiz\MailjetMailer\Mailer;
use WizeWiz\MailjetMailer\Models\MailjetMessage;
use WizeWiz\MailjetMailer\Models\MailjetRequest;
use WizeWiz\MailjetMailer\Tests\FakeEvents;
use WizeWiz\MailjetMailer\Tests\FakeMailer;
use WizeWiz\MailjetMailer\Tests\FakeModels;
use WizeWiz\MailjetMailer\Tests\TestEnvironment;

class MailerTest extends TestCase {

    use WithoutMiddleware, RefreshDatabase, TestEnvironment;

    protected function ignoreTest() {
        return false;
    }

    public function testMail() {
        // mailer with default settings.
        $Mailer = new FakeMailer();
        // mailer should be initialized
        $this->assertTrue($Mailer->isInitialized());
        // create request
        $Request = $Mailer->newRequest();
        $this->assertInstanceOf(MailjetRequest::class, $Request);
        // create collection
        $Collection = $Mailer->newCollection();
        $this->assertInstanceOf(MailjetRequestCollection::class, $Collection);

        // account default settings should have been set
        $this->assertEquals('default', $Mailer->getAccount());
        // change account to test
        $Mailer->configure('test');
        $this->assertEquals('test', $Mailer->getAccount());
    }

    public function testMailAccountDefault() {
        // mailer with default settings.
        $Mailer = new Mailer();

        $this->assertTrue($Mailer->isInitialized());
        $this->assertEquals('local', $Mailer->getEnvironment());
        $this->assertEquals('default', $Mailer->getAccount());
        // test account (config)
        $this->assertEquals('v3.1', $Mailer->getConfigOption('version'));
        // check auth
        $this->assertEquals('default-fake-key', $Mailer->getConfigOption('key'));
        $this->assertEquals('default-fake-secret', $Mailer->getConfigOption('secret'));
        // check sender
        $this->assertEquals([
            "email" => "default@fake.email.local",
            "name" => "no-reply"
        ], $Mailer->getConfigOption('sender'));
        // check pre-defined templates
        $templates = $Mailer->getConfigOption('templates');
        $this->assertTrue(array_key_exists('test-template', $templates));
        $this->assertTrue(isset($templates['test-template']['id']));
        $this->assertEquals(1000000, $templates['test-template']['id']);

    }

    public function testMailAccountTest() {
        // configure the mailer with test account.
        $Mailer = new Mailer(['account' => 'test']);

        $this->assertTrue($Mailer->isInitialized());
        $this->assertEquals('local', $Mailer->getEnvironment());
        $this->assertEquals('test', $Mailer->getAccount());
        // test account (config)
        $this->assertEquals('v3', $Mailer->getConfigOption('version'));
        // check auth
        $this->assertEquals('test-fake-key', $Mailer->getConfigOption('key'));
        $this->assertEquals('test-fake-secret', $Mailer->getConfigOption('secret'));
        // check sender
        $this->assertEquals([
            "email" => "test@fake.email.local",
            "name" => "no-reply (test)"
        ], $Mailer->getConfigOption('sender'));
        // check pre-defined templates
        $templates = $Mailer->getConfigOption('templates');
        $this->assertTrue(array_key_exists('test-template', $templates));
        $this->assertTrue(isset($templates['test-template']['id']));
        $this->assertEquals(2000000, $templates['test-template']['id']);
    }
}