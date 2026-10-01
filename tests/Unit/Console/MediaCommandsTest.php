<?php

namespace TheatreCMS\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use TheatreCMS\Console\Command\MediaBackfillCommand;
use TheatreCMS\Console\Command\MediaRegenerateThumbnailsCommand;
use TheatreCMS\Console\Command\MediaRenameFilenamesCommand;
use TheatreCMS\Services\ImageBackfillService;
use TheatreCMS\Services\MediaFilenameBackfillService;
use TheatreCMS\Services\MediaVariantBackfillService;

class MediaCommandsTest extends TestCase
{
    public function testBackfillReportsCreatedAndRepointedRows(): void
    {
        $service = $this->createMock(ImageBackfillService::class);
        $service->expects($this->once())->method('scanUploads')->with(false)->willReturn(4);
        $service->expects($this->once())->method('repointAll')->with(false)->willReturn(['productions' => 2]);

        $tester = new CommandTester(new MediaBackfillCommand($service));

        $this->assertSame(0, $tester->execute([]));
        $this->assertSame(
            "Created 4 media row(s) from www/uploads/.\nRepointed 2 productions row(s) to their matching image.\n",
            $tester->getDisplay(true),
        );
    }

    public function testBackfillDryRun(): void
    {
        $service = $this->createMock(ImageBackfillService::class);
        $service->expects($this->once())->method('scanUploads')->with(true)->willReturn(1);
        $service->expects($this->once())->method('repointAll')->with(true)->willReturn([]);

        $tester = new CommandTester(new MediaBackfillCommand($service));
        $tester->execute(['--dry-run' => true]);

        $this->assertSame("[dry run] Would create 1 media row(s) from www/uploads/.\n", $tester->getDisplay(true));
    }

    public function testRegenerateThumbnailsPassesItsOptions(): void
    {
        $service = $this->createMock(MediaVariantBackfillService::class);
        $service->expects($this->once())->method('regenerate')->with(true, true, 'medium')->willReturn(['medium' => 3]);

        $tester = new CommandTester(new MediaRegenerateThumbnailsCommand($service));

        $this->assertSame(0, $tester->execute(['--dry-run' => true, '--force' => true, '--size' => 'medium']));
        $this->assertSame("[dry run] Would generate 3 'medium' thumbnail(s).\n", $tester->getDisplay(true));
    }

    public function testRegenerateThumbnailsDefaults(): void
    {
        $service = $this->createMock(MediaVariantBackfillService::class);
        $service->expects($this->once())->method('regenerate')->with(false, false, null)
            ->willReturn(['thumbnail' => 1, 'large' => 0]);

        $tester = new CommandTester(new MediaRegenerateThumbnailsCommand($service));
        $tester->execute([]);

        $this->assertSame(
            "Generated 1 'thumbnail' thumbnail(s).\nGenerated 0 'large' thumbnail(s).\n",
            $tester->getDisplay(true),
        );
    }

    public function testRenameFilenamesListsEachChange(): void
    {
        $service = $this->createMock(MediaFilenameBackfillService::class);
        $service->expects($this->once())->method('renameToSeoSlugs')->with(true)
            ->willReturn([['id' => 7, 'from' => 'a1b2.jpg', 'to' => 'hamlet.jpg']]);

        $tester = new CommandTester(new MediaRenameFilenamesCommand($service));

        $this->assertSame(0, $tester->execute(['--dry-run' => true]));
        $this->assertSame(
            "[dry run] Would rename media #7: a1b2.jpg -> hamlet.jpg\n[dry run] Would rename 1 file(s) total.\n",
            $tester->getDisplay(true),
        );
    }
}
