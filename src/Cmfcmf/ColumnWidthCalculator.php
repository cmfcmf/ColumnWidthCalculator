<?php
/**
 * A simple class to auto-calculate the "perfect" column widths of a table.
 * Copyright (C) 2014 Christian Flach
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * This is based on my question at StackOverflow:
 * http://stackoverflow.com/questions/24394787/how-to-calculate-the-perfect-column-widths
 *
 * Thank you "watcher" (http://stackoverflow.com/users/697370/watcher) for the initial idea!
 */

namespace Cmfcmf;

class ColumnWidthCalculator
{
    /**
     * @var array
     */
    private $rows;

    /**
     * @var bool
     */
    private $html;

    /**
     * @var bool
     */
    private $stripTags;

    /**
     * @var int
     */
    private $minPercentage;

    /**
     * @var Callable|null
     */
    private $customColumnFunction;

    /**
     * @param array $rows An array of rows, where each row is an array of cells containing the cell content.
     * @param bool  $html Whether or not the rows contain html content. This will call html_entity_decode.
     * @param bool  $stripTags Whether or not to strip tags (only if $html is true).
     * @param int   $minPercentage The minimum percentage each row must be wide.
     * @param null  $customColumnFunction A custom function to transform a cell's value before it's length is measured.
     */
    public function __construct(array $rows, $html = false, $stripTags = false, $minPercentage = 3, $customColumnFunction = null)
    {
        $this->rows = $rows;
        $this->html = $html;
        $this->stripTags = $stripTags;
        $this->minPercentage = $minPercentage;
        $this->customColumnFunction = $customColumnFunction;
    }

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
    public function calculateWidths()
    {
        $columnSizes = array();

        foreach ($this->rows as $row) {
            foreach ($row as $key => $column) {
                if (isset($this->customColumnFunction)) {
                    $column = call_user_func_array($this->customColumnFunction, array($column));
                }
                $length = $this->strWidth($this->html ? html_entity_decode($this->stripTags ? strip_tags($column) : $column) : $column);

                $columnSizes[$key]['max'] = !isset($columnSizes[$key]['max']) ? $length : ($columnSizes[$key]['max'] < $length ? $length : $columnSizes[$key]['max']);

                // Sum up the lengths in `avg` for now. See below where it is converted to the actual average.
                $columnSizes[$key]['avg'] = !isset($columnSizes[$key]['avg']) ? $length : $columnSizes[$key]['avg'] + $length;
                $columnSizes[$key]['raw'][] = $length;
            }
        }

        // Calculate the actual averages.
        $columnSizes = array_map(function ($columnSize) {
            $columnSize['avg'] = $columnSize['avg'] / count ($columnSize['raw']);

            return $columnSize;
        }, $columnSizes);

        foreach ($columnSizes as $key => $columnSize) {
            $colMaxSize = $columnSize['max'];
            $colAvgSize = $columnSize['avg'];

            $stdDeviation = $this->sd($columnSize['raw']);
            $coefficientVariation = $stdDeviation / $colAvgSize;

            $columnSizes[$key]['cv'] = $coefficientVariation;
            $columnSizes[$key]['stdd'] = $stdDeviation;
            $columnSizes[$key]['stdd/max'] = $stdDeviation / $colMaxSize;

            // $columnSizes[$key]['stdd/max'] < 0.3 is here for no mathematical reason, it's been found by trying stuff
            if(($columnSizes[$key]['stdd/max'] < 0.3 || $coefficientVariation == 1) && ($coefficientVariation == 0 || ($coefficientVariation > 0.6 && $coefficientVariation < 1.5))) {
                // The average width of the column is close to the standard deviation
                // In this case I would just make the width of the column equal to the
                // average.
                $columnSizes[$key]['calc'] = $colAvgSize;
            } else {
                // There is a large variance in the dataset (really small values and
                // really large values in the same set).
                // Do some magic! (There is no mathematical rule behind that line, it's been created by trying different combinations.)
                if ($coefficientVariation > 1 && $columnSizes[$key]['stdd'] > 4.5 && $columnSizes[$key]['stdd/max'] > 0.2) {
                    $tmp = ($colMaxSize - $colAvgSize) / 2;
                } else {
                    $tmp = 0;
                }

                $columnSizes[$key]['calc'] = $colAvgSize + ($colMaxSize / $colAvgSize) * 2 / abs(1 - $coefficientVariation);
                $columnSizes[$key]['calc'] = $columnSizes[$key]['calc'] > $colMaxSize ? $colMaxSize - $tmp : $columnSizes[$key]['calc'];
            }
        }

        $totalCalculatedSize = 0;
        foreach ($columnSizes as $columnSize) {
            $totalCalculatedSize += $columnSize['calc'];
        }

        // Convert calculated sizes to percentages.
        foreach ($columnSizes as $key => $columnSize) {
            $columnSizes[$key]['percentage'] = 100 / ($totalCalculatedSize / $columnSize['calc']);
        }

        // Make sure everything is at least 3 percent wide.
        if ($this->minPercentage > 0) {
            foreach ($columnSizes as $key => $columnSize) {
                if ($columnSize['percentage'] < $this->minPercentage) {
                    // That's how many percent we need to steal.
                    $neededPercents = ($this->minPercentage - $columnSize['percentage']);

                    // Steal some percents from the column with the $coefficientVariation nearest to one and being big enough.
                    $lowestDistance = 9999999;
                    $stealKey = null;
                    foreach ($columnSizes as $k => $val) {
                        // This is the distance from the actual $coefficientVariation to 1.
                        $distance = abs(1 - $val['cv']);
                        if ($distance < $lowestDistance
                            && $val['calc'] - $neededPercents > $val['avg'] /* This line is here due to whatever reason :/ */
                            && $val['percentage'] - $this->minPercentage >= $neededPercents /* Make sure the column we steal from would still be wider than $this->minPercentage percent after stealing. */
                        ) {
                            $stealKey = $k;
                            $lowestDistance = $distance;
                        }
                    }
                    if (!isset($stealKey)) {
                        // Dang it! We could not get something reliable here. Fallback to stealing from the largest column.
                        $max = -1;
                        foreach ($columnSizes as $k => $val) {
                            if ($val['percentage'] > $max) {
                                $stealKey = $k;
                                $max = $val['percentage'];
                            }
                        }
                    }
                    $columnSizes[$stealKey]['percentage'] = $columnSizes[$stealKey]['percentage'] - $neededPercents;

                    $columnSizes[$key]['percentage'] = $this->minPercentage;
                }
            }
        }

        return $columnSizes;
    }

