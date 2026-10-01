<?php

namespace TheatreCMS\Tests\Unit\Migrations;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Migrations\SqlSplitter;

class SqlSplitterTest extends TestCase
{
    public function testSplitsOnSemicolonsAndTrims(): void
    {
        $this->assertSame(
            ['CREATE TABLE a (id INT)', 'ALTER TABLE a ADD b INT', 'SELECT 1'],
            SqlSplitter::split("CREATE TABLE a (id INT);\n\n  ALTER TABLE a ADD b INT ;\nSELECT 1"),
        );
    }

    public function testIgnoresSemicolonsInQuotesAndIdentifiers(): void
    {
        $sql = "INSERT INTO t VALUES ('a;b', \"c;d\");\n"
            . "INSERT INTO `odd;name` VALUES ('it''s; fine', 'back\\'slash;');\n"
            . 'SELECT 2';

        $this->assertSame([
            "INSERT INTO t VALUES ('a;b', \"c;d\")",
            "INSERT INTO `odd;name` VALUES ('it''s; fine', 'back\\'slash;')",
            'SELECT 2',
        ], SqlSplitter::split($sql));
    }

    public function testIgnoresSemicolonsInCommentsAndDropsCommentOnlyPieces(): void
    {
        $sql = "-- header; with a semicolon\n# another; comment\n"
            . "CREATE TABLE a (id INT); /* block; comment */\n"
            . "-- trailing note;\n";

        $this->assertSame(
            ["-- header; with a semicolon\n# another; comment\nCREATE TABLE a (id INT)"],
            SqlSplitter::split($sql),
        );
    }

    public function testKeepsMysqlVersionedComments(): void
    {
        $this->assertSame(
            ['/*!40101 SET NAMES utf8mb4 */', 'SELECT 1'],
            SqlSplitter::split("/*!40101 SET NAMES utf8mb4 */;\nSELECT 1;"),
        );
    }

    public function testEmptyInput(): void
    {
        $this->assertSame([], SqlSplitter::split("  \n-- nothing here\n"));
    }
}
