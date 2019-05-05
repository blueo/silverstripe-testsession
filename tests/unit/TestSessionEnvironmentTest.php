<?php

namespace SilverStripe\TestSession\Tests\Unit;

use DateTime;
use SilverStripe\Control\Email\Mailer;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestMailer;
use SilverStripe\ORM\Connect\TempDatabase;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\TestSession\TestSessionEnvironment;
use stdClass;

class TestSessionEnvironmentTest extends SapphireTest
{

    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->unregisterNamedObject(TestSessionEnvironment::class);
        Injector::inst()->registerService(
            new TestSessionEnvironment,
            TestSessionEnvironment::class
        );
        $this->testSessionEnvironment = TestSessionEnvironment::singleton();
    }

    public function testApplyStateNoModification(): void
    {
        $state = new stdClass();
        $state->testprop = '';

        $env = new TestSessionEnvironment();
        $afterApply = $env->applyState($state);

        $this->assertSame($state, $afterApply);
    }

    public function testApplyStateDBConnect(): void
    {
        $state = new stdClass();
        $state->testprop = '';

        $env = $this->getMockBuilder(TestSessionEnvironment::class)
            ->setMethodsExcept(['applyState'])
            ->getMock();

        $env->expects($this->once())
            ->method('connectToDatabase');

        $env->applyState($state);
    }

    public function testApplyStateDatetime(): void
    {
        $state = new stdClass();
        $now = new DateTime();
        $now->modify('-1 day');
        $state->datetime = $now->format('Y-m-d H:i:s');

        $env = new TestSessionEnvironment();
        $env->applyState($state);
        $date = new DateTime();
        $this->assertNotEquals($date->getTimestamp(), DBDatetime::now()->getTimestamp());
        $this->assertEquals($now->getTimestamp(), DBDatetime::now()->getTimestamp());

        $state->datetime = 'invalid format';
        $this->expectExceptionMessage('Invalid date format "invalid format", use yyyy-MM-dd HH:mm:ss');
        $env->applyState($state);
    }

    public function testAppyStateMailer(): void
    {

        $state = new stdClass();
        $state->mailer = TestMailer::class;
        $env = new TestSessionEnvironment();

        $env->applyState($state);

        $mailer = Injector::inst()->get(Mailer::class);
        $this->assertEquals(TestMailer::class, get_class($mailer));

        $state->mailer = 'stdClass';
        $this->expectExceptionMessage('Class "stdClass" is not a valid class, or subclass of Mailer');
        $env->applyState($state);
    }

    public function testAppyStateStub(): void
    {

        $module = ModuleLoader::getModule('silverstripe/testsession');
        $state = new stdClass();

        $state->stubfile = $module->getPath() . DIRECTORY_SEPARATOR . 'tests/stubs/teststub.php';

        $env = new TestSessionEnvironment();

        $this->assertFalse(defined('TESTSESSION_STUBFILE'));

        $env->applyState($state);

        $this->assertTrue(defined('TESTSESSION_STUBFILE'));
    }

    public function testConnectToDatabase(): void
    {
        $env = new TestSessionEnvironment();
        $dbExisting = DB::get_conn()->getSelectedDatabase();

        $env->connectToDatabase(new stdClass);
        $db = DB::get_conn()->getSelectedDatabase();
        $this->assertEquals($dbExisting, $db);

        $tempDB = new TempDatabase();
        $dbName = $tempDB->build();

        $state = new stdClass();
        $state->database = $dbName;
        $env->connectToDatabase($state);
        $db = DB::get_conn()->getSelectedDatabase();
        $this->assertEquals($dbName, $db);
        $tempDB->kill();
    }
}
