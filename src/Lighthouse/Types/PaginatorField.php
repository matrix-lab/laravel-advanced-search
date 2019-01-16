<?php

namespace MatrixLab\LaravelAdvancedSearch\Lighthouse\Types;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

class PaginatorField
{
    /**
     * Resolve paginator info for connection.
     *
     * @param AbstractPaginator $root
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo|null $info
     *
     * @return array
     */
    public function paginatorInfoResolver(
        AbstractPaginator $root,
        array $args,
        $context = null,
        ResolveInfo $info = null
    ) {
        $info = [
            'count'       => $root->count(),
            'currentPage' => $root->currentPage(),
            'firstItem'   => $root->firstItem(),
            'hasPages'    => $root->hasPages(),
            'lastItem'    => $root->lastItem(),
            'perPage'     => $root->perPage(),
        ];

        if ($root instanceof LengthAwarePaginator) {
            $info['total'] = $root->total();
        }

        return $info;
    }

    /**
     * Resolve data for connection.
     *
     * @param AbstractPaginator $root
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo|null $info
     *
     * @return \Illuminate\Support\Collection
     */
    public function dataResolver(
        AbstractPaginator $root,
        array $args,
        $context = null,
        ResolveInfo $info = null
    ) {
        return $root->values();
    }
}
