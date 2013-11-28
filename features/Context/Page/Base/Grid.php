<?php

namespace Context\Page\Base;

use Behat\Mink\Element\NodeElement;

/**
 * Page object for datagrid generated by the OroGridBundle
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Grid extends Index
{
    const FILTER_CONTAINS = 1;
    const FILTER_DOES_NOT_CONTAIN = 2;
    const FILTER_IS_EQUAL_TO = 3;
    const FILTER_STARTS_WITH = 4;
    const FILTER_ENDS_WITH = 5;

    /**
     * {@inheritdoc}
     */
    public function __construct($session, $pageFactory, $parameters = array())
    {
        parent::__construct($session, $pageFactory, $parameters);

        $this->elements = array_merge(
            array(
                'Grid'           => array('css' => 'table.grid'),
                'Grid content'   => array('css' => 'table.grid tbody'),
                'Filters'        => array('css' => 'div.filter-box'),
                'Grid toolbar'   => array('css' => 'div.grid-toolbar'),
                'Manage filters' => array('css' => 'div.filter-list')
            ),
            $this->elements
        );
    }

    /**
     * Returns the currently visible grid, if there is one
     *
     * @return NodeElement
     * @throws InvalidArgumentException
     */
    public function getGrid()
    {
        $grids = $this->getElement('Container')->findAll('css', $this->elements['Grid']['css']);

        foreach ($grids as $grid) {
            if ($grid->isVisible()) {
                return $grid;
            }
        }

        throw new \InvalidArgumentException('No visible grids found');
    }

    /**
     * Returns the grid body
     *
     * @return NodeElement
     */
    public function getGridContent()
    {
        return $this->getGrid()->find('css', 'tbody');
    }

    /**
     * Get a row from the grid containing the value asked
     * @param string $value
     *
     * @throws \InvalidArgumentException
     * @return NodeElement
     */
    public function getRow($value)
    {
        $value = str_replace('"', '', $value);
        $gridRow = $this->getGridContent()->find('css', sprintf('tr td:contains("%s")', $value));

        if (!$gridRow) {
            throw new \InvalidArgumentException(
                sprintf('Couldn\'t find a row for value "%s"', $value)
            );
        }

        return $gridRow->getParent();
    }

    /**
     * @param string $element
     * @param string $actionName
     */
    public function clickOnAction($element, $actionName)
    {
        $rowElement = $this->getRow($element);
        $rowElement->find('css', 'a.dropdown-toggle')->click();

        $action = $rowElement->find('css', sprintf('a.action[title="%s"]', $actionName));

        if (!$action) {
            throw new \InvalidArgumentException(
                sprintf('Could not find action "%s".', $actionName)
            );
        }

        $action->click();
    }

    /**
     * @param string $filterName The name of the filter
     * @param string $value      The value to filter by
     * @param string $operator   If false, no operator will be selected
     */
    public function filterBy($filterName, $value, $operator = false)
    {
        $filter = $this->getFilter($filterName);
        $this->openFilter($filter);

        if ($elt = $filter->find('css', 'select')) {
            $elt->selectOption($value);
        } elseif ($elt = $filter->find('css', 'div.filter-criteria')) {
            if ($operator !== false) {
                $filter->find('css', 'button.dropdown-toggle')->click();
                $filter->find('css', '[data-value="'.$operator.'"]')->click();
            }
            $elt->fillField('value', $value);
            $filter->find('css', 'button.filter-update')->click();
        } else {
            throw new \InvalidArgumentException(
                sprintf('Filtering by "%s" is not yet implemented"', $filterName)
            );
        }
    }

    /**
     * Count all rows in the grid
     * @return integer
     */
    public function countRows()
    {
        try {
            return count($this->getRows());
        } catch (\InvalidArgumentException $e) {
            return 0;
        }
    }

    /**
     * Get toolbar count
     * @throws \InvalidArgumentException
     * @return int
     */
    public function getToolbarCount()
    {
        $paginationText = $this
            ->getElement('Grid toolbar')
            ->find('css', 'div label:contains("record")')
            ->getText();

        // If pagination not found, count rows
        if (!$paginationText) {
            return $this->countRows();
        }

        if (preg_match('/([0-9][0-9 ]*) records?$/', $paginationText, $matches)) {
            return $matches[1];
        } else {
            throw new \InvalidArgumentException('Impossible to get count of datagrid records');
        }
    }

    /**
     * @param int $num
     */
    public function changePageSize($num)
    {
        assertContains($num, array(10, 25, 50, 100), 'Only 10, 25, 50 and 100 records per page are available');
        $element = $this->getElement('Grid toolbar')->find('css', '.page-size');
        $element->find('css', 'button')->click();
        $element->find('css', sprintf('ul.dropdown-menu li a:contains("%d")', $num))->click();
    }

    /**
     * Get the text in the specified column of the specified row
     * @param string $column
     * @param string $row
     *
     * @return string
     */
    public function getColumnValue($column, $row)
    {
        return $this->getRowCell($this->getRow($row), $this->getColumnPosition($column))->getText();
    }

    /**
     * Get an array of values in the specified column
     * @param string $column
     *
     * @return array
     */
    public function getValuesInColumn($column)
    {
        $column = $this->getColumnPosition($column);
        $rows   = $this->getRows();
        $values = array();

        foreach ($rows as $row) {
            $cell = $this->getRowCell($row, $column);
            if ($span = $cell->find('css', 'span')) {
                $values[] = (string) strpos($span->getAttribute('class'), 'success') !== false;
            } else {
                $values[] = $cell->getText();
            }
        }

        return $values;
    }

    /**
     * @param string $column
     *
     * @return integer
     */
    public function getColumnPosition($column)
    {
        $headers = $this->getColumnHeaders();
        foreach ($headers as $position => $header) {
            if (strtolower($column) === strtolower($header->getText())) {
                return $position;
            }
        }

        throw new \InvalidArgumentException(
            sprintf('Couldn\'t find a column "%s"', $column)
        );
    }

    /**
     * Sort rows by a column in the specified order
     *
     * @param string $column
     * @param string $order
     */
    public function sortBy($column, $order = 'ascending')
    {
        $sorter = $this->getColumnSorter($column);
        if ($sorter->getParent()->getAttribute('class') !== strtolower($order)) {
            $sorter->click();
        }
    }

    /**
     * Predicate to know if a column is sorted and ordered as we want
     *
     * @param string $column
     * @param string $order
     *
     * @return boolean
     */
    public function isSortedAndOrdered($column, $order)
    {
        $column = strtoupper($column);
        $order = strtolower($order);
        if ($this->getColumn($column)->getAttribute('class') !== $order) {
            return false;
        }

        $values = $this->getValuesInColumn($column);
        $sortedValues = $values;
        if ($order === 'ascending') {
            sort($sortedValues, SORT_NATURAL | SORT_FLAG_CASE);
        } else {
            rsort($sortedValues, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $sortedValues === $values;
    }

    /**
     * Count columns in datagrid
     *
     * @return integer
     */
    public function countColumns()
    {
        return count($this->getColumnHeaders(false, false));
    }

    /**
     * Get column
     *
     * @param string $columnName
     *
     * @throws \InvalidArgumentException
     * @return \Behat\Mink\Element\Element
     */
    public function getColumn($columnName)
    {
        $columnName = strtoupper($columnName);
        $columnHeaders = $this->getColumnHeaders();

        foreach ($columnHeaders as $columnHeader) {
            if ($columnHeader->getText() === $columnName) {
                return $columnHeader;
            }
        }

        throw new \InvalidArgumentException(
            sprintf('Couldn\'t find column "%s"', $columnName)
        );
    }

    /**
     * Get column sorter
     *
     * @param string $columnName
     *
     * @return \Behat\Mink\Element\Element
     */
    public function getColumnSorter($columnName)
    {
        if (!$this->getColumn($columnName)->find('css', 'a')) {
            throw new \InvalidArgumentException(
                sprintf('Column %s is not sortable', $columnName)
            );
        }

        return $this->getColumn($columnName)->find('css', 'a');
    }

    /**
     * Get grid filter from label name
     * @param string $filterName
     *
     * @throws \InvalidArgumentException
     * @return NodeElement
     */
    public function getFilter($filterName)
    {
        if (strtolower($filterName) === 'channel') {
            $filter = $this->getElement('Grid toolbar')->find('css', 'div.filter-item');
        } else {
            $filter = $this->getElement('Filters')->find('css', sprintf('div.filter-item:contains("%s")', $filterName));
        }

        if (!$filter) {
            throw new \InvalidArgumentException(
                sprintf('Couldn\'t find a filter with name "%s"', $filterName)
            );
        }

        return $filter;
    }

    /**
     * Show a filter from the management list
     * @param string $filterName
     */
    public function showFilter($filterName)
    {
        $this->clickFiltersList();
        $this->activateFilter($filterName);
        $this->clickFiltersList();
    }

    /**
     * Hide a filter from the management list
     * @param string $filterName
     */
    public function hideFilter($filterName)
    {
        $this->clickFiltersList();
        $this->deactivateFilter($filterName);
        $this->clickFiltersList();
    }

    /**
     * Click on the reset button of the datagrid toolbar
     * @throws \InvalidArgumentException
     */
    public function clickOnResetButton()
    {
        $resetBtn = $this
            ->getElement('Grid toolbar')
            ->find('css', sprintf('a:contains("%s")', 'Reset'));

        if (!$resetBtn) {
            throw new \InvalidArgumentException('Reset button not found');
        }

        $resetBtn->click();
    }

    /**
     * Click on the refresh button of the datagrid toolbar
     * @throws \InvalidArgumentException
     */
    public function clickOnRefreshButton()
    {
        $refreshBtn = $this
            ->getElement('Grid toolbar')
            ->find('css', sprintf('a:contains("%s")', 'Refresh'));

        if (!$refreshBtn) {
            throw new \InvalidArgumentException('Refresh button not found');
        }

        $refreshBtn->click();
    }

    /**
     * Activate a filter
     * @param string $filterName
     *
     * @throws \InvalidArgumentException
     */
    private function activateFilter($filterName)
    {
        if (!$this->getFilter($filterName)->isVisible()) {
            $this->clickOnFilterToManage($filterName);
        }

        if (!$this->getFilter($filterName)->isVisible()) {
            throw new \InvalidArgumentException(
                sprintf('Filter "%s" is not visible', $filterName)
            );
        }
    }

    /**
     * Deactivate filter
     * @param string $filterName
     *
     * @throws \InvalidArgumentException
     */
    private function deactivateFilter($filterName)
    {
        if ($this->getFilter($filterName)->isVisible()) {
            $this->clickOnFilterToManage($filterName);
        }

        if ($this->getFilter($filterName)->isVisible()) {
            throw new \InvalidArgumentException(
                sprintf('Filter "%s" is visible', $filterName)
            );
        }
    }

    /**
     * Click on a filter in filter management list
     * @param string $filterName
     *
     * @throws \InvalidArgumentException
     */
    private function clickOnFilterToManage($filterName)
    {
        try {
            $this
                ->getElement('Manage filters')
                ->find('css', sprintf('label:contains("%s")', $filterName))
                ->click();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                sprintf('Impossible to activate filter "%s"', $filterName)
            );
        }
    }

    /**
     * Open/close filters list
     */
    private function clickFiltersList()
    {
        $this
            ->getElement('Filters')
            ->find('css', 'a#add-filter-button')
            ->click();
    }

    /**
     * Select a row
     * @param string $value
     *
     * @throws \InvalidArgumentException
     * @return \Behat\Mink\Element\NodeElement|null
     */
    public function selectRow($value)
    {
        $row = $this->getRow($value);
        $checkbox = $row->find('css', 'input[type="checkbox"]');

        if (!$checkbox) {
            throw new \InvalidArgumentException(
                sprintf('Couldn\'t find a checkbox for row "%s"', $value)
            );
        }

        $checkbox->check();

        return $checkbox;
    }
    /**
     * @param NodeElement $row
     * @param string      $position
     *
     * @return NodeElement
     */
    protected function getRowCell($row, $position)
    {
        $cells = $row->findAll('css', 'td');

        $visibleCells = array();
        foreach ($cells as $cell) {
            $style = $cell->getAttribute('style');
            if (!$style || !preg_match('/display: ?none;/', $style)) {
                $visibleCells[] = $cell;
            }
        }

        $cells = $visibleCells;

        if (!isset($cells[$position])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Trying to access cell %d of a row which has %d cell(s).',
                    $position + 1,
                    count($cells)
                )
            );
        }

        return $cells[$position];
    }

    /**
     * Open the filter
     * @param NodeElement $filter
     *
     * @throws \InvalidArgumentException
     */
    protected function openFilter(NodeElement $filter)
    {
        if ($element = $filter->find('css', 'button')) {
            $element->click();
        } else {
            throw new \InvalidArgumentException(
                'Impossible to open filter or maybe its type is not yet implemented'
            );
        }
    }

    /**
     * Get column headers
     *
     * @param boolean $withHidden
     * @param boolean $withActions
     *
     * @return \Behat\Mink\Element\Element
     */
    protected function getColumnHeaders($withHidden = false, $withActions = true)
    {
        $headers = $this->getGrid()->findAll('css', 'thead th');

        if (!$withActions) {
            foreach ($headers as $key => $header) {
                if ($header->getAttribute('class') === 'action-column'
                    || $header->getAttribute('class') === 'select-all-header-cell') {
                    unset($headers[$key]);
                }
            }
        }

        if ($withHidden) {
            return $headers;
        }

        $visibleHeaders = array();
        foreach ($headers as $header) {
            $style = $header->getAttribute('style');
            if (!$style || !preg_match('/display: ?none;/', $style)) {
                $visibleHeaders[] = $header;
            }
        }

        return $visibleHeaders;
    }

    /**
     * Get rows
     *
     * @return \Behat\Mink\Element\Element
     */
    protected function getRows()
    {
        return $this->getGridContent()->findAll('css', 'tr');
    }

    /**
     * @param string $filterName The name of the price filter
     * @param string $action     Type of filtering (>, >=, etc.)
     * @param number $value      Value to filter
     * @param string $currency   Currency on which to filter
     */
    public function filterPerPrice($filterName, $action, $value, $currency)
    {
        $filter = $this->getFilter($filterName);
        if (!$filter) {
            throw new \Exception("Could not find filter for $filterName.");
        }

        $this->openFilter($filter);

        $criteriaElt = $filter->find('css', 'div.filter-criteria');
        $criteriaElt->fillField('value', $value);

        $buttons = $filter->findAll('css', '.currencyfilter button.dropdown-toggle');
        $actionButton = array_shift($buttons);
        $currencyButton = array_shift($buttons);

        // Open the dropdown menu with currency list and click on $currency line
        $currencyButton->click();
        $currencyButton->getParent()->find('css', sprintf('ul a:contains("%s")', $currency))->click();

        // Open the dropdown menu with action list and click on $action line
        $actionButton->click();
        $actionButton->getParent()->find('xpath', sprintf("//ul//a[text() = '%s']", $action))->click();

        $filter->find('css', 'button.filter-update')->click();
    }
}
