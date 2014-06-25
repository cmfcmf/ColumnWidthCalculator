ColumnWidthCalculator
=====================

Calculate "perfect" table column widths in PHP, based on [my question at StackOverflow](http://stackoverflow.com/q/24394787/2560557) and [@watcher's](http://stackoverflow.com/users/697370/watcher) [answer](http://stackoverflow.com/a/24395075/2560557).

### How to use
`$rows` needs to be an array of rows. Each `$row` needs to be an array of cells. Each `$cell` needs to have the cell's content in it.
```php
<?php
/**
 * @param array $rows An array of rows, where each row is an array of cells containing the cell content.
 * @param bool  $html Whether or not the rows contain html content. This will call html_entity_decode.
 * @param bool  $stripTags Whether or not to strip tags (only if $html is true).
 * @param int   $minPercentage The minimum percentage each row must be wide.
 * @param null  $customColumnFunction A custom function to transform a cell's value before it's length is measured.
 */
$columnWidthCalculator = new Cmfcmf\ColumnWidthCalculator($rows);
/**
 * Calculate the column widths.
 *
 * @return array
 *
 * Explanation of return array:
 * - $columnSizes[$colNumber]['percentage'] The calculated column width in percents.
 * - $columnSizes[$colNumber]['calc'] The calculated column width in letters.
 *
 * - $columnSizes[$colNumber]['max'] The maximum column width in letters.
 * - $columnSizes[$colNumber]['avg'] The average column width in letters.
 * - $columnSizes[$colNumber]['raw'] An array of all the column widths of this column in letters.
 * - $columnSizes[$colNumber]['stdd'] The calculated standard deviation in letters.
 *
 * INTERNAL
 * - $columnSizes[$colNumber]['cv'] The calculated standard deviation / the average column width in letters.
 * - $columnSizes[$colNumber]['stdd/max'] The calculated standard deviation / the maximum column width in letters.
 */
$columnSizes = $columnWidthCalculator->calculateWidths();
```

### License

GPLv2, see the LICENSE file.
