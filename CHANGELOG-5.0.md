CHANGELOG for 5.0.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 5.0 versions.

* 5.0.0 (2017-xx-xx)
 * Add `ruflin/elastica` 5.x support.
 * Add asnychronous index update option.
 * Add ability to close an index.
 * Dropped PHP 5.5 support.
 * Removed Symfony 3.0 support.
 * [BC break] Removed `ignore_unmapped` support for Paginator.
 * [BC break] Removed `_boost`, `ttl` and `timestamp` config options.
 * [BC break] Removed deprecated config options `servers`, `mappings` and `is_indexable_callback`.
 * [BC break] Add `PaginatedFinderInterface::createRawPaginatorAdapter`.
