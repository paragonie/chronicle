<?php
declare(strict_types=1);
namespace ParagonIE\Chronicle;

/**
 * Trait Pagination
 * @package ParagonIE\Chronicle
 */
trait Pagination
{
    /**
     * @param string $page
     * @return int
     */
    protected function getOffset(string $page = ''): int
    {
        /** @var int $p */
        $p = (int) $page;
        if ($p < 1) {
            $p = 1;
        }

        /** @var int $pageSize */
        $pageSize = Chronicle::getPageSize();
        return (int) (($p - 1) * $pageSize);
    }

    /**
     * @param int $offset
     * @param int $limit
     * @return string
     */
    protected function formatOffsetSuffix(int $offset, int $limit): string
    {
        switch (Chronicle::getDatabase()->getDriver()) {
            case 'mysql':
                return ' LIMIT ' . $offset . ', ' . $limit;
            case 'sqlite':
                return ' LIMIT ' . $limit . ' OFFSET ' . $offset;
            default:
                return ' OFFSET ' . $offset . ' LIMIT ' . $limit;
        }
    }
}