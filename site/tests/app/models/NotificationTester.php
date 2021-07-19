<?php

declare(strict_types=1);

namespace tests\app\models;

use app\libraries\Core;
use app\models\Config;
use app\models\Notification;
use app\models\User;

class NotificationTester extends \PHPUnit\Framework\TestCase {
    /**
     * @var Core
     */
    private $core;
    private $notify_details;

    public function setUp(): void {
        $this->core = new Core();
        $config = new Config($this->core);
        $config->setSemester('s20');
        $config->setCourse('sample');
        $user = new User($this->core, [
            'user_id' => 'test_user',
            'user_firstname' => 'Tester',
            'user_lastname' => 'Test',
            'user_email' => null,
            'user_email_secondary' => '',
            'user_email_secondary_notify' => false,
            'time_zone' => 'America/New_York'
        ]);
        $this->core->setUser($user);
        $this->core->setConfig($config);

        $this->notify_details = [
            'normal' => [
                'component' => 'test_component',
                'metadata' => json_encode([]),
                'subject' => 'test_message_content',
                'sender_id' => $this->core->getUser()->getId(),
                'to_user_id' => 'test_recipient'
            ],
            'view_only' => [
                'id' => 'test_notification_id',
                'seen' => false,
                'component' => 'test_component_view_only',
                'created_at' => date('n/j g:i A'),
                'elapsed_time' => 59,
                'metadata' => json_encode([]),
                'content' => 'test_content_view_only',
            ],
            'empty_view_only' => []
        ];
    }

    public function testCreateNotification(): void {
        // Now get the basic notifcation details and create the notification object
        $normalNotifyDetails = $this->notify_details['normal'];
        $normalNotification = Notification::createNotification($this->core, $normalNotifyDetails);

        $this->assertEquals($normalNotifyDetails['component'], $normalNotification->getComponent());
        $this->assertEquals($normalNotifyDetails['metadata'], $normalNotification->getNotifyMetadata());
        $this->assertEquals($this->core->getUser()->getId(), $normalNotification->getNotifySource());
        $this->assertEquals($normalNotifyDetails['subject'], $normalNotification->getNotifyContent());
        $this->assertEquals($normalNotifyDetails['to_user_id'], $normalNotification->getNotifyTarget());

        // Update the values using various setters
        $created_at = date('n/j g:i A');
        $normalNotification->setComponent('updated_component');
        $normalNotification->setSeen(true);
        $normalNotification->setCreatedAt($created_at);
        $normalNotification->setNotifyMetadata(json_encode(["url" => "https://test.submitty.com", "extra" => "some_extra_metadata"]));
        $normalNotification->setNotifyContent('updated_content');
        $normalNotification->setNotifySource('updated_test_user');
        $normalNotification->setNotifyTarget('updated_recipient_user');

        // Now test if the values are properly set/updated or not
        $this->assertEquals('updated_component', $normalNotification->getComponent());
        $this->assertEquals(true, $normalNotification->isSeen());
        $this->assertEquals($created_at, $normalNotification->getCreatedAt());
        $this->assertEquals('updated_test_user', $normalNotification->getNotifySource());
        $this->assertEquals('updated_recipient_user', $normalNotification->getNotifyTarget());
        $this->assertEquals('updated_content', $normalNotification->getNotifyContent());
        $this->assertEquals(json_encode(["url" => "https://test.submitty.com", "extra" => "some_extra_metadata"]), $normalNotification->getNotifyMetadata());
    }

    public function testCreateViewOnlyNotification(): void {
        // testing with empty notification details
        $emptyViewOnlyNotification =  Notification::createViewOnlyNotification($this->core, $this->notify_details['empty_view_only']);
        // notification should be null
        $this->assertNull($emptyViewOnlyNotification);

        // Creating a simple view-only notification object
        $viewOnlyNotificationDetails = $this->notify_details['view_only'];
        $viewOnlyNotification = Notification::createViewOnlyNotification($this->core, $viewOnlyNotificationDetails);

        $this->assertEquals($viewOnlyNotificationDetails['component'], $viewOnlyNotification->getComponent());
        $this->assertEquals($viewOnlyNotificationDetails['seen'], $viewOnlyNotification->isSeen());
        $this->assertEquals($viewOnlyNotificationDetails['elapsed_time'], $viewOnlyNotification->getElapsedTime());
        $this->assertEquals($viewOnlyNotificationDetails['created_at'], $viewOnlyNotification->getCreatedAt());
        $this->assertEquals($viewOnlyNotificationDetails['content'], $viewOnlyNotification->getNotifyContent());
        $this->assertEquals($viewOnlyNotificationDetails['metadata'], $viewOnlyNotification->getNotifyMetadata());

        // Update the values using various setters
        $created_at = date('n/j g:i A');
        $viewOnlyNotification->setComponent('updated_component_view_only');
        $viewOnlyNotification->setSeen(true);
        $viewOnlyNotification->setElapsedTime(130);
        $viewOnlyNotification->setCreatedAt($created_at);
        $viewOnlyNotification->setNotifyMetadata(json_encode(["url" => "https://test.submitty.com", "extra" => "some_extra_metadata"]));
        $viewOnlyNotification->setNotifyContent('updated_content_view_only');
        $viewOnlyNotification->setNotifySource('test_user');
        $viewOnlyNotification->setNotifyTarget('test_recipient');


        $this->assertEquals('updated_component_view_only', $viewOnlyNotification->getComponent());
        $this->assertEquals(true, $viewOnlyNotification->isSeen());
        $this->assertEquals(130, $viewOnlyNotification->getElapsedTime());
        $this->assertEquals($created_at, $viewOnlyNotification->getCreatedAt());
        $this->assertEquals('test_user', $viewOnlyNotification->getNotifySource());
        $this->assertEquals('test_recipient', $viewOnlyNotification->getNotifyTarget());
        $this->assertEquals('updated_content_view_only', $viewOnlyNotification->getNotifyContent());
        $this->assertEquals(json_encode(["url" => "https://test.submitty.com", "extra" => "some_extra_metadata"]), $viewOnlyNotification->getNotifyMetadata());
    }

