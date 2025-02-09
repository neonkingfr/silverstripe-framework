<?php

namespace SilverStripe\ORM\Tests;

use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Tests\DataObjectTest\Player;
use SilverStripe\View\ArrayData;
use SilverStripe\Control\HTTPRequest;

/**
 * Tests for the {@link SilverStripe\ORM\PaginatedList} class.
 */
class PaginatedListTest extends SapphireTest
{

    protected static $fixture_file = 'DataObjectTest.yml';

    public static function getExtraDataObjects()
    {
        return array_merge(
            DataObjectTest::$extra_data_objects,
            ManyManyListTest::$extra_data_objects
        );
    }

    public function testPageStart()
    {
        $list = new PaginatedList(new ArrayList());
        $this->assertEquals(0, $list->getPageStart(), 'The start defaults to 0.');

        $list->setPageStart(10);
        $this->assertEquals(10, $list->getPageStart(), 'You can set the page start.');

        $list = new PaginatedList(new ArrayList(), ['start' => 50]);
        $this->assertEquals(50, $list->getPageStart(), 'The page start can be read from the request.');
    }

    public function testGetTotalItems()
    {
        $list = new PaginatedList(new ArrayList());
        $this->assertEquals(0, $list->getTotalItems());

        $list->setTotalItems(10);
        $this->assertEquals(10, $list->getTotalItems());

        $list = new PaginatedList(
            new ArrayList(
                [
                new ArrayData([]),
                new ArrayData([])
                ]
            )
        );
        $this->assertEquals(2, $list->getTotalItems());
    }

    public function testSetPaginationFromQuery()
    {
        $query = $this->getMockBuilder(SQLSelect::class)->getMock();
        $query->expects($this->once())
            ->method('getLimit')
            ->will($this->returnValue(['limit' => 15, 'start' => 30]));
        $query->expects($this->once())
            ->method('unlimitedRowCount')
            ->will($this->returnValue(100));

        $list = new PaginatedList(new ArrayList());
        $list->setPaginationFromQuery($query);

        $this->assertEquals(15, $list->getPageLength());
        $this->assertEquals(30, $list->getPageStart());
        $this->assertEquals(100, $list->getTotalItems());
    }

    public function testSetCurrentPage()
    {
        $list = new PaginatedList(new ArrayList());
        $list->setPageLength(10);
        $list->setCurrentPage(10);

        $this->assertEquals(10, $list->CurrentPage());
        $this->assertEquals(90, $list->getPageStart());

        // Test disabled paging
        $list->setPageLength(0);
        $this->assertEquals(1, $list->CurrentPage());
    }

    public function testGetIterator()
    {
        $list = new PaginatedList(
            new ArrayList([
                new DataObject(['Num' => 1]),
                new DataObject(['Num' => 2]),
                new DataObject(['Num' => 3]),
                new DataObject(['Num' => 4]),
                new DataObject(['Num' => 5]),
            ])
        );
        $list->setPageLength(2);

        $this->assertListEquals(
            [['Num' => 1], ['Num' => 2]],
            ArrayList::create($list->getIterator()->getInnerIterator()->getArrayCopy())
        );

        $list->setCurrentPage(2);
        $this->assertListEquals(
            [['Num' => 3], ['Num' => 4]],
            ArrayList::create($list->getIterator()->getInnerIterator()->getArrayCopy())
        );

        $list->setCurrentPage(3);
        $this->assertListEquals(
            [['Num' => 5]],
            ArrayList::create($list->getIterator()->getInnerIterator()->getArrayCopy())
        );

        $list->setCurrentPage(999);
        $this->assertListEquals(
            [],
            ArrayList::create($list->getIterator()->getInnerIterator()->getArrayCopy())
        );

        // Test disabled paging
        $list->setPageLength(0);
        $list->setCurrentPage(1);
        $this->assertListEquals(
            [
                ['Num' => 1],
                ['Num' => 2],
                ['Num' => 3],
                ['Num' => 4],
                ['Num' => 5],
            ],
            ArrayList::create($list->getIterator()->getInnerIterator()->getArrayCopy())
        );

        // Test with dataobjectset
        $players = Player::get();
        $list = new PaginatedList($players);
        $list->setPageLength(1);
        $list->getIterator();
        $this->assertEquals(
            4,
            $list->getTotalItems(),
            'Getting an iterator should not trim the list to the page length.'
        );
    }

    public function testPages()
    {
        $list = new PaginatedList(new ArrayList());
        $list->setPageLength(10);
        $list->setTotalItems(50);

        $this->assertCount(5, $list->Pages());
        $this->assertCount(3, $list->Pages(3));
        $this->assertCount(5, $list->Pages(15));

        $list->setCurrentPage(3);

        $expectAll = [
            ['PageNum' => 1],
            ['PageNum' => 2],
            ['PageNum' => 3, 'CurrentBool' => true],
            ['PageNum' => 4],
            ['PageNum' => 5],
        ];
        $this->assertListEquals($expectAll, $list->Pages());

        $expectLimited = [
            ['PageNum' => 2],
            ['PageNum' => 3, 'CurrentBool' => true],
            ['PageNum' => 4],
        ];
        $this->assertListEquals($expectLimited, $list->Pages(3));

        // Disable paging
        $list->setPageLength(0);
        $expectAll = [
            ['PageNum' => 1, 'CurrentBool' => true],
        ];
        $this->assertListEquals($expectAll, $list->Pages());
    }

