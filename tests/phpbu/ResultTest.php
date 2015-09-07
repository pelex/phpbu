<?php
namespace phpbu\App;

use phpbu\App\Backup\Check\Exception;
use phpbu\App\Configuration;

/**
 * Version test
 *
 * @package    phpbu
 * @subpackage tests
 * @author     Sebastian Feldmann <sebastian@phpbu.de>
 * @copyright  Sebastian Feldmann <sebastian@phpbu.de>
 * @license    https://opensource.org/licenses/MIT The MIT License (MIT)
 * @link       http://www.phpbu.de/
 * @since      Class available since Release 1.1.6
 */
class ResultTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests Result::wasSuccessFul
     */
    public function testSuccessFullByDefault()
    {
        $result     = new Result();
        $cliPrinter = new Result\PrinterCli();
        $result->addListener($cliPrinter);

        $this->assertTrue($result->wasSuccessful(), 'should be successful by default');
        $this->assertTrue($result->allOk(), 'should be ok by default');
        $this->assertFalse($result->backupOkButSkipsOrFails(), 'nothing should be skipped');
    }

    /**
     * Tests minimal Result life cycle.
     */
    public function testBackupMinimal()
    {
        $conf   = new Configuration('/tmp/foo.xml');
        $backup = new Configuration\Backup('test-backup', false);
        $result = new Result();
        $result->phpbuStart($conf);
        $result->backupStart($backup);
        $result->backupEnd($backup);
        $result->phpbuEnd();

        $this->assertTrue($result->wasSuccessful(), 'should be successful');
        $this->assertTrue($result->allOk(), 'should be ok');
        $this->assertFalse($result->backupOkButSkipsOrFails(), 'nothing should be skipped');
    }

    /**
     * Tests max Result life cycle.
     */
    public function testBackupMaximalAllOk()
    {
        $conf    = new Configuration('/tmp/foo.xml');
        $backup  = new Configuration\Backup('test-backup', false);
        $check   = new Configuration\Backup\Check('sizemin', '10M');
        $crypt   = new Configuration\Backup\Crypt('mcrypt', false);
        $sync    = new Configuration\Backup\Sync('rsync', false);
        $cleanup = new Configuration\Backup\Cleanup('capacity', false);
        $result  = new Result();
        $result->phpbuStart($conf);
        $result->backupStart($backup);
        $result->checkStart($check);
        $result->checkEnd($check);
        $result->cryptStart($crypt);
        $result->cryptEnd($crypt);
        $result->syncStart($sync);
        $result->syncEnd($sync);
        $result->cleanupStart($cleanup);
        $result->cleanupEnd($cleanup);
        $result->backupEnd($backup);
        $result->phpbuEnd();

        $this->assertTrue($result->wasSuccessful(), 'should be successful');
        $this->assertTrue($result->allOk(), 'should be ok');
        $this->assertFalse($result->backupOkButSkipsOrFails(), 'nothing should be skipped');
        $this->assertEquals(0, $result->backupsFailedCount());
        $this->assertEquals(0, $result->checksFailedCount());
        $this->assertEquals(0, $result->syncsFailedCount());
        $this->assertEquals(0, $result->syncsSkippedCount());
        $this->assertEquals(0, $result->cleanupsFailedCount());
        $this->assertEquals(0, $result->cleanupsSkippedCount());
        $this->assertEquals(0, $result->errorCount());
        $this->assertEquals(0, count($result->getErrors()));
        $this->assertEquals(1, count($result->getBackups()));
    }

    /**
     * Tests max Result life cycle with skips.
     */
    public function testBackupMaximalWithSkips()
    {
        $conf    = new Configuration('/tmp/foo.xml');
        $backup  = new Configuration\Backup('test-backup', false);
        $check   = new Configuration\Backup\Check('sizemin', '10M');
        $crypt   = new Configuration\Backup\Crypt('mcrypt', false);
        $sync    = new Configuration\Backup\Sync('rsync', false);
        $cleanup = new Configuration\Backup\Cleanup('capacity', false);
        $result  = new Result();
        $result->phpbuStart($conf);
        $result->backupStart($backup);
        $result->checkStart($check);
        $result->checkEnd($check);
        $result->cryptSkipped($crypt);
        $result->syncSkipped($sync);
        $result->cleanupSkipped($cleanup);
        $result->backupEnd($backup);
        $result->phpbuEnd();

        $this->assertTrue($result->wasSuccessful(), 'should be successful');
        $this->assertFalse($result->allOk(), 'should be ok');
        $this->assertTrue($result->backupOkButSkipsOrFails(), 'crypt, sync and cleanup should be skipped');
        $this->assertEquals(0, $result->syncsFailedCount());
        $this->assertEquals(1, $result->syncsSkippedCount());
        $this->assertEquals(0, $result->cleanupsFailedCount());
        $this->assertEquals(1, $result->cleanupsSkippedCount());
        $this->assertEquals(0, $result->errorCount());
        $this->assertEquals(0, count($result->getErrors()));
        $this->assertEquals(1, count($result->getBackups()));
    }

    /**
     * Tests max Result life cycle with errors.
     */
    public function testBackupMaximalWithErrors()
    {
        $conf    = new Configuration('/tmp/foo.xml');
        $backup  = new Configuration\Backup('test-backup', false);
        $check   = new Configuration\Backup\Check('sizemin', '10M');
        $crypt   = new Configuration\Backup\Crypt('mcrypt', false);
        $sync    = new Configuration\Backup\Sync('rsync', false);
        $cleanup = new Configuration\Backup\Cleanup('capacity', false);
        $result  = new Result();
        $result->phpbuStart($conf);
        $result->backupStart($backup);
        $result->checkStart($check);
        $result->checkEnd($check);
        $result->cryptStart($crypt);
        $result->addError(new Exception('failed'));
        $result->cryptFailed($crypt);
        $result->syncStart($sync);
        $result->addError(new Exception('failed'));
        $result->syncFailed($sync);
        $result->cleanupStart($cleanup);
        $result->addError(new Exception('failed'));
        $result->cleanupFailed($cleanup);
        $result->backupEnd($backup);
        $result->phpbuEnd();

        $this->assertTrue($result->wasSuccessful(), 'should be successful');
        $this->assertFalse($result->allOk(), 'should be ok');
        $this->assertTrue($result->backupOkButSkipsOrFails(), 'crypt, sync and cleanup should be failed');
        $this->assertEquals(1, $result->syncsFailedCount());
        $this->assertEquals(0, $result->syncsSkippedCount());
        $this->assertEquals(1, $result->cryptsFailedCount());
        $this->assertEquals(0, $result->cryptsSkippedCount());
        $this->assertEquals(1, $result->cleanupsFailedCount());
        $this->assertEquals(0, $result->cleanupsSkippedCount());
        $this->assertEquals(3, $result->errorCount());
        $this->assertEquals(3, count($result->getErrors()));
        $this->assertEquals(1, count($result->getBackups()));
    }

    /**
     * Tests Backup failed.
     */
    public function testBackupFailed()
    {
        $conf    = new Configuration('/tmp/foo.xml');
        $backup  = new Configuration\Backup('test-backup', false);
        $result  = new Result();
        $result->phpbuStart($conf);
        $result->backupStart($backup);
        $result->addError(new Exception('failed'));
        $result->backupFailed($backup);
        $result->backupEnd($backup);
        $result->phpbuEnd();

        $this->assertFalse($result->wasSuccessful(), 'should be successful');
        $this->assertFalse($result->allOk(), 'should be ok');
        $this->assertEquals(1, $result->backupsFailedCount());
        $this->assertEquals(1, $result->errorCount());
        $this->assertEquals(1, count($result->getErrors()));
        $this->assertEquals(1, count($result->getBackups()));
    }

    /**
     * Tests Check failed.
     */
    public function testBackupFailedOnFailedCheck()
    {
        $conf   = new Configuration('/tmp/foo.xml');
        $backup = new Configuration\Backup('test-backup', false);
        $check  = new Configuration\Backup\Check('sizemin', '10M');
        $result = new Result();
        $result->phpbuStart($conf);
        $result->backupStart($backup);
        $result->backupEnd($backup);
        $result->checkStart($check);
        $result->addError(new Exception('failed'));
        $result->checkFailed($check);
        $result->backupFailed($backup);
        $result->backupEnd($backup);
        $result->phpbuEnd();

        $this->assertFalse($result->wasSuccessful(), 'should be successful');
        $this->assertFalse($result->allOk(), 'should be ok');
        $this->assertEquals(1, $result->backupsFailedCount());
        $this->assertEquals(1, $result->checksFailedCount());
        $this->assertEquals(1, $result->errorCount());
        $this->assertEquals(1, count($result->getErrors()));
        $this->assertEquals(1, count($result->getBackups()));
    }

    /**
     * Tests debug.
     */
    public function testDebug()
    {
        $conf   = new Configuration('/tmp/foo.xml');
        $backup = new Configuration\Backup('test-backup', false);
        $result = new Result();
        $result->phpbuStart($conf);
        $result->debug('debug');
        $result->backupStart($backup);
        $result->backupEnd($backup);
        $result->phpbuEnd();
    }
}