    public function testGetUrl(): void {
        // creating both notification from both the methods `createNotification` and `createViewOnlyNotification`
        $simpleNotification = Notification::createNotification($this->core, $this->notify_details['normal']);
        $viewOnlyNotification = Notification::createViewOnlyNotification($this->core, $this->notify_details['view_only']);

        // Initially the metadata is empty array so the getUrl should return null
        $this->assertNull(Notification::getUrl($this->core, $simpleNotification->getNotifyMetadata()));
        $this->assertNull(Notification::getUrl($this->core, $viewOnlyNotification->getNotifyMetadata()));

        // set the metadata with some data other than url
        $simpleNotification->setNotifyMetadata(json_encode(["extra" => "some_extra_metadata"]));
        $viewOnlyNotification->setNotifyMetadata(json_encode(["extra" => "some_extra_metadata"]));
        // As notification doesnt have 'url' metadata key, getUrl should return course-url
        $this->assertEquals($this->core->buildCourseUrl(), Notification::getUrl($this->core, $simpleNotification->getNotifyMetadata()));
        $this->assertEquals($this->core->buildCourseUrl(), Notification::getUrl($this->core, $viewOnlyNotification->getNotifyMetadata()));

        // set the metadata with url data
        $simpleNotification->setNotifyMetadata(json_encode(["url" => "https://test.submitty.com"]));
        $viewOnlyNotification->setNotifyMetadata(json_encode(["url" => "https://test.submitty.com/view-only"]));
        // getUrl should return the url present in notification's metadata
        $this->assertEquals("https://test.submitty.com", Notification::getUrl($this->core, $simpleNotification->getNotifyMetadata()));
        $this->assertEquals("https://test.submitty.com/view-only", Notification::getUrl($this->core, $viewOnlyNotification->getNotifyMetadata()));
    }

    public function testGetThreadIdIfExists(): void {
        // creating both notification from both the methods `createNotification` and `createViewOnlyNotification`
        $simpleNotification = Notification::createNotification($this->core, $this->notify_details['normal']);
        $viewOnlyNotification = Notification::createViewOnlyNotification($this->core, $this->notify_details['view_only']);

        // As notification doesnt have 'thread_id' metadata key, getThreadIdIfExists() should return -1
        $this->assertEquals(-1, Notification::getThreadIdIfExists($simpleNotification->getNotifyMetadata()));
        $this->assertEquals(-1, Notification::getThreadIdIfExists($viewOnlyNotification->getNotifyMetadata()));

        // set the metadata with thread_id data
        $simpleNotification->setNotifyMetadata(json_encode(["thread_id" => 123]));
        $viewOnlyNotification->setNotifyMetadata(json_encode(["thread_id" => 1234]));
        // getThreadIdIfExists() should return the thread_id present in notification's metadata
        $this->assertEquals(123, Notification::getThreadIdIfExists($simpleNotification->getNotifyMetadata()));
        $this->assertEquals(1234, Notification::getThreadIdIfExists($viewOnlyNotification->getNotifyMetadata()));
    }

    public function testTextShortner(): void {
        $message = "This is a short message";
        // max_length of message is 40, as the message is shorter than 40 characters,
        // textShortner should give back the exact same message
        $this->assertEquals($message, Notification::textShortner($message));

        $message = "This is a message having more than 40 characters";
        $this->assertEquals("This is a message having more than 40...", Notification::textShortner($message));
    }

    public function testHasEmptyMetadata(): void {
        $notification = Notification::createNotification($this->core, $this->notify_details['normal']);
        $this->assertEquals(true, $notification->hasEmptyMetadata());
        $notification->setNotifyMetadata(json_encode(["url" => "https://test.submitty.com"]));
        $this->assertEquals(false, $notification->hasEmptyMetadata());
    }

    public function testGetNotifyTime(): void {
        $notification = Notification::createViewOnlyNotification($this->core, $this->notify_details['view_only']);
        $this->assertEquals("Less than a minute ago", $notification->getNotifyTime());
        // Setting up different 'elapsed time'
        $notification->setElapsedTime(60); // 1 min
        $this->assertEquals("1 minute ago", $notification->getNotifyTime());

        $notification->setElapsedTime(350); // 5 mins 50 secs
        $this->assertEquals("5 minutes ago", $notification->getNotifyTime());

        $notification->setElapsedTime(4000); // 1 hr 6mins 40 secs
        $this->assertEquals("1 hour ago", $notification->getNotifyTime());

        $notification->setElapsedTime(20000); // 5 hrs 33min 20secs
        $this->assertEquals("5 hours ago", $notification->getNotifyTime());

        $notification->setElapsedTime(90000); // 24 hrs 43 mins 20 secs
        $created_at = date('n/j g:i A');
        $notification->setCreatedAt($created_at);

        $this->assertEquals($created_at, $notification->getNotifyTime());
    }
}