    public function testPaginationSummary()
    {
        $list = new PaginatedList(new ArrayList());

        $list->setPageLength(10);
        $list->setTotalItems(250);
        $list->setCurrentPage(6);

        $expect = [
            ['PageNum' => 1],
            ['PageNum' => null],
            ['PageNum' => 4],
            ['PageNum' => 5],
            ['PageNum' => 6, 'CurrentBool' => true],
            ['PageNum' => 7],
            ['PageNum' => 8],
            ['PageNum' => null],
            ['PageNum' => 25],
        ];
        $this->assertListEquals($expect, $list->PaginationSummary(4));

        // Disable paging
        $list->setPageLength(0);
        $expect = [
            ['PageNum' => 1, 'CurrentBool' => true]
        ];
        $this->assertListEquals($expect, $list->PaginationSummary(4));
    }

    public function testLimitItems()
    {
        $list = new ArrayList(range(1, 50));
        $list = new PaginatedList($list);

        $list->setCurrentPage(3);
        $this->assertCount(10, $list->getIterator()->getInnerIterator());

        $list->setLimitItems(false);
        $this->assertCount(50, $list->getIterator()->getInnerIterator());
    }

    public function testCurrentPage()
    {
        $list = new PaginatedList(new ArrayList());
        $list->setTotalItems(50);

        $this->assertEquals(1, $list->CurrentPage());
        $list->setPageStart(10);
        $this->assertEquals(2, $list->CurrentPage());
        $list->setPageStart(40);
        $this->assertEquals(5, $list->CurrentPage());

        // Disable paging
        $list->setPageLength(0);
        $this->assertEquals(1, $list->CurrentPage());
    }

    public function testTotalPages()
    {
        $list = new PaginatedList(new ArrayList());

        $list->setPageLength(1);
        $this->assertEquals(0, $list->TotalPages());

        $list->setTotalItems(1);
        $this->assertEquals(1, $list->TotalPages());

        $list->setTotalItems(5);
        $this->assertEquals(5, $list->TotalPages());

        // Disable paging
        $list->setPageLength(0);
        $this->assertEquals(1, $list->TotalPages());

        $list->setTotalItems(0);
        $this->assertEquals(0, $list->TotalPages());
    }

    public function testMoreThanOnePage()
    {
        $list = new PaginatedList(new ArrayList());

        $list->setPageLength(1);
        $list->setTotalItems(1);
        $this->assertFalse($list->MoreThanOnePage());

        $list->setTotalItems(2);
        $this->assertTrue($list->MoreThanOnePage());

        // Disable paging
        $list->setPageLength(0);
        $this->assertFalse($list->MoreThanOnePage());
    }

    public function testFirstPage()
    {
        $list = new PaginatedList(new ArrayList());
        $this->assertTrue($list->FirstPage());
        $list->setCurrentPage(2);
        $this->assertFalse($list->FirstPage());
    }

    public function testNotFirstPage()
    {
        $list = new PaginatedList(new ArrayList());
        $this->assertFalse($list->NotFirstPage());
        $list->setCurrentPage(2);
        $this->assertTrue($list->NotFirstPage());
    }

    public function testLastPage()
    {
        $list = new PaginatedList(new ArrayList());
        $list->setTotalItems(50);

        $this->assertFalse($list->LastPage());
        $list->setCurrentPage(4);
        $this->assertFalse($list->LastPage());
        $list->setCurrentPage(5);
        $this->assertTrue($list->LastPage());
        $list->setCurrentPage(6);
        $this->assertTrue($list->LastPage());

        $emptyList = new PaginatedList(new ArrayList());
        $emptyList->setTotalItems(0);

        $this->assertTrue($emptyList->LastPage());
        $emptyList->setCurrentPage(1);
        $this->assertTrue($emptyList->LastPage());
    }

    public function testNotLastPage()
    {
        $list = new PaginatedList(new ArrayList());
        $list->setTotalItems(50);

        $this->assertTrue($list->NotLastPage());
        $list->setCurrentPage(5);
        $this->assertFalse($list->NotLastPage());
    }

    public function testFirstItem()
    {
        $list = new PaginatedList(new ArrayList());
        $this->assertEquals(1, $list->FirstItem());
        $list->setPageStart(10);
        $this->assertEquals(11, $list->FirstItem());
    }

    public function testLastItem()
    {
        $list = new PaginatedList(new ArrayList());
        $list->setPageLength(10);
        $list->setTotalItems(25);

        $list->setCurrentPage(1);
        $this->assertEquals(10, $list->LastItem());
        $list->setCurrentPage(2);
        $this->assertEquals(20, $list->LastItem());
        $list->setCurrentPage(3);
        $this->assertEquals(25, $list->LastItem());

        // Disable paging
        $list->setPageLength(0);
        $this->assertEquals(25, $list->LastItem());
    }