    /**
     * Function to calculate standard deviation.
     * http://stackoverflow.com/a/5434698/697370
     *
     * @param $array
     *
     * @return float
     */
    protected function sd($array)
    {
        if (count($array) == 1) {
            // Return 1 if we only have one value.
            return 1.0;
        }
        // Function to calculate square of value - mean
        $sd_square = function ($x, $mean) { return pow($x - $mean,2); };

        // square root of sum of squares devided by N-1
        return sqrt(array_sum(array_map($sd_square, $array, array_fill(0,count($array), (array_sum($array) / count($array)) ) ) ) / (count($array)-1) );
    }


    /**
     * Helper function to get the (approximate) width of a string. A normal character counts as 1, short characters
     * count as 0.4 and long characters count as 1.3.
     * The minimum width returned is 1.
     *
     * @param $text
     *
     * @return float
     */
    protected function strWidth($text)
    {
        $smallCharacters = array('!', 'i', 'f', 'j', 'l', ',', ';', '.', ':', '-', '|',
            ' ', /* normal whitespace */
            "\xC2", /* non breaking whitespace */
            "\xA0", /* non breaking whitespace */
            "\n",
            "\r",
            "\t",
            "\0",
            "\x0B" /* vertical tab */
        );
        $bigCharacters = array('w', 'm', '—', 'G', 'ß', '@');

        $width = strlen($text);
        foreach (count_chars($text, 1) as $i => $val) {
            if (in_array(chr($i), $smallCharacters)) {
                $width -= (0.6 * $val);
            }
            if (in_array(chr($i), $bigCharacters)) {
                $width += (0.3 * $val);
            }
        }
        if ($width < 1) {
            $width = 1;
        }

        return (float)$width;
    }
}
