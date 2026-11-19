<?php

declare(strict_types=1);

namespace tests\app\models;

use app\entities\calendar\GlobalItem;
use app\models\GlobalCalendarInfo;
use Doctrine\ORM\EntityRepository;
use tests\BaseUnitTest;

class GlobalCalendarInfoTester extends BaseUnitTest {
    private $core;

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    private function createItem(int $id, string $date, string $text, string $type): GlobalItem {
        $item = $this->createMock(GlobalItem::class);
        $item->method('getId')->willReturn($id);
        $item->method('getDate')->willReturn(new \DateTime($date));
        $item->method('getText')->willReturn($text);
        $item->method('getTypeString')->willReturn($type);
        return $item;
    }

    /**
     * @param GlobalItem[] $items
     */
    private function loadInfo(array $items): GlobalCalendarInfo {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn($items);
        $this->core->getSubmittyEntityManager()
            ->method('getRepository')
            ->with(GlobalItem::class)
            ->willReturn($repository);

        return GlobalCalendarInfo::loadGlobalCalendarInfo($this->core);
    }

    public function testEmptyBeforeLoading(): void {
        $info = new GlobalCalendarInfo($this->core);

        $this->assertEquals([], $info->getGlobalItemsByDate());
        $this->assertEquals('', $info->getGlobalEmptyMessage());
    }

    public function testLoadWithNoItems(): void {
        $info = $this->loadInfo([]);

        $this->assertEquals([], $info->getGlobalItemsByDate());
        $this->assertEquals(
            'There are currently no assignments posted. Please check back later.',
            $info->getGlobalEmptyMessage()
        );
    }

    public function testItemsAreGroupedByDate(): void {
        $info = $this->loadInfo([
            $this->createItem(1, '2024-03-01', 'Spring break starts', 'text'),
            $this->createItem(2, '2024-03-05', 'Classes resume', 'ann'),
            $this->createItem(3, '2024-03-01', 'Office hours cancelled', 'ann'),
        ]);

        $items = $info->getGlobalItemsByDate();
        $this->assertEquals(['2024-03-01', '2024-03-05'], array_keys($items));
        $this->assertEquals(['Spring break starts', 'Office hours cancelled'], array_column($items['2024-03-01'], 'title'));
        $this->assertEquals([2], array_column($items['2024-03-05'], 'id'));
    }

    public function testItemFields(): void {
        $info = $this->loadInfo([$this->createItem(7, '2024-03-05', 'Classes resume', 'ann')]);

        $this->assertEquals([
            '2024-03-05' => [[
                'title' => 'Classes resume',
                'status' => 'ann',
                'course' => 'Superuser',
                'semester' => '',
                'icon' => '',
                'url' => '',
                'show_due' => false,
                'submission' => '',
                'status_note' => '',
                'color' => '',
                'type' => 'item',
                'date' => '2024-03-05',
                'id' => 7,
            ]],
        ], $info->getGlobalItemsByDate());
    }

    public function testTitleIsHtmlEscaped(): void {
        $info = $this->loadInfo([$this->createItem(1, '2024-03-01', '<b>Exam</b> & quiz', 'text')]);

        $this->assertEquals('&lt;b&gt;Exam&lt;/b&gt; &amp; quiz', $info->getGlobalItemsByDate()['2024-03-01'][0]['title']);
    }
}