    public function testFirstLink()
    {
        $list = new PaginatedList(new ArrayList());
        $this->assertStringContainsString('start=0', $list->FirstLink());
    }

    public function testFirstLinkContainsCurrentGetParameters()
    {
        $request = new HTTPRequest(
            'GET',
            'http://example.com/my-cool-page',
            ['awesomeness' => 'nextLevel', 'start' => 20]
        );
        $list = new PaginatedList(new ArrayList(), $request);
        $list->setTotalItems(50);
        $list->setPageLength(10);

        // check the query string has correct parameters
        $queryString = parse_url($list->FirstLink() ?? '', PHP_URL_QUERY);
        parse_str($queryString ?? '', $queryParams);

        $this->assertArrayHasKey('awesomeness', $queryParams);
        $this->assertequals('nextLevel', $queryParams['awesomeness']);
        $this->assertArrayHasKey('start', $queryParams);
        $this->assertequals(0, $queryParams['start']);
    }

    public function testLastLink()
    {
        $list = new PaginatedList(new ArrayList());
        $list->setPageLength(10);
        $list->setTotalItems(100);
        $this->assertStringContainsString('start=90', $list->LastLink());

        // Disable paging
        $list->setPageLength(0);
        $this->assertStringContainsString('start=0', $list->LastLink());
    }

    public function testLastLinkContainsCurrentGetParameters()
    {
        $request = new HTTPRequest(
            'GET',
            'http://example.com/my-cool-page',
            ['awesomeness' => 'nextLevel']
        );
        $list = new PaginatedList(new ArrayList(), $request);
        $list->setTotalItems(50);
        $list->setPageLength(10);

        // check the query string has correct parameters
        $queryString = parse_url($list->LastLink() ?? '', PHP_URL_QUERY);
        parse_str($queryString ?? '', $queryParams);

        $this->assertArrayHasKey('awesomeness', $queryParams);
        $this->assertequals('nextLevel', $queryParams['awesomeness']);
        $this->assertArrayHasKey('start', $queryParams);
        $this->assertequals(40, $queryParams['start']);
    }

    public function testNextLink()
    {
        $list = new PaginatedList(new ArrayList());
        $list->setTotalItems(50);

        $this->assertStringContainsString('start=10', $list->NextLink());
        $list->setCurrentPage(2);
        $this->assertStringContainsString('start=20', $list->NextLink());
        $list->setCurrentPage(3);
        $this->assertStringContainsString('start=30', $list->NextLink());
        $list->setCurrentPage(4);
        $this->assertStringContainsString('start=40', $list->NextLink());
        $list->setCurrentPage(5);
        $this->assertNull($list->NextLink());

        // Disable paging
        $list->setCurrentPage(1);
        $list->setPageLength(0);
        $this->assertNull($list->NextLink());
    }

    public function testNextLinkContainsCurrentGetParameters()
    {
        $request = new HTTPRequest(
            'GET',
            'http://example.com/my-cool-page',
            ['awesomeness' => 'nextLevel']
        );
        $list = new PaginatedList(new ArrayList(), $request);
        $list->setTotalItems(50);
        $list->setPageLength(10);

        // check the query string has correct parameters
        $queryString = parse_url($list->NextLink() ?? '', PHP_URL_QUERY);
        parse_str($queryString ?? '', $queryParams);

        $this->assertArrayHasKey('awesomeness', $queryParams);
        $this->assertequals('nextLevel', $queryParams['awesomeness']);
        $this->assertArrayHasKey('start', $queryParams);
        $this->assertequals(10, $queryParams['start']);
    }

    public function testPrevLink()
    {
        $list = new PaginatedList(new ArrayList());
        $list->setTotalItems(50);

        $this->assertNull($list->PrevLink());
        $list->setCurrentPage(2);
        $this->assertStringContainsString('start=0', $list->PrevLink());
        $list->setCurrentPage(3);
        $this->assertStringContainsString('start=10', $list->PrevLink());
        $list->setCurrentPage(5);
        $this->assertStringContainsString('start=30', $list->PrevLink());

        // Disable paging
        $list->setPageLength(0);
        $this->assertNull($list->PrevLink());
    }

    public function testPrevLinkContainsCurrentGetParameters()
    {
        $request = new HTTPRequest(
            'GET',
            'http://example.com/my-cool-page',
            ['awesomeness' => 'nextLevel', 'start' => '30']
        );
        $list = new PaginatedList(new ArrayList(), $request);
        $list->setTotalItems(50);
        $list->setPageLength(10);

        // check the query string has correct parameters
        $queryString = parse_url($list->PrevLink() ?? '', PHP_URL_QUERY);
        parse_str($queryString ?? '', $queryParams);

        $this->assertArrayHasKey('awesomeness', $queryParams);
        $this->assertequals('nextLevel', $queryParams['awesomeness']);
        $this->assertArrayHasKey('start', $queryParams);
        $this->assertequals(20, $queryParams['start']);
    }
}
